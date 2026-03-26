<?php
/**
 * ROLE-BASED ACCESS CONTROL
 * Enforces role restrictions and logs every denial attempt to the audit log.
 */

/**
 * Halt execution if the current user's role is not in $allowed_roles.
 * Logs the denial attempt (with IP and page) before stopping.
 *
 * @param int[] $allowed_roles  Array of role_id values permitted on this page
 */
function requireRole(array $allowed_roles): void
{
    $current_role = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;

    if ($current_role === null || !in_array($current_role, $allowed_roles, true)) {

        // ── Log the denial ────────────────────────────────────────────────────
        // Only log if we have a DB connection and a user to attribute the event to.
        if (isset($GLOBALS['conn']) && isset($_SESSION['user_id'])) {
            $page    = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'unknown', ENT_QUOTES);
            $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $role    = $current_role ?? 'none';
            $action  = "PERMISSION DENIED: role=$role attempted to access $page from IP $ip";
            logAction($GLOBALS['conn'], (int)$_SESSION['user_id'], $action);
        }

        http_response_code(403);
        // Generic message — do not leak which roles ARE allowed
        die("Access denied. You do not have permission to view this page.");
    }
}