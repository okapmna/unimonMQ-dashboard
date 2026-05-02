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

if (!isset($_SESSION['username']) || !isset($_POST['redeem'])) {
    header("Location: ../dashboard.php");
    exit;
}

include "../config/koneksi.php";

$user_id = $_SESSION['user_id'];
$token_code = mysqli_real_escape_string($koneksi, $_POST['token_code']);

// 1. Validate Token
$sql_token = "SELECT * FROM device_access_tokens WHERE token_code = '$token_code' AND is_active = 1 LIMIT 1";
$result_token = mysqli_query($koneksi, $sql_token);
$token = mysqli_fetch_assoc($result_token);

if (!$token) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid or inactive token.'];
    header("Location: ../dashboard.php");
    exit;
}

// 2. Check Expiry
if ($token['expires_at'] && strtotime($token['expires_at']) < time()) {
    mysqli_query($koneksi, "UPDATE device_access_tokens SET is_active = 0 WHERE token_id = '{$token['token_id']}'");
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Token has expired.'];
    header("Location: ../dashboard.php");
    exit;
}

// 3. Check Max Uses
if ($token['max_uses'] !== null && $token['current_uses'] >= $token['max_uses']) {
    mysqli_query($koneksi, "UPDATE device_access_tokens SET is_active = 0 WHERE token_id = '{$token['token_id']}'");
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Token has reached maximum usage limit.'];
    header("Location: ../dashboard.php");
    exit;
}

$device_id = $token['device_id'];

// 4. Check if user is owner
$sql_owner = "SELECT user_id FROM device WHERE device_id = '$device_id' LIMIT 1";
$result_owner = mysqli_query($koneksi, $sql_owner);
$owner = mysqli_fetch_assoc($result_owner);

if ($owner['user_id'] == $user_id) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'You already own this device!'];
    header("Location: ../dashboard.php");
    exit;
}

// 5. Check if user already has shared access
$sql_access = "SELECT id FROM user_device_access WHERE user_id = '$user_id' AND device_id = '$device_id' LIMIT 1";
$result_access = mysqli_query($koneksi, $sql_access);

if (mysqli_num_rows($result_access) > 0) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'You already have access to this device.'];
    header("Location: ../dashboard.php");
    exit;
}

// 6. Grant Access (Transactional-like)
mysqli_begin_transaction($koneksi);
try {
    // Re-check uses inside transaction if needed, but for simplicity:
    mysqli_query($koneksi, "INSERT INTO user_device_access (user_id, device_id, access_type, redeemed_via_token_id) 
                            VALUES ('$user_id', '$device_id', 'viewer', '{$token['token_id']}')");
    
    mysqli_query($koneksi, "UPDATE device_access_tokens SET current_uses = current_uses + 1 WHERE token_id = '{$token['token_id']}'");
    
    // Auto-deactivate if max reached
    if ($token['max_uses'] !== null && ($token['current_uses'] + 1) >= $token['max_uses']) {
        mysqli_query($koneksi, "UPDATE device_access_tokens SET is_active = 0 WHERE token_id = '{$token['token_id']}'");
    }
    
    mysqli_commit($koneksi);
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Device successfully added to your dashboard!'];
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Redemption failed. Please try again.'];
}

header("Location: ../dashboard.php");
exit;
?>