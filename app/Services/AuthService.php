<?php
// app/Services/AuthService.php
namespace App\Services;

use PDO;

class AuthService
{
    private PDO $db;

    public function __construct(DatabaseService $database)
    {
        $this->db = $database->getConnection();
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->db->prepare("SELECT id, password FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
    }

    public function isLogged(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function createUser(string $email, string $plainPassword): int
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
        $stmt->execute(['email' => $email, 'password' => $hash]);
        return (int)$this->db->lastInsertId();
    }
}
