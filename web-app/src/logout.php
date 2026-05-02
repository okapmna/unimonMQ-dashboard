<?php
/**
 * Logout Handler
 * Destroys the session and redirects to login page
 */

session_start();

// Unset all session variables
$_SESSION = array();

// Delete login session token
include "config/koneksi.php";
if (isset($_COOKIE['remember_me'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
    $stmt = $koneksi->prepare("DELETE FROM user_tokens WHERE selector = ?");
    if ($stmt) {
        $stmt->bind_param("s", $selector);
        $stmt->execute();
    }
    
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax',
    ]);
}

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
?>
