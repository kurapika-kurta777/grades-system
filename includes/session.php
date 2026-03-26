<?php
/**
 * SESSION CONFIGURATION
 * Hardened session initialization with:
 *  - Strict mode (reject unrecognized session IDs)
 *  - HttpOnly + SameSite=Strict cookies
 *  - Inactivity timeout (SESSION_IDLE_TIMEOUT seconds)
 *  - Absolute session lifetime (SESSION_ABS_TIMEOUT seconds)
 *  - Session fixation prevention on every regeneration
 */

define('SESSION_IDLE_TIMEOUT', 1800);   // 30 minutes of inactivity → auto-logout
define('SESSION_ABS_TIMEOUT',  28800);  // 8 hours absolute max → force re-login

ini_set('session.use_strict_mode',    1);
ini_set('session.use_only_cookies',   1);
ini_set('session.use_trans_sid',      0);
ini_set('session.cookie_httponly',    1);
ini_set('session.cookie_samesite', 'Strict');
// session.gc_maxlifetime controls server-side session file TTL
ini_set('session.gc_maxlifetime', SESSION_ABS_TIMEOUT);

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,                                             // browser-session cookie (expires on close)
    'path'     => $cookieParams['path'] ?? '/',
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Inactivity timeout ────────────────────────────────────────────────────────
// If the user has been idle longer than SESSION_IDLE_TIMEOUT, destroy the session.
if (isset($_SESSION['_last_activity'])) {
    $idle = time() - (int)$_SESSION['_last_activity'];
    if ($idle > SESSION_IDLE_TIMEOUT) {
        session_unset();
        session_destroy();

        // Restart a clean session so the page can still function (show login form)
        session_start();
        $_SESSION['_session_expired'] = true;  // flag so login page can show a message
    }
}
$_SESSION['_last_activity'] = time();

// ── Absolute session lifetime ─────────────────────────────────────────────────
if (isset($_SESSION['_session_created'])) {
    $age = time() - (int)$_SESSION['_session_created'];
    if ($age > SESSION_ABS_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['_session_expired'] = true;
    }
} else {
    $_SESSION['_session_created'] = time();
}

// NOTE: User-Agent + IP fingerprinting has been intentionally removed.
// Browsers can alter the User-Agent string slightly (e.g. when DevTools is
// opened, or due to minor browser updates), which caused false "session
// hijacking" detections and logged legitimate users out immediately.
// The inactivity timeout and absolute lifetime above provide sufficient
// session-expiry protection without false positives.

/**
 * Regenerate session ID after privilege changes (login, role switches).
 * Deletes the old session file to prevent fixation.
 */
function secure_session_regenerate(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);   // true = delete old session
        // Reset timestamps after regeneration so the new session starts fresh
        $_SESSION['_session_created'] = time();
        $_SESSION['_last_activity']   = time();
    }
}