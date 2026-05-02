<?php
include "auth_check.php";
include "../config/koneksi.php";

header('Content-Type: application/json');

if (!isset($_GET['device_id'])) {
    echo json_encode(['error' => 'Missing device_id']);
    exit;
}

$device_id = mysqli_real_escape_string($koneksi, $_GET['device_id']);
$sql = "SELECT * FROM device_access_tokens WHERE device_id = '$device_id' ORDER BY created_at DESC";
$result = mysqli_query($koneksi, $sql);

$tokens = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tokens[] = $row;
}

echo json_encode($tokens);
?>