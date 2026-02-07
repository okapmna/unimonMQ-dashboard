<?php
include "config/koneksi.php";

if (isset($_POST['register'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Incubator System</title>
    
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
            <h2 class="text-3xl font-bold text-black mb-2">Create Account</h2>
            <p class="text-gray-500 text-sm tracking-wide uppercase font-semibold">Join UnimonMQ</p>
        </div>

        <form method="post" class="space-y-5">
            <div>
                <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Username</label>
                <input type="text" name="username" placeholder="Choose a username" required
                    class="w-full px-5 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-[#C69C6D] focus:ring-1 focus:ring-[#C69C6D] outline-none transition duration-200">
            </div>

            <div>
                <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Password</label>
                <input type="password" name="password" placeholder="Create a strong password" required
                    class="w-full px-5 py-3 rounded-xl bg-gray-50 border border-gray-200 focus:border-[#C69C6D] focus:ring-1 focus:ring-[#C69C6D] outline-none transition duration-200">
            </div>

            <button type="submit" name="register" 
                class="w-full bg-[#C69C6D] text-black font-bold py-3.5 rounded-xl shadow-sm hover:bg-[#b08b61] transition duration-300 mt-4">
                REGISTER NOW
            </button>
        </form>

        <div class="mt-8 pt-8 border-t border-gray-100 text-center">
            <p class="text-sm text-gray-600">
                Sudah memiliki akun? 
                <a href="index.php" class="text-[#C69C6D] font-bold hover:underline ml-1">Login di sini</a>
            </p>
        </div>

        <?php if (isset($error)) { ?>
            <div class="mt-6 p-4 bg-red-50 border border-red-100 rounded-xl">
                <p class="text-center text-sm font-bold text-red-600"><?= $error ?></p>
            </div>
        <?php } ?>
    </div>

</body>
</html>