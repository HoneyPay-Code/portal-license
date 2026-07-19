<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class CustomerService
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function upsert(string $email, string $name, ?string $phone = null): array
    {
        $email = strtolower(trim($email));
        $existing = $this->findByEmail($email);
        $now = gmdate('c');
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE customers SET name = :name, phone = COALESCE(:phone, phone), updated_at = :u WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name !== '' ? $name : $existing['name'],
                'phone' => $phone,
                'u' => $now,
                'id' => (int) $existing['id'],
            ]);

            return $this->findById((int) $existing['id']) ?? $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (email, name, phone, password_hash, status, created_at, updated_at)
             VALUES (:email, :name, :phone, NULL, :status, :c, :u)'
        );
        $stmt->execute([
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
            'phone' => $phone,
            'status' => 'active',
            'c' => $now,
            'u' => $now,
        ]);

        return $this->findByEmail($email) ?? throw new \RuntimeException('customer create failed');
    }

    public function setPassword(int $customerId, string $password): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET password_hash = :h, updated_at = :u WHERE id = :id');
        $stmt->execute([
            'h' => password_hash($password, PASSWORD_DEFAULT),
            'u' => gmdate('c'),
            'id' => $customerId,
        ]);
    }

    public function attempt(string $email, string $password): ?array
    {
        $customer = $this->findByEmail($email);
        if (! $customer || empty($customer['password_hash'])) {
            return null;
        }
        if (! password_verify($password, (string) $customer['password_hash'])) {
            return null;
        }
        if (($customer['status'] ?? '') !== 'active') {
            return null;
        }

        return $customer;
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->pdo->query('SELECT * FROM customers ORDER BY id DESC')->fetchAll() ?: [];
    }

    /**
     * @return array{ok:bool,message:string,customer?:array<string,mixed>}
     */
    public function createManual(string $email, string $name, ?string $phone, ?string $password, string $status = 'active'): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'E-mail inválido.'];
        }
        if ($this->findByEmail($email)) {
            return ['ok' => false, 'message' => 'Já existe um cliente com este e-mail.'];
        }
        if (! in_array($status, ['active', 'blocked'], true)) {
            $status = 'active';
        }

        $now = gmdate('c');
        $hash = null;
        if ($password !== null && $password !== '') {
            if (strlen($password) < 8) {
                return ['ok' => false, 'message' => 'Senha deve ter no mínimo 8 caracteres.'];
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (email, name, phone, password_hash, status, created_at, updated_at)
             VALUES (:email, :name, :phone, :hash, :status, :c, :u)'
        );
        $stmt->execute([
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
            'phone' => $phone !== '' ? $phone : null,
            'hash' => $hash,
            'status' => $status,
            'c' => $now,
            'u' => $now,
        ]);

        $customer = $this->findByEmail($email);

        return ['ok' => true, 'message' => 'Cliente criado.', 'customer' => $customer ?? []];
    }

    /**
     * @return array{ok:bool,message:string,customer?:array<string,mixed>}
     */
    public function updateManual(
        int $id,
        string $email,
        string $name,
        ?string $phone,
        string $status,
        ?string $newPassword = null,
    ): array {
        $customer = $this->findById($id);
        if (! $customer) {
            return ['ok' => false, 'message' => 'Cliente não encontrado.'];
        }

        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'E-mail inválido.'];
        }

        $other = $this->findByEmail($email);
        if ($other && (int) $other['id'] !== $id) {
            return ['ok' => false, 'message' => 'Já existe outro cliente com este e-mail.'];
        }

        if (! in_array($status, ['active', 'blocked'], true)) {
            return ['ok' => false, 'message' => 'Status inválido.'];
        }

        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return ['ok' => false, 'message' => 'Senha deve ter no mínimo 8 caracteres.'];
            }
            $this->setPassword($id, $newPassword);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE customers SET email = :email, name = :name, phone = :phone, status = :status, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
            'phone' => $phone !== '' ? $phone : null,
            'status' => $status,
            'u' => gmdate('c'),
            'id' => $id,
        ]);

        return ['ok' => true, 'message' => 'Cliente atualizado.', 'customer' => $this->findById($id) ?? []];
    }

    public function clearPassword(int $customerId): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET password_hash = NULL, updated_at = :u WHERE id = :id');
        $stmt->execute(['u' => gmdate('c'), 'id' => $customerId]);
    }

    public function createResetToken(int $customerId, int $ttlMinutes = 60): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (customer_id, token_hash, expires_at, created_at)
             VALUES (:cid, :hash, :exp, :c)'
        );
        $stmt->execute([
            'cid' => $customerId,
            'hash' => hash('sha256', $token),
            'exp' => gmdate('c', time() + ($ttlMinutes * 60)),
            'c' => gmdate('c'),
        ]);

        return $token;
    }

    /** @return array<string, mixed>|null */
    public function consumeResetToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM password_resets WHERE token_hash = :h AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch();
        if (! $row) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $upd = $this->pdo->prepare('UPDATE password_resets SET used_at = :u WHERE id = :id');
        $upd->execute(['u' => gmdate('c'), 'id' => (int) $row['id']]);

        return $this->findById((int) $row['customer_id']);
    }

    /**
     * @return array{ok:bool,message:string,customer?:array<string,mixed>}
     */
    public function updateOwnProfile(int $id, string $name, ?string $phone): array
    {
        $customer = $this->findById($id);
        if (! $customer) {
            return ['ok' => false, 'message' => 'Conta não encontrada.'];
        }
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'Informe seu nome.'];
        }
        $phone = $phone !== null ? trim($phone) : null;
        $stmt = $this->pdo->prepare(
            'UPDATE customers SET name = :name, phone = :phone, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'phone' => ($phone !== null && $phone !== '') ? $phone : null,
            'u' => gmdate('c'),
            'id' => $id,
        ]);

        return ['ok' => true, 'message' => 'Perfil atualizado.', 'customer' => $this->findById($id) ?? []];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function changeOwnPassword(int $id, string $currentPassword, string $newPassword): array
    {
        $customer = $this->findById($id);
        if (! $customer) {
            return ['ok' => false, 'message' => 'Conta não encontrada.'];
        }
        if (empty($customer['password_hash']) || ! password_verify($currentPassword, (string) $customer['password_hash'])) {
            return ['ok' => false, 'message' => 'Senha atual incorreta.'];
        }

        $err = Security::validatePassword($newPassword, 8, false);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }
        if (password_verify($newPassword, (string) $customer['password_hash'])) {
            return ['ok' => false, 'message' => 'A nova senha deve ser diferente da atual.'];
        }

        $this->setPassword($id, $newPassword);

        return ['ok' => true, 'message' => 'Senha atualizada.'];
    }
}
