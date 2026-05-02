<?php
include "config/koneksi.php";

if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // username check
    $stmt_cek = $koneksi->prepare("SELECT user_id FROM user WHERE user_name = ?");
    $stmt_cek->bind_param("s", $username);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();

    if ($result_cek->num_rows > 0) {
        $error = "username is already registered";
    } else {
        // save new user to db
        $stmt_ins = $koneksi->prepare("INSERT INTO user (user_name, password) VALUES (?, ?)");
        $stmt_ins->bind_param("ss", $username, $password);

        if ($stmt_ins->execute()) {
            header("Location: index.php?pesan=Registration Success");
            exit;
        } else {
            $error = "Registration Failed";
        }
        $stmt_ins->close();
    }
    $stmt_cek->close();
}
?>


<?php
$page_title = 'Registrasi - Incubator System';
$body_class = 'font-sans min-h-screen flex items-center justify-center p-6';
$base_url = '';
include "components/header.php";
?>

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
                <div class="relative">
                    <input type="password" id="register_password" name="password" placeholder="Create a strong password" required
                        class="w-full px-5 py-3 pr-12 rounded-xl bg-gray-50 border border-gray-200 focus:border-[#C69C6D] focus:ring-1 focus:ring-[#C69C6D] outline-none transition duration-200">
                    <button type="button" onclick="togglePassword('register_password', 'eye_icon_register')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                        <svg id="eye_icon_register" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
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

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === "password") {
                input.type = "text";
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            } else {
                input.type = "password";
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }
    </script>
<?php include "components/footer.php"; ?>