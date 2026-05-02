<?php
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $isSecure,
        'cookie_samesite' => 'Strict',
        'cookie_lifetime' => 60 * 60 * 24 * 30,
        'use_strict_mode' => true,
    ]);
}

if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Access Denied: Admin only area.'];
    header("Location: ../dashboard.php");
    exit;
}
?>