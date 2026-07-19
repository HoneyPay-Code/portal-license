<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;
use RuntimeException;

final class ReleaseService
{
    private const TOKEN_TTL_SECONDS = 1800;
    private const TOKEN_MAX_USES = 2;

    public function __construct(
        private PDO $pdo,
        private string $basePath,
        private LicenseService $licenses,
        private string $appUrl,
    ) {}

    public function storageDir(): string
    {
        $dir = $this->basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'releases';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** @return list<array<string, mixed>> */
    public function listReleases(): array
    {
        return $this->pdo->query(
            'SELECT * FROM releases ORDER BY is_current DESC, id DESC'
        )->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function currentRelease(): ?array
    {
        $row = $this->pdo->query(
            'SELECT * FROM releases WHERE is_current = 1 ORDER BY id DESC LIMIT 1'
        )->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM releases WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array<string, mixed>
     */
    public function createFromUpload(array $file, string $version, ?string $notes = null, bool $makeCurrent = true): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do arquivo.');
        }

        $name = (string) ($file['name'] ?? '');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new RuntimeException('Arquivo de upload inválido.');
        }

        if ($size <= 0 || $size > 512 * 1024 * 1024) {
            throw new RuntimeException('ZIP inválido ou maior que 512 MB.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            throw new RuntimeException('Envie um arquivo .zip.');
        }

        $version = trim($version);
        if ($version === '' || strlen($version) > 64) {
            throw new RuntimeException('Informe uma versão válida.');
        }

        $sha = hash_file('sha256', $tmp);
        if ($sha === false) {
            throw new RuntimeException('Não foi possível calcular o checksum.');
        }

        $safeVersion = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $version) ?: 'release';
        $storedName = $safeVersion.'-'.bin2hex(random_bytes(6)).'.zip';
        $dest = $this->storageDir().DIRECTORY_SEPARATOR.$storedName;

        if (! move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Não foi possível salvar o ZIP.');
        }

        $relative = 'storage/releases/'.$storedName;
        $now = gmdate('c');

        if ($makeCurrent) {
            $this->pdo->exec('UPDATE releases SET is_current = 0');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO releases (version, filename, storage_path, sha256, size_bytes, notes, is_current, created_at)
             VALUES (:version, :filename, :storage_path, :sha256, :size_bytes, :notes, :is_current, :created_at)'
        );
        $stmt->execute([
            'version' => $version,
            'filename' => $name !== '' ? $name : $storedName,
            'storage_path' => $relative,
            'sha256' => $sha,
            'size_bytes' => $size,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'is_current' => $makeCurrent ? 1 : 0,
            'created_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? throw new RuntimeException('Release não encontrada após upload.');
    }

    public function setCurrent(int $id): void
    {
        $release = $this->findById($id);
        if (! $release) {
            throw new RuntimeException('Release não encontrada.');
        }
        $this->pdo->exec('UPDATE releases SET is_current = 0');
        $stmt = $this->pdo->prepare('UPDATE releases SET is_current = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $release = $this->findById($id);
        if (! $release) {
            return;
        }

        $path = $this->absolutePath($release);
        $stmt = $this->pdo->prepare('DELETE FROM releases WHERE id = :id');
        $stmt->execute(['id' => $id]);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<string, mixed> $release
     */
    public function absolutePath(array $release): string
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) ($release['storage_path'] ?? ''));

        return $this->basePath.DIRECTORY_SEPARATOR.$relative;
    }

    public function customerMayDownload(int $customerId): bool
    {
        foreach ($this->licenses->listForCustomer($customerId) as $lic) {
            if ($this->licenseAllowsDownload($lic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ok:bool,message?:string,token?:string,download_url?:string,expires_at?:string,version?:string,sha256?:string,filename?:string,size_bytes?:int}
     */
    public function authorizeInstall(string $licenseKey, ?string $ip = null): array
    {
        $licenseKey = trim($licenseKey);
        if ($licenseKey === '') {
            return ['ok' => false, 'message' => 'license_key é obrigatória.'];
        }

        $license = $this->licenses->findLicense($licenseKey);
        if (! $license) {
            return ['ok' => false, 'message' => 'Licença inválida.'];
        }

        if (! $this->licenseAllowsDownload($license)) {
            return ['ok' => false, 'message' => 'Licença inativa, bloqueada ou expirada.'];
        }

        $release = $this->currentRelease();
        if (! $release) {
            return ['ok' => false, 'message' => 'Nenhum release disponível no momento.'];
        }

        $path = $this->absolutePath($release);
        if (! is_file($path)) {
            return ['ok' => false, 'message' => 'Arquivo do release não encontrado no servidor.'];
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expiresAt = gmdate('c', time() + self::TOKEN_TTL_SECONDS);
        $now = gmdate('c');

        $stmt = $this->pdo->prepare(
            'INSERT INTO install_download_tokens (token_hash, release_id, license_id, expires_at, max_uses, uses, created_ip, created_at)
             VALUES (:token_hash, :release_id, :license_id, :expires_at, :max_uses, 0, :created_ip, :created_at)'
        );
        $stmt->execute([
            'token_hash' => $hash,
            'release_id' => (int) $release['id'],
            'license_id' => (int) $license['id'],
            'expires_at' => $expiresAt,
            'max_uses' => self::TOKEN_MAX_USES,
            'created_ip' => $ip,
            'created_at' => $now,
        ]);

        return [
            'ok' => true,
            'token' => $plain,
            'download_url' => rtrim($this->appUrl, '/').'/api/v1/install/download?token='.rawurlencode($plain),
            'expires_at' => $expiresAt,
            'version' => (string) $release['version'],
            'sha256' => (string) $release['sha256'],
            'filename' => (string) $release['filename'],
            'size_bytes' => (int) $release['size_bytes'],
        ];
    }

    /**
     * @return array{release: array<string, mixed>, token_id: int}|null
     */
    public function consumeDownloadToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT t.*, r.version AS r_version, r.filename AS r_filename, r.storage_path AS r_storage_path,
                    r.sha256 AS r_sha256, r.size_bytes AS r_size_bytes, r.notes AS r_notes, r.is_current AS r_is_current,
                    r.created_at AS r_created_at, r.id AS r_id
             FROM install_download_tokens t
             INNER JOIN releases r ON r.id = t.release_id
             WHERE t.token_hash = :hash
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();
        if (! $row) {
            return null;
        }

        $expires = strtotime((string) $row['expires_at']);
        if ($expires === false || $expires < time()) {
            return null;
        }

        if ((int) $row['uses'] >= (int) $row['max_uses']) {
            return null;
        }

        $upd = $this->pdo->prepare(
            'UPDATE install_download_tokens SET uses = uses + 1 WHERE id = :id AND uses < max_uses'
        );
        $upd->execute(['id' => (int) $row['id']]);
        if ($upd->rowCount() < 1) {
            return null;
        }

        return [
            'token_id' => (int) $row['id'],
            'release' => [
                'id' => (int) $row['r_id'],
                'version' => $row['r_version'],
                'filename' => $row['r_filename'],
                'storage_path' => $row['r_storage_path'],
                'sha256' => $row['r_sha256'],
                'size_bytes' => (int) $row['r_size_bytes'],
                'notes' => $row['r_notes'],
                'is_current' => (int) $row['r_is_current'],
                'created_at' => $row['r_created_at'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $release
     * @return never
     */
    public function streamRelease(array $release, ?string $downloadName = null): void
    {
        $path = $this->absolutePath($release);
        if (! is_file($path)) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }

        $name = $downloadName ?: (string) ($release['filename'] ?? 'gateway.zip');
        $name = preg_replace('/[^\w.\-]+/', '_', $name) ?: 'gateway.zip';
        $size = filesize($path);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        if ($size !== false) {
            header('Content-Length: '.$size);
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        readfile($path);
        exit;
    }

    /** @param array<string, mixed> $license */
    private function licenseAllowsDownload(array $license): bool
    {
        $status = (string) ($license['status'] ?? '');
        if ($status !== 'active') {
            return false;
        }

        $expiresAt = $license['expires_at'] ?? null;
        if ($expiresAt !== null && $expiresAt !== '') {
            $ts = strtotime((string) $expiresAt);
            if ($ts !== false && $ts < time()) {
                return false;
            }
        }

        return true;
    }
}
