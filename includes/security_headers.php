<?php
/**
 * SECURITY HEADERS
 * Include this file as early as possible in index.php and auth pages.
 * Sends hardened HTTP response headers to defend against common web attacks.
 */

if (headers_sent()) {
    return; // Already sent — cannot set headers
}

// ── Content Security Policy ──────────────────────────────────────────────────
// Restricts which resources the browser may load.
// 'nonce' approach is ideal for inline scripts but requires per-request nonces;
// for this architecture (PHP-generated inline JS) we permit 'unsafe-inline'
// only for script-src and style-src while keeping everything else locked down.
$csp_directives = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com https://cdnjs.cloudflare.com",
    "font-src 'self' https://fonts.gstatic.com https://unpkg.com",
    "img-src 'self' data: blob:",
    "connect-src 'self'",
    "frame-ancestors 'none'",
    "form-action 'self'",
    "base-uri 'self'",
    "object-src 'none'",
];
header("Content-Security-Policy: " . implode('; ', $csp_directives));

// ── Clickjacking Protection ──────────────────────────────────────────────────
// Prevents this page from being embedded in an iframe on any other origin.
header("X-Frame-Options: DENY");

// ── MIME Sniffing Protection ─────────────────────────────────────────────────
// Stops browsers from trying to guess the content type (MIME confusion attacks).
header("X-Content-Type-Options: nosniff");

// ── HTTP Strict Transport Security ──────────────────────────────────────────
// Forces HTTPS for 1 year (31536000 s). Only send over HTTPS to avoid breaking
// HTTP-only local dev — detect via HTTPS server variable.
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// ── Referrer Policy ──────────────────────────────────────────────────────────
// Sends the full URL only to same-origin requests; only the origin to cross-origin.
header("Referrer-Policy: strict-origin-when-cross-origin");

// ── Permissions Policy ───────────────────────────────────────────────────────
// Disables browser features this app does not need.
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=()");

// ── Cross-Origin Policies ────────────────────────────────────────────────────
// Prevent cross-origin embedding of resources and isolation of the document.
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");

// ── Remove Server & X-Powered-By fingerprints ────────────────────────────────
header_remove("X-Powered-By");
header_remove("Server");