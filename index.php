<?php
ob_start();
session_start();
include "config/koneksi.php";

$error = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // PREPARED STATEMENT
    $stmt = $koneksi->prepare("SELECT user_id, user_name, password FROM user WHERE user_name = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $data = $result->fetch_assoc();

        if (password_verify($password, $data['password'])) {
            $_SESSION['user_id'] = $data['user_id'];
            $_SESSION['username'] = $data['user_name'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Password salah";
        }
    } else {
        $error = "Username tidak ditemukan";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Unimon</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Manrope', 'sans-serif'] },
                    colors: {
                        background: '#FFF8EC',
                        'batch-brown': '#C69C6D',
                    }
                }
            }
        }
    </script>

    <style>
        body { background-color: #FFF8EC; }
    </style>
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md bg-white rounded-3xl p-10 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-white">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold text-black mb-2">Welcome Back</h2>
            <p class="text-gray-500 text-sm tracking-wide uppercase font-semibold">UnimonMQ</p>
        </div>

        <form method="post" class="space-y-5">
            <div>
                <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Username</label>
                <input type="text" name="username" placeholder="Enter your username" required
                    class="w-full px-5 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-[#C69C6D] focus:ring-1 focus:ring-[#C69C6D] outline-none transition duration-200">
            </div>

            <div>
                <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Password</label>
                <input type="password" name="password" placeholder="••••••••" required
                    class="w-full px-5 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-[#C69C6D] focus:ring-1 focus:ring-[#C69C6D] outline-none transition duration-200">
            </div>

            <button type="submit" name="login" 
                class="w-full bg-[#C69C6D] text-black font-bold py-3.5 rounded-xl shadow-sm hover:bg-[#b08b61] transition duration-300 mt-4">
                LOGIN
            </button>
        </form>

        <div class="mt-8 pt-8 border-t border-gray-100">
            <p class="text-center text-sm text-gray-600 mb-4">Belum memiliki akun?</p>
            <form action="register.php" method="get">
                <button type="submit" class="w-full border-2 border-black text-black font-bold py-3 rounded-xl hover:bg-black hover:text-white transition duration-300">
                    DAFTAR SEKARANG
                </button>
            </form>
        </div>

        <?php if ($error != "") { ?>
            <div class="mt-6 p-4 bg-red-50 border border-red-100 rounded-xl">
                <p class="text-center text-sm font-bold text-red-600"><?= $error ?></p>
            </div>
        <?php } ?>
    </div>

</body>
</html>