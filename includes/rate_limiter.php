<?php
/**
 * RATE LIMITER — Brute Force / Account Lockout Protection
 *
 * Tracks failed login attempts in the `login_attempts` table.
 * After RATE_LIMIT_MAX_ATTEMPTS failures within RATE_LIMIT_WINDOW seconds,
 * the account (by email) is locked for RATE_LIMIT_LOCKOUT seconds.
 *
 * NOTE: IP-based locking is intentionally removed to prevent one user's
 * lockout from affecting other users on the same network/IP.
 */

define('RATE_LIMIT_MAX_ATTEMPTS', 5);     // max failures before lockout
define('RATE_LIMIT_WINDOW',       600);   // sliding window in seconds (10 min)
define('RATE_LIMIT_LOCKOUT',      900);   // lockout duration in seconds (15 min)

class RateLimiter
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->ensureTable();
    }

    /**
     * Create the login_attempts table if it does not exist yet.
     */
    private function ensureTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `attempt_id`   INT          NOT NULL AUTO_INCREMENT,
                `identifier`   VARCHAR(255) NOT NULL,
                `ip_address`   VARCHAR(45)  NOT NULL,
                `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`attempt_id`),
                INDEX `idx_identifier` (`identifier`),
                INDEX `idx_ip`         (`ip_address`),
                INDEX `idx_time`       (`attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    /**
     * Purge stale attempts older than the lockout window.
     */
    private function purgeOld(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - RATE_LIMIT_LOCKOUT);
        $stmt = $this->conn->prepare(
            "DELETE FROM login_attempts WHERE attempted_at < ?"
        );
        if ($stmt) {
            $stmt->bind_param('s', $cutoff);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Count recent failures for a given identifier (email only) within the window.
     */
    private function countRecent(string $identifier): int
    {
        $window_start = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW);
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE identifier = ? AND attempted_at >= ?"
        );
        if (!$stmt) return 0;
        $stmt->bind_param('ss', $identifier, $window_start);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Returns true if the email is currently locked out.
     * Only locks by email — NOT by IP — to avoid blocking other users.
     */
    public function isLocked(string $email, string $ip): bool
    {
        $this->purgeOld();
        return $this->countRecent($email) >= RATE_LIMIT_MAX_ATTEMPTS;
    }

    /**
     * Record one failed attempt for the email only.
     * IP is stored for audit purposes but NOT used for lockout decisions.
     */
    public function recordFailure(string $email, string $ip): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)"
        );
        if (!$stmt) return;
        $stmt->bind_param('ss', $email, $ip);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clear all failure records for this email on successful login.
     */
    public function clearFailures(string $email, string $ip): void
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM login_attempts WHERE identifier = ?"
        );
        if (!$stmt) return;
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Returns how many seconds remain in the lockout, or 0 if not locked.
     */
    public function getRemainingLockout(string $email, string $ip): int
    {
        if (!$this->isLocked($email, $ip)) return 0;

        $window_start = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW);

        $stmt = $this->conn->prepare(
            "SELECT attempted_at FROM login_attempts
             WHERE identifier = ? AND attempted_at >= ?
             ORDER BY attempted_at ASC
             LIMIT 1"
        );
        if (!$stmt) return RATE_LIMIT_LOCKOUT;
        $stmt->bind_param('ss', $email, $window_start);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return 0;

        $unlock_at = strtotime($row['attempted_at']) + RATE_LIMIT_LOCKOUT;
        return max(0, $unlock_at - time());
    }

    /**
     * Returns the number of remaining attempts before lockout for a given email.
     */
    public function remainingAttempts(string $email, string $ip): int
    {
        $used = $this->countRecent($email);
        return max(0, RATE_LIMIT_MAX_ATTEMPTS - $used);
    }
}