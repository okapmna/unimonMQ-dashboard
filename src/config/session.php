<?php
/**
 * Global Session Configuration
 * Robust HTTPS detection and centralized session settings for Proxy/Docker environments
 */

// Start output buffering
if (ob_get_level() === 0) ob_start();

// Robust HTTPS detection
$isSecure = false;

// Standard HTTPS header
if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
    $isSecure = true;
} 
// Load balancer / Reverse proxy headers (X-Forwarded-Proto)
elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $protos = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
    if (strtolower(trim($protos[0])) === 'https') {
        $isSecure = true;
    }
}
// Port based detection
elseif (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
    $isSecure = true;
}
// X-Forwarded-Port
elseif (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && (int)$_SERVER['HTTP_X_FORWARDED_PORT'] === 443) {
    $isSecure = true;
}

// Start session with secure parameters
if (session_status() === PHP_SESSION_NONE) {
    // Determine cookie domain if needed (optional, letting it be default is usually safer for subdomains)
    
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
        'cookie_lifetime' => 60 * 60 * 24 * 30, // 30 days
        'cookie_path'     => '/',               // Explicitly set to root
        'use_strict_mode' => true,
    ]);
}
