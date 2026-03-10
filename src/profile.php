<?php
require_once "config/session.php";

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

include "config/koneksi.php";
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Logika ubah password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Ambil password saat ini dari database
    $stmt = $koneksi->prepare("SELECT password FROM user WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $data = $result->fetch_assoc();
        
        // Verifikasi password lama
        if (password_verify($current_password, $data['password'])) {
            if ($new_password === $confirm_password) {
                // Hash password baru
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password ke database
                $update_stmt = $koneksi->prepare("UPDATE user SET password = ? WHERE user_id = ?");
                $update_stmt->bind_param("ss", $hashed_new_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Password berhasil diubah!'];
                } else {
                    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Terjadi kesalahan sistem saat mengubah password.'];
                }
                $update_stmt->close();
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Konfirmasi password baru tidak cocok.'];
            }
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Password saat ini salah.'];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'User tidak ditemukan.'];
    }
    $stmt->close();
    
    header("Location: profile.php");
    exit;
}

$page_title = 'User Profile - UNIMQ';
$body_class = 'bg-cream-bg text-dark-text min-h-screen font-sans selection:bg-accent-green selection:text-white pb-20';
$base_url = '';

include "components/header.php";
?>

    <nav class="flex justify-between items-center px-6 py-6 max-w-7xl mx-auto">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-accent-green">UNIMQ</h1>
            <p class="text-sm text-muted-text font-medium">Profile Settings</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="text-gray-600 font-semibold hover:text-black transition-colors text-sm px-4 py-2 bg-gray-100 rounded-lg">Kembali</a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-6 mt-8">
        <div class="mb-8">
            <h2 class="text-4xl font-bold mt-1">My Profile</h2>
            <p class="text-muted-text mt-2 font-medium">Kelola informasi akun dan kata sandi Anda.</p>
        </div>

        <div class="bg-card-white rounded-3xl p-8 shadow-xl shadow-gray-200/50 border border-transparent">
            <div class="flex items-center gap-6 mb-10 pb-8 border-b border-gray-100">
                <div class="w-20 h-20 bg-accent-green/10 rounded-full flex items-center justify-center text-accent-green">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-dark-text"><?= htmlspecialchars($username) ?></h3>
                    <p class="text-sm text-muted-text font-medium uppercase tracking-wider mt-1">User ID: <?= htmlspecialchars($user_id) ?></p>
                </div>
            </div>

            <form method="post" class="space-y-6">
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-dark-text flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-accent-brown" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Ubah Password
                    </h4>
                </div>
                
                <div>
                    <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Password Saat Ini</label>
                    <div class="relative">
                        <input type="password" id="current_password" name="current_password" placeholder="••••••••" required
                            class="w-full px-5 py-3 pr-12 rounded-xl bg-gray-50 border border-gray-200 focus:border-accent-green focus:ring-1 focus:ring-accent-green outline-none transition duration-200">
                        <button type="button" onclick="togglePassword('current_password', 'eye_current')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                            <svg id="eye_current" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Password Baru</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password" placeholder="Minimal 8 karakter" required minlength="8"
                            class="w-full px-5 py-3 pr-12 rounded-xl bg-gray-50 border border-gray-200 focus:border-accent-green focus:ring-1 focus:ring-accent-green outline-none transition duration-200">
                        <button type="button" onclick="togglePassword('new_password', 'eye_new')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                            <svg id="eye_new" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-[0.65rem] font-bold uppercase tracking-wider text-gray-600 block mb-2 px-1">Konfirmasi Password Baru</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Ketik ulang password baru" required minlength="8"
                            class="w-full px-5 py-3 pr-12 rounded-xl bg-gray-50 border border-gray-200 focus:border-accent-green focus:ring-1 focus:ring-accent-green outline-none transition duration-200">
                        <button type="button" onclick="togglePassword('confirm_password', 'eye_confirm')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors">
                            <svg id="eye_confirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <button type="submit" name="change_password" 
                        class="w-full bg-accent-green text-white font-bold py-4 rounded-xl shadow-sm shadow-green-900/10 hover:bg-[#2e5910] hover:-translate-y-0.5 transition-all duration-300">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </main>

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
