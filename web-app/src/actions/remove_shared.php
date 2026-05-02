<?php
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $isSecure,
    'cookie_samesite' => 'Strict',
    'cookie_lifetime' => 60 * 60 * 24 * 30,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['username']) || !isset($_POST['device_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

include "../config/koneksi.php";

$user_id = $_SESSION['user_id'];
$device_id = mysqli_real_escape_string($koneksi, $_POST['device_id']);

$query = "DELETE FROM user_device_access WHERE user_id = '$user_id' AND device_id = '$device_id'";
if (mysqli_query($koneksi, $query)) {
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Shared device removed from your dashboard.'];
} else {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to remove shared device.'];
}

header("Location: ../dashboard.php");
exit;
?>