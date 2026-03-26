<?php
/**
 * DATABASE CONNECTION
 * Errors are logged to a file — never exposed to the browser.
 */

// ── Credentials ───────────────────────────────────────────────────────────────
// In production, load these from environment variables or a config outside webroot.
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'grades_system';

// ── Suppress MySQLi native errors from reaching the browser ──────────────────
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    // Log the real error to a private file — never send it to the client
    $log_msg = sprintf(
        "[%s] DB connection failed (errno %d): %s\n",
        date('Y-m-d H:i:s'),
        $conn->connect_errno,
        $conn->connect_error
    );
    // Write to a log file outside the webroot if possible; fallback to /tmp
    $log_path = defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/db_errors.log';
    @file_put_contents($log_path, $log_msg, FILE_APPEND | LOCK_EX);

    // Generic error to the user
    http_response_code(503);
    die("Service temporarily unavailable. Please try again later.");
}

// ── Force UTF-8 for all queries ───────────────────────────────────────────────
$conn->set_charset('utf8mb4');