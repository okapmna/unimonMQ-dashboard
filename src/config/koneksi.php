<?php
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    
    $host = $env['DB_HOST'] ?? 'db';
    $user = $env['DB_USER'] ?? 'user_app';
    $pass = $env['DB_PASS'] ?? 'password_app';
    $db   = $env['DB_NAME'] ?? 'unimq';
} else {
    die(".env file not found");
}

// Eksekusi koneksi
$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Failed connect to database : " . mysqli_connect_error());
}
?>