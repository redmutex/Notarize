<?php
declare(strict_types=1);

namespace App;

use PDO;

class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function register(string $name, string $email, string $password): array
    {
        $name  = trim($name);
        $email = strtolower(trim($email));

        if (strlen($name) < 2) {
            return ['error' => 'Name must be at least 2 characters.'];
        }
        if (strlen($name) > 200) {
            return ['error' => 'Name is too long (maximum 200 characters).'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email address.'];
        }
        if (strlen($email) > 254) {
            return ['error' => 'Email address is too long.'];
        }
        if (strlen($password) < 8) {
            return ['error' => 'Password must be at least 8 characters.'];
        }
        if (strlen($password) > 1000) {
            return ['error' => 'Password is too long.'];
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['error' => 'An account with that email already exists.'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT)]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limiting: block IP after 15 failed attempts in 15 minutes
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE (ip = ? OR email = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip, $email]);
        if ((int)$stmt->fetchColumn() >= 15) {
            return ['error' => 'Too many failed attempts. Please try again in 15 minutes.'];
        }

        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->db->prepare(
                "INSERT INTO login_attempts (ip, email) VALUES (?, ?)"
            )->execute([$ip, $email]);
            // Prune old rows probabilistically to keep the table small
            if (mt_rand(1, 50) === 1) {
                $this->db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
            }
            return ['error' => 'Invalid email or password.'];
        }

        // Successful login — clear attempt history for this IP/email
        $this->db->prepare(
            "DELETE FROM login_attempts WHERE ip = ? OR email = ?"
        )->execute([$ip, $email]);

        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_is_admin'] = (bool)($user['is_admin'] ?? false);

        return ['success' => true];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public function user(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return [
            'id'       => (int)$_SESSION['user_id'],
            'name'     => $_SESSION['user_name'],
            'email'    => $_SESSION['user_email'],
            'is_admin' => (bool)($_SESSION['user_is_admin'] ?? false),
        ];
    }
}
