<?php
/**
 * Global Session Configuration
 * Robust HTTPS detection and centralized session settings
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Robust HTTPS detection
$isSecure = false;
if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
    $isSecure = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $isSecure = true;
} elseif (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
    $isSecure = true;
}

// Start session with secure parameters
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
        'cookie_lifetime' => 60 * 60 * 24 * 30, // 30 days
        'use_strict_mode' => true,
    ]);
}
