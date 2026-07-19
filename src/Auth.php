<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo) {}

    public function ensureBootstrapAdmin(string $email, string $password): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO admins (email, password_hash, created_at) VALUES (:email, :hash, :created_at)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => gmdate('c'),
        ]);
    }

    /**
     * Password-only check. Returns admin row or null.
     *
     * @return array<string, mixed>|null
     */
    public function verifyAdminPassword(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $admin = $stmt->fetch();
        if (! $admin || ! password_verify($password, (string) $admin['password_hash'])) {
            return null;
        }

        return $admin;
    }

    public function attemptAdmin(string $email, string $password): bool
    {
        $admin = $this->verifyAdminPassword($email, $password);
        if (! $admin) {
            return false;
        }
        if (! empty($admin['totp_enabled'])) {
            session_regenerate_id(true);
            $_SESSION['admin_2fa_pending_id'] = (int) $admin['id'];
            $_SESSION['admin_2fa_pending_email'] = (string) $admin['email'];
            unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['customer_id'], $_SESSION['customer_email']);
            Security::rotateCsrfToken();

            return true; // caller must redirect to /admin/2fa
        }
        $this->completeAdminLogin($admin);

        return true;
    }

    /** @param array<string, mixed> $admin */
    public function completeAdminLogin(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_email'] = (string) $admin['email'];
        unset(
            $_SESSION['customer_id'],
            $_SESSION['customer_email'],
            $_SESSION['admin_2fa_pending_id'],
            $_SESSION['admin_2fa_pending_email']
        );
        Security::rotateCsrfToken();
    }

    public function loginCustomer(array $customer): void
    {
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $customer['id'];
        $_SESSION['customer_email'] = (string) $customer['email'];
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_email'],
            $_SESSION['admin_2fa_pending_id'],
            $_SESSION['admin_2fa_pending_email']
        );
        Security::rotateCsrfToken();
    }

    public function adminCheck(): bool
    {
        if (! isset($_SESSION['admin_id'])) {
            return false;
        }
        $id = (int) $_SESSION['admin_id'];
        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if (! $stmt->fetch()) {
            unset($_SESSION['admin_id'], $_SESSION['admin_email']);

            return false;
        }

        return true;
    }

    public function adminPending2fa(): bool
    {
        return isset($_SESSION['admin_2fa_pending_id']);
    }

    public function pendingAdminId(): ?int
    {
        return isset($_SESSION['admin_2fa_pending_id']) ? (int) $_SESSION['admin_2fa_pending_id'] : null;
    }

    public function customerCheck(): bool
    {
        return isset($_SESSION['customer_id']);
    }

    public function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public function adminEmail(): ?string
    {
        return isset($_SESSION['admin_email']) ? (string) $_SESSION['admin_email'] : null;
    }

    public function customerId(): ?int
    {
        return isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null;
    }

    public function customerEmail(): ?string
    {
        return isset($_SESSION['customer_email']) ? (string) $_SESSION['customer_email'] : null;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    /** @return array<string, mixed>|null */
    public function findAdminById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array{id:int,email:string,totp_enabled:int,created_at:string}> */
    public function listAdmins(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, email, totp_enabled, created_at FROM admins ORDER BY id ASC'
        )->fetchAll() ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'totp_enabled' => (int) ($row['totp_enabled'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    public function adminCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function updateAdminEmail(int $adminId, string $newEmail, string $currentPassword): array
    {
        $admin = $this->findAdminById($adminId);
        if (! $admin || ! password_verify($currentPassword, (string) $admin['password_hash'])) {
            return ['ok' => false, 'message' => 'Senha atual incorreta.'];
        }

        $newEmail = strtolower(trim($newEmail));
        if ($newEmail === '' || ! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'E-mail inválido.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE email = :e AND id != :id LIMIT 1');
        $stmt->execute(['e' => $newEmail, 'id' => $adminId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Já existe um admin com este e-mail.'];
        }

        $upd = $this->pdo->prepare('UPDATE admins SET email = :e WHERE id = :id');
        $upd->execute(['e' => $newEmail, 'id' => $adminId]);

        if ($this->adminId() === $adminId) {
            $_SESSION['admin_email'] = $newEmail;
        }

        return ['ok' => true, 'message' => 'E-mail atualizado.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function updateAdminPassword(int $adminId, string $currentPassword, string $newPassword): array
    {
        $admin = $this->findAdminById($adminId);
        if (! $admin || ! password_verify($currentPassword, (string) $admin['password_hash'])) {
            return ['ok' => false, 'message' => 'Senha atual incorreta.'];
        }

        $err = Security::validatePassword($newPassword, 10, true);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        if (password_verify($newPassword, (string) $admin['password_hash'])) {
            return ['ok' => false, 'message' => 'A nova senha deve ser diferente da atual.'];
        }

        $upd = $this->pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id');
        $upd->execute([
            'h' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $adminId,
        ]);

        return ['ok' => true, 'message' => 'Senha atualizada.'];
    }

    /**
     * @return array{ok:bool,message:string,admin?:array{id:int,email:string}}
     */
    public function createAdmin(string $email, string $password, string $actorPassword, int $actorAdminId): array
    {
        $actor = $this->findAdminById($actorAdminId);
        if (! $actor || ! password_verify($actorPassword, (string) $actor['password_hash'])) {
            return ['ok' => false, 'message' => 'Confirme com a sua senha atual para criar outro admin.'];
        }

        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'E-mail inválido.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM admins WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Já existe um admin com este e-mail.'];
        }

        $err = Security::validatePassword($password, 10, true);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO admins (email, password_hash, created_at) VALUES (:email, :hash, :created_at)'
        );
        $ins->execute([
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => gmdate('c'),
        ]);

        return [
            'ok' => true,
            'message' => 'Admin criado. Peça para ele entrar e ativar 2FA.',
            'admin' => ['id' => (int) $this->pdo->lastInsertId(), 'email' => $email],
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function deleteAdmin(int $targetId, int $actorAdminId, string $actorPassword): array
    {
        if ($targetId === $actorAdminId) {
            return ['ok' => false, 'message' => 'Você não pode excluir a própria conta por aqui.'];
        }
        if ($this->adminCount() <= 1) {
            return ['ok' => false, 'message' => 'Não é possível excluir o único admin.'];
        }

        $actor = $this->findAdminById($actorAdminId);
        if (! $actor || ! password_verify($actorPassword, (string) $actor['password_hash'])) {
            return ['ok' => false, 'message' => 'Senha atual incorreta.'];
        }

        $target = $this->findAdminById($targetId);
        if (! $target) {
            return ['ok' => false, 'message' => 'Admin não encontrado.'];
        }

        $del = $this->pdo->prepare('DELETE FROM admins WHERE id = :id');
        $del->execute(['id' => $targetId]);

        return ['ok' => true, 'message' => 'Admin removido.'];
    }
}
