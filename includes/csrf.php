<?php
/**
 * CSRF PROTECTION
 * Generates and validates per-session CSRF tokens.
 * Validation failures are logged to the audit trail.
 */

require_once __DIR__ . '/session.php';

/**
 * Return the current session CSRF token, generating one if needed.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Constant-time comparison of the submitted token vs the session token.
 *
 * @param string|null $token  Value from $_POST['csrf_token']
 * @return bool
 */
function csrf_check(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate the CSRF token and die (with audit log entry) on failure.
 *
 * @param string|null $token  Value from $_POST['csrf_token']
 */
function csrf_validate_or_die(?string $token): void
{
    if (!csrf_check($token)) {

        // ── Log the CSRF failure ──────────────────────────────────────────────
        if (isset($GLOBALS['conn'])) {
            $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $page   = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown', ENT_QUOTES);
            $uid    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $action = "CSRF VALIDATION FAILED: POST to $page from IP $ip";

            // logAction requires a valid user_id; use 0 for unauthenticated attempts
            if (function_exists('logAction')) {
                logAction($GLOBALS['conn'], $uid, $action);
            }
        }

        http_response_code(400);
        die("Invalid or missing security token. Please go back and try again.");
    }
}