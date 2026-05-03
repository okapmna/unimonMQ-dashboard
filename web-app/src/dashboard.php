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

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION['role'] ?? 'user') === 'admin') {
    header("Location: admin/index.php");
    exit;
}

include "config/koneksi.php";
$username = $_SESSION['username'];

// Fetch devices (Owned + Shared)
$user_id = $_SESSION['user_id'];
$sql_fetch = "SELECT d.*, 'owner' as access_type FROM device d
              WHERE d.user_id = '$user_id'
              UNION
              SELECT d.*, uda.access_type FROM device d
              JOIN user_device_access uda ON d.device_id = uda.device_id
              WHERE uda.user_id = '$user_id'
              ORDER BY device_id DESC";
$devices = mysqli_query($koneksi, $sql_fetch);
?>

<?php
$page_title = 'UNIMQ - Dashboard';
$body_class = 'bg-cream-bg text-dark-text min-h-screen font-sans selection:bg-accent-green selection:text-white pb-20';
$base_url = '';
include "components/header.php";
?>

    <nav class="flex justify-between items-center px-6 py-6 max-w-7xl mx-auto">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-accent-green">UNIMQ</h1>
            <p class="text-sm text-muted-text font-medium">Welcome, <?= htmlspecialchars($username) ?></p>
        </div>
        <div class="flex items-center gap-4">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin/index.php" class="bg-accent-green/10 hover:bg-accent-green text-accent-green hover:text-white px-4 py-2 rounded-xl font-bold transition flex items-center gap-2 text-sm shadow-sm border border-accent-green/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Admin Panel
                </a>
            <?php endif; ?>
            <a href="profile.php" class="bg-gray-100/50 hover:bg-card-white text-muted-text hover:text-dark-text px-4 py-2 rounded-xl font-bold transition flex items-center gap-2 text-sm shadow-sm border border-transparent hover:border-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent-brown" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Profile
            </a>
            <a href="logout.php" class="text-red-500 font-semibold hover:text-red-700 hover:underline text-sm">Logout</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 mt-6 md:mt-8">
        <div class="flex items-end justify-between mb-6 md:mb-8">
            <div>
                <span class="text-[10px] md:text-xs font-bold tracking-wider text-muted-text uppercase">Overview</span>
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mt-1">My Devices</h2>
            </div>
            <button type="button" onclick="openNewDeviceModal()" class="bg-accent-green hover:bg-[#2e5910] text-white px-3 py-2 sm:px-5 sm:py-2.5 rounded-xl text-xs sm:text-base font-semibold shadow-lg shadow-green-900/10 transition transform hover:-translate-y-0.5 flex items-center gap-1.5 sm:gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                New Device
            </button>
        </div>

        <?php if ($devices && mysqli_num_rows($devices) > 0): ?>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-6 md:gap-8">
                <?php while ($device = mysqli_fetch_assoc($devices)): ?>
                    <?php
                    $keys = array_keys($device);
                    $primary_key = $keys[0];
                    $id_val = $device[$primary_key];
                    $displayName = !empty($device['device_name']) ? $device['device_name'] : $device['device_type'];

                    $link = '#';
                    $icon_incubator = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent-brown" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9a4 4 0 0 0-2 7.5M12 3v2M6.6 18.4l-1.4 1.4M18.8 4.2l-1.4 1.4M2 12h2M20 12h2M6.6 5.6l-1.4-1.4M18.8 19.8l-1.4-1.4"/></svg>';
                    $icon_lamp = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 16.5 8 4.5 4.5 0 0 0 12 3.5 4.5 4.5 0 0 0 7.5 8c0 1.5.81 2.82 2 3.5.76.76 1.23 1.52 1.41 2.5"/></svg>';
                    $icon_default = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2" ry="2"/><path d="M12 2v20"/><path d="M2 12h20"/></svg>';

                    $current_icon = $icon_default;
                    $badge_color = 'bg-gray-100 text-gray-600';

                    if (strpos($device['device_type'], 'inkubator') !== false) {
                        $link = 'iot-dashboard/incubator32/incubator_dashboard.php?device_id=' . $id_val;
                        $current_icon = $icon_incubator;
                        $badge_color = 'bg-[#FFF8EC] text-accent-brown border border-accent-brown/20';
                    } elseif (strpos($device['device_type'], 'lamp') !== false) {
                        $link = 'iot-dashboard/smartlamp32/smartlamp_dashboard.php?device_id=' . $id_val;
                        $current_icon = $icon_lamp;
                        $badge_color = 'bg-blue-50 text-accent-blue border border-accent-blue/20';
                    }

                    ?>

                    <div class="group relative bg-card-white rounded-3xl p-4 sm:p-6 md:p-8 shadow-xl shadow-gray-200/50 hover:shadow-2xl hover:shadow-gray-200/80 transition-all duration-300 border border-transparent hover:border-accent-green/20 transform hover:-translate-y-1">

                        <div class="absolute top-3 right-3 sm:top-6 sm:right-6 z-20">
                            <?php if ($device['access_type'] !== 'owner'): ?>
                            <form method="POST" action="actions/remove_shared.php" onsubmit="return confirm('Remove device: <?= htmlspecialchars($displayName) ?>?');">
                                <input type="hidden" name="device_id" value="<?= $id_val ?>">
                                <button type="submit" class="w-8 h-8 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-full transition-colors" title="Remove Device">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <a href="<?= $link ?>" class="block h-full flex flex-col justify-between">
                            <div>
                                <div class="flex flex-row items-center gap-2 mb-2 sm:mb-6">
                                    <div class="p-1 sm:p-3 bg-gray-50 rounded-lg sm:rounded-2xl shrink-0">
                                        <?= str_replace('h-8 w-8', 'h-5 w-5 sm:h-8 sm:w-8', $current_icon) ?>
                                    </div>
                                    <span class="<?= $badge_color ?> px-1.5 sm:px-3 py-0.5 sm:py-1 rounded-full text-[8px] sm:text-xs font-bold tracking-wider uppercase truncate max-w-[80px] sm:max-w-full">
                                        <?= str_replace('esp32-', '', htmlspecialchars($device['device_type'])) ?>
                                    </span>
                                </div>
                                <h3 class="text-base sm:text-2xl md:text-3xl font-bold text-dark-text mb-1 pr-4 sm:pr-8 group-hover:text-accent-green transition-colors line-clamp-2">
                                    <?= htmlspecialchars($displayName) ?>
                                </h3>
                                <div class="mt-3 sm:mt-6 pt-3 sm:pt-6 border-t border-gray-100"></div>
                            </div>
                            <div class="mt-2 sm:mt-4 text-right">
                                <span class="text-[8px] sm:text-[10px] text-gray-300 font-mono line-clamp-1">ID: <?= $id_val ?></span>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-20 bg-white/50 rounded-3xl border-2 border-dashed border-gray-200">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4 text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <p class="text-lg text-gray-500 font-medium">Belum ada device untuk user <b><?= htmlspecialchars($username) ?></b>.</p>
            </div>
        <?php endif; ?>
    </main>

    <div id="newDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" onclick="closeNewDeviceModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-gray-100">
                <div class="bg-white px-8 py-8">
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold leading-6 text-gray-900">New Device</h3>
                        <p class="mt-2 text-sm text-gray-500">Enter the serial number provided by your administrator.</p>
                    </div>
                    <form action="actions/redeem_serial_number.php" method="post" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Serial Number</label>
                            <input type="text" name="serial_number" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50 font-mono" placeholder="e.g. AB12CD34" required>
                        </div>
                        <div class="pt-4 flex flex-col gap-3">
                            <button type="submit" name="add_serial_device" class="w-full justify-center rounded-xl bg-accent-green px-3 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-[#2e5910] transition-all">Add Device</button>
                            <button type="button" onclick="closeNewDeviceModal()" class="w-full justify-center rounded-xl bg-white px-3 py-3.5 text-sm font-bold text-gray-500 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openNewDeviceModal() {
            document.getElementById('newDeviceModal').classList.remove('hidden');
        }

        function closeNewDeviceModal() {
            document.getElementById('newDeviceModal').classList.add('hidden');
        }

        document.onkeydown = function(evt) {
            evt = evt || window.event;
            if (evt.keyCode == 27) {
                closeNewDeviceModal();
            }
        };
    </script>

<?php include "components/footer.php"; ?>
