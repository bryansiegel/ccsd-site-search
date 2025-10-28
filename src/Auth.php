<?php

namespace CCSD\Search;

class Auth
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
    
    public function login(string $username, string $password): bool
    {
        $user = $this->db->fetchOne(
            "SELECT id, username, password_hash, role FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        
        return false;
    }
    
    public function logout(): void
    {
        session_destroy();
    }
    
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin(): bool
    {
        return $this->isLoggedIn() && $_SESSION['role'] === 'admin';
    }
    
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT id, username, email, role, created_at FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access denied. Admin privileges required.';
            exit;
        }
    }
    
    public function createUser(string $username, string $email, string $password, string $role = 'user'): int
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        return $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role
        ]);
    }
}