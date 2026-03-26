<?php
/**
 * AUDIT LOGGING
 * Inserts an action record and signs it with an HMAC digest
 * so that direct database tampering can later be detected.
 */

require_once __DIR__ . '/audit_hmac.php';

/**
 * Log an action and immediately sign the row with HMAC-SHA256.
 *
 * @param mysqli $conn
 * @param int    $user_id  The actor's user_id
 * @param string $action   Human-readable description (max 255 chars enforced)
 */
function logAction(mysqli $conn, int $user_id, string $action): void
{
    // ── Input sanitisation ────────────────────────────────────────────────────
    // Truncate to the column's VARCHAR(255) limit so oversized inputs never
    // cause silent truncation or data-integrity surprises.
    $action = mb_substr(trim($action), 0, 255, 'UTF-8');

    // Strip control characters (null bytes, carriage returns, etc.) that could
    // corrupt log readers or enable log-injection attacks.
    $action = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $action);

    // ── Insert ────────────────────────────────────────────────────────────────
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)"
    );
    if (!$stmt) {
        error_log("logAction prepare failed: " . $conn->error);
        return;
    }
    $stmt->bind_param('is', $user_id, $action);

    if (!$stmt->execute()) {
        error_log("logAction execute failed: " . $stmt->error);
        $stmt->close();
        return;
    }

    $log_id = (int)$conn->insert_id;
    $stmt->close();

    // ── HMAC sign ─────────────────────────────────────────────────────────────
    // Sign the newly inserted row so the admin UI can verify integrity later.
    // Failures are logged but do not abort the request.
    if ($log_id > 0) {
        $signed = AuditHMAC::signAndStore($conn, $log_id);
        if (!$signed) {
            error_log("logAction: HMAC signing failed for log_id $log_id");
        }
    }
}