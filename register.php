<?php
include "config/koneksi.php";

if (isset($_POST['register'])) {
    // Escape username to prevent SQL injection
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // cek username sudah ada
    $cek = mysqli_query($koneksi, 
        "SELECT * FROM user WHERE user_name='$username'"
    );

    if (mysqli_num_rows($cek) > 0) {
        $error = "Username sudah terdaftar";
    } else {
        $insert = mysqli_query($koneksi,
            "INSERT INTO user (user_name, password)
             VALUES ('$username', '$password')"
        );

        if ($insert) {
            // setelah register → balik ke login (index.php is the login page)
            header("Location: index.php");
            exit;
        } else {
            $error = "Registrasi gagal: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi</title>
    <style>
        body {
            background: linear-gradient(120deg, #89f7fe, #66a6ff);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
        }
        .box {
            background: white;
            padding: 30px;
            width: 350px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .box h2 { text-align: center; margin-bottom: 25px; }
        .box input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        .box button {
            width: 100%;
            padding: 10px;
            background: #66a6ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }
        .msg {
            text-align: center;
            margin-top: 10px;
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Form Registrasi</h2>

    <form method="post">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="register">Daftar</button>
    </form>

    <?php if (isset($error)) { ?>
        <div class="msg"><?= $error ?></div>
    <?php } ?>
</div>

</body>
</html>
