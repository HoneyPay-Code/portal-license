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
}
