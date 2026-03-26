<?php
/**
 * AUDIT LOG HMAC INTEGRITY
 *
 * Signs each audit log row with an HMAC-SHA256 digest so that direct
 * database tampering can be detected by the admin UI.
 *
 * The secret key is derived from a constant defined here (AUDIT_HMAC_SECRET).
 * In production, move this value to an environment variable or a config file
 * outside the webroot and never commit it to version control.
 *
 * Database change required — add a column to audit_logs:
 *   ALTER TABLE audit_logs ADD COLUMN `row_hmac` VARCHAR(64) DEFAULT NULL;
 *
 * Usage:
 *   require_once 'audit_hmac.php';
 *   $hmac = AuditHMAC::sign($log_id, $user_id, $action, $action_time);
 *   AuditHMAC::verify($log_id, $user_id, $action, $action_time, $stored_hmac); // bool
 */

// ── HMAC Secret ───────────────────────────────────────────────────────────────
// IMPORTANT: Change this to a strong random value in your deployment.
// Generate one with: php -r "echo bin2hex(random_bytes(32));"
// In production, load from an env var: getenv('AUDIT_HMAC_SECRET')
if (!defined('AUDIT_HMAC_SECRET')) {
    define('AUDIT_HMAC_SECRET', '5ed5cef103ffebabfd70bb8b7243d15865646872e0a6185f77da6ac439813c16');
}

class AuditHMAC
{
    /**
     * Build the canonical message string for a log row.
     * All fields that identify the row are included so any change is detectable.
     */
    private static function canonical(
        int    $log_id,
        int    $user_id,
        string $action,
        string $action_time
    ): string {
        // Pipe-delimited, double-escaped pipes within values to avoid collisions
        return implode('|', [
            $log_id,
            $user_id,
            str_replace('|', '\\|', $action),
            $action_time,
        ]);
    }

    /**
     * Compute and return the HMAC-SHA256 hex digest for a log row.
     */
    public static function sign(
        int    $log_id,
        int    $user_id,
        string $action,
        string $action_time
    ): string {
        return hash_hmac(
            'sha256',
            self::canonical($log_id, $user_id, $action, $action_time),
            AUDIT_HMAC_SECRET
        );
    }

    /**
     * Verify a stored HMAC against the current row values.
     * Returns true if intact, false if tampered or missing.
     */
    public static function verify(
        int    $log_id,
        int    $user_id,
        string $action,
        string $action_time,
        ?string $stored_hmac
    ): bool {
        if (empty($stored_hmac)) return false;

        $expected = self::sign($log_id, $user_id, $action, $action_time);
        // Constant-time comparison to prevent timing attacks
        return hash_equals($expected, $stored_hmac);
    }

    /**
     * Convenience: compute HMAC and immediately update the audit_logs row.
     * Call this right after inserting a new log row.
     *
     * @param mysqli $conn
     * @param int    $log_id  The newly inserted log_id
     */
    public static function signAndStore(mysqli $conn, int $log_id): bool
    {
        // Fetch the row we just inserted
        $stmt = $conn->prepare(
            "SELECT user_id, action, action_time FROM audit_logs WHERE log_id = ?"
        );
        if (!$stmt) return false;
        $stmt->bind_param('i', $log_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return false;

        $hmac = self::sign(
            $log_id,
            (int)$row['user_id'],
            $row['action'],
            $row['action_time']
        );

        $upd = $conn->prepare(
            "UPDATE audit_logs SET row_hmac = ? WHERE log_id = ?"
        );
        if (!$upd) return false;
        $upd->bind_param('si', $hmac, $log_id);
        $result = $upd->execute();
        $upd->close();
        return $result;
    }

    /**
     * Bulk-verify all rows in the audit_logs table.
     * Returns an array of log_ids where the HMAC does NOT match (tampered rows).
     * An empty array means all rows are intact.
     *
     * @param mysqli $conn
     * @return int[]  Array of tampered log_ids
     */
    public static function bulkVerify(mysqli $conn): array
    {
        $result = $conn->query(
            "SELECT log_id, user_id, action, action_time, row_hmac FROM audit_logs ORDER BY log_id"
        );
        if (!$result) return [];

        $tampered = [];
        while ($row = $result->fetch_assoc()) {
            if (!self::verify(
                (int)$row['log_id'],
                (int)$row['user_id'],
                $row['action'],
                $row['action_time'],
                $row['row_hmac']
            )) {
                $tampered[] = (int)$row['log_id'];
            }
        }
        return $tampered;
    }
}