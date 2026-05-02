<?php
ob_start();
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $isSecure,
    'cookie_samesite' => 'Strict',
    'cookie_lifetime' => 60 * 60 * 24 * 30,
    'use_strict_mode' => true,
]);
include "config/koneksi.php";

// redirect to dashboard if already logged in
if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit;
}

// auto login checker
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    if (strpos($_COOKIE['remember_me'], ':') !== false) {
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);

    $stmt = $koneksi->prepare("SELECT ut.user_id, ut.hashed_validator, u.user_name, u.role FROM user_tokens ut JOIN user u ON ut.user_id = u.user_id WHERE ut.selector = ? AND ut.expiry > NOW() LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $token_data = $res->fetch_assoc();
            if (password_verify($validator, $token_data['hashed_validator'])) {
                $_SESSION['user_id'] = $token_data['user_id'];
                $_SESSION['username'] = $token_data['user_name'];
                $_SESSION['role'] = $token_data['role'] ?? 'user';
                header("Location: dashboard.php");
                exit;
            }
        }
        }
    }
}

$error = "";

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $koneksi->prepare("SELECT user_id, user_name, password, role FROM user WHERE user_name = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $data = $result->fetch_assoc();

        // Login Checker
        if (password_verify($password, $data['password'])) {
            $_SESSION['user_id'] = $data['user_id'];
            $_SESSION['username'] = $data['user_name'];
            $_SESSION['role'] = $data['role'] ?? 'user';

            // Remember Me
            if (isset($_POST['remember'])) {
                $selector = bin2hex(random_bytes(6));
                $validator = bin2hex(random_bytes(32));
                $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);
                $expiry = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

                $stmt_token = $koneksi->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, expiry) VALUES (?, ?, ?, ?)");
                if ($stmt_token) {
                    $user_id_int = (int)$data['user_id'];
                    $stmt_token->bind_param("isss", $user_id_int, $selector, $hashedValidator, $expiry);
                    if (!$stmt_token->execute()) {
                        error_log("Token insertion failed: " . $stmt_token->error);
                    }
                    $stmt_token->close();
                } else {
                    error_log("Token prepare failed: " . $koneksi->error);
                }

                setcookie('remember_me', $selector . ':' . $validator, [
                    'expires' => time() + 60 * 60 * 24 * 30,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => $isSecure,
                    'samesite' => 'Lax',
                ]);
            }
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Username and password do not match";
        }
    } else {
        $error = "Username and password do not match";
    }
    $stmt->close();
}
?>
<?php
$page_title = 'Login - Unimon';
$body_class = 'font-sans min-h-screen flex items-center justify-center p-6';
$base_url = '';
include "components/header.php";
?>

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
                <div class="relative">
                    <input type="password" id="login_password" name="password" placeholder="••••••••" required
                        class="w-full px-5 py-3 pr-12 rounded-xl bg-gray-50 border border-gray-200 focus:border-[#C69C6D] focus:ring-1 focus:ring-[#C69C6D] outline-none transition duration-200">
                    <button type="button" onclick="togglePassword('login_password', 'eye_icon_login')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                        <svg id="eye_icon_login" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between px-1">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="remember" checked class="w-4 h-4 rounded border-gray-300 text-[#C69C6D] focus:ring-[#C69C6D]">
                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Remember Me</span>
                </label>
            </div>

            <button type="submit" name="login" 
                class="w-full bg-[#C69C6D] text-black font-bold py-3.5 rounded-xl shadow-sm hover:bg-[#b08b61] transition duration-300 mt-4">
                LOGIN
            </button>
        </form>

        <div class="mt-8 pt-8 border-t border-gray-100">
            <p class="text-center text-sm text-gray-600 mb-4">Don't have an account?</p>
            <form action="register.php" method="get">
                <button type="submit" class="w-full border-2 border-black text-black font-bold py-3 rounded-xl hover:bg-black hover:text-white transition duration-300">
                    REGISTER
                </button>
            </form>
        </div>

        <?php if ($error != "") { ?>
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