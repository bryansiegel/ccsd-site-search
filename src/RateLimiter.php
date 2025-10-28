<?php

namespace CCSD\Search;

class RateLimiter
{
    private Database $db;
    private int $maxAttempts;
    private int $lockoutDuration;
    
    public function __construct(int $maxAttempts = 5, int $lockoutDuration = 900) // 15 minutes
    {
        $this->db = Database::getInstance();
        $this->maxAttempts = $maxAttempts;
        $this->lockoutDuration = $lockoutDuration;
        $this->createRateLimitTable();
    }
    
    private function createRateLimitTable(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 1,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                locked_until TIMESTAMP NULL,
                INDEX idx_ip_action (ip_address, action),
                INDEX idx_locked_until (locked_until)
            )
        ");
    }
    
    public function isRateLimited(string $action, string $ipAddress = null): bool
    {
        $ipAddress = $ipAddress ?: $this->getClientIp();
        
        $this->cleanupExpiredLocks();
        
        $record = $this->db->fetchOne(
            "SELECT * FROM rate_limits WHERE ip_address = ? AND action = ?",
            [$ipAddress, $action]
        );
        
        if (!$record) {
            return false;
        }
        
        if ($record['locked_until'] && strtotime($record['locked_until']) > time()) {
            return true;
        }
        
        return false;
    }
    
    public function recordAttempt(string $action, bool $successful = false, string $ipAddress = null): void
    {
        $ipAddress = $ipAddress ?: $this->getClientIp();
        
        if ($successful) {
            $this->db->query(
                "DELETE FROM rate_limits WHERE ip_address = ? AND action = ?",
                [$ipAddress, $action]
            );
            return;
        }
        
        $record = $this->db->fetchOne(
            "SELECT * FROM rate_limits WHERE ip_address = ? AND action = ?",
            [$ipAddress, $action]
        );
        
        if ($record) {
            $newAttempts = $record['attempts'] + 1;
            $lockedUntil = null;
            
            if ($newAttempts >= $this->maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $this->lockoutDuration);
            }
            
            $this->db->update('rate_limits', [
                'attempts' => $newAttempts,
                'locked_until' => $lockedUntil
            ], ['id' => $record['id']]);
        } else {
            $this->db->insert('rate_limits', [
                'ip_address' => $ipAddress,
                'action' => $action,
                'attempts' => 1
            ]);
        }
    }
    
    public function getRemainingAttempts(string $action, string $ipAddress = null): int
    {
        $ipAddress = $ipAddress ?: $this->getClientIp();
        
        $record = $this->db->fetchOne(
            "SELECT attempts FROM rate_limits WHERE ip_address = ? AND action = ?",
            [$ipAddress, $action]
        );
        
        if (!$record) {
            return $this->maxAttempts;
        }
        
        return max(0, $this->maxAttempts - $record['attempts']);
    }
    
    public function getTimeUntilUnlock(string $action, string $ipAddress = null): int
    {
        $ipAddress = $ipAddress ?: $this->getClientIp();
        
        $record = $this->db->fetchOne(
            "SELECT locked_until FROM rate_limits WHERE ip_address = ? AND action = ?",
            [$ipAddress, $action]
        );
        
        if (!$record || !$record['locked_until']) {
            return 0;
        }
        
        $lockTime = strtotime($record['locked_until']);
        return max(0, $lockTime - time());
    }
    
    private function cleanupExpiredLocks(): void
    {
        $this->db->query(
            "DELETE FROM rate_limits WHERE locked_until IS NOT NULL AND locked_until < NOW()"
        );
        
        $this->db->query(
            "DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
    
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}