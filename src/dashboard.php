<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

include "config/koneksi.php";
$username = $_SESSION['username'];

// 1. LOGIKA TAMBAH DEVICE
if (isset($_POST['add_device'])) {
    $sql_user = "SELECT user_id FROM user WHERE user_name = '$username'";
    $result_user = mysqli_query($koneksi, $sql_user);
    $data_user = mysqli_fetch_assoc($result_user);

    if ($data_user) {
        $id_pemilik = $data_user['user_id'];
        $dev_name = mysqli_real_escape_string($koneksi, $_POST['device_name']);
        $dev_type = mysqli_real_escape_string($koneksi, $_POST['device_type']);
        $broker   = mysqli_real_escape_string($koneksi, $_POST['broker_url']);
        $user_mq  = mysqli_real_escape_string($koneksi, $_POST['mq_user']);
        $pass_mq  = mysqli_real_escape_string($koneksi, $_POST['mq_pass']);
        $broker_port = mysqli_real_escape_string($koneksi, $_POST['broker_port']);

        $query = "INSERT INTO device (user_id, device_name, broker_url, mq_user, mq_pass, device_type, broker_port) 
                  VALUES ('$id_pemilik', '$dev_name', '$broker', '$user_mq', '$pass_mq', '$dev_type', '$broker_port')";

        if (mysqli_query($koneksi, $query)) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Berhasil tambah device!'];
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal tambah device: ' . mysqli_error($koneksi)];
        }
    }
}

// 2. LOGIKA EDIT DEVICE (BARU)
if (isset($_POST['edit_device'])) {
    $sql_user = "SELECT user_id FROM user WHERE user_name = '$username'";
    $result_user = mysqli_query($koneksi, $sql_user);
    $data_user = mysqli_fetch_assoc($result_user);

    if ($data_user) {
        $id_pemilik = $data_user['user_id'];
        
        $id_device = mysqli_real_escape_string($koneksi, $_POST['edit_device_id']);
        $dev_name  = mysqli_real_escape_string($koneksi, $_POST['edit_device_name']);
        $dev_type  = mysqli_real_escape_string($koneksi, $_POST['edit_device_type']);
        $broker    = mysqli_real_escape_string($koneksi, $_POST['edit_broker_url']);
        $broker_port = mysqli_real_escape_string($koneksi, $_POST['edit_broker_port']);
        $user_mq   = mysqli_real_escape_string($koneksi, $_POST['edit_mq_user']);
        $pass_mq   = mysqli_real_escape_string($koneksi, $_POST['edit_mq_pass']);

        $query_update = "UPDATE device SET 
                         device_name = '$dev_name',
                         device_type = '$dev_type',
                         broker_url  = '$broker',
                         broker_port = '$broker_port',
                         mq_user     = '$user_mq',
                         mq_pass     = '$pass_mq'
                         WHERE device_id = '$id_device' AND user_id = '$id_pemilik'";

        if (mysqli_query($koneksi, $query_update)) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Berhasil update device!'];
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal update: ' . mysqli_error($koneksi)];
        }
    }
}

// 3. LOGIKA HAPUS DEVICE
if (isset($_POST['btn_hapus_pintar'])) {
    $id_target = mysqli_real_escape_string($koneksi, $_POST['id_hapus_target']);
    $nama_kolom = mysqli_real_escape_string($koneksi, $_POST['nama_kolom_target']);

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $nama_kolom)) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error: Nama kolom tidak valid!'];
    } elseif (empty($id_target)) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error: ID kosong!'];
    } else {
        $query_hapus = "DELETE FROM device WHERE $nama_kolom = '$id_target' LIMIT 1";
        if (mysqli_query($koneksi, $query_hapus)) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Berhasil! Device terhapus.'];
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal hapus database.'];
        }
    }
}

// Fetch devices
$sql_fetch = "SELECT d.* FROM device d 
              JOIN user u ON d.user_id = u.user_id 
              WHERE u.user_name = '$username' 
              ORDER BY d.device_id DESC";
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
            <button onclick="openModal()" class="bg-accent-green hover:bg-[#2e5910] text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg shadow-green-900/10 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                New Device
            </button>
        </div>

        <?php if ($devices && mysqli_num_rows($devices) > 0): ?>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 md:gap-8">
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
                            <button type="button" onclick="toggleDeviceMenu(event, 'menu-<?= $id_val ?>')" class="w-8 h-8 flex items-center justify-center text-gray-300 hover:text-dark-text hover:bg-gray-100 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-accent-green/50" aria-label="Device Options">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                </svg>
                            </button>
                            <div id="menu-<?= $id_val ?>" class="device-dropdown opacity-0 pointer-events-none absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl shadow-gray-200/50 border border-gray-100 py-1.5 origin-top-right transition-all duration-200 transform scale-95 z-30">
                                <button type="button" onclick="event.preventDefault(); openEditModal(<?= htmlspecialchars(json_encode($device)) ?>); closeAllDeviceMenus();" class="w-full text-left px-4 py-2.5 text-sm font-semibold text-gray-600 hover:text-dark-text hover:bg-gray-50 flex items-center gap-3 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                    Edit Device
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>
                                <form method="POST" onsubmit="return confirm('Hapus device: <?= htmlspecialchars($displayName) ?>?');">
                                    <input type="hidden" name="nama_kolom_target" value="<?= $primary_key ?>">
                                    <input type="hidden" name="id_hapus_target" value="<?= $id_val ?>">
                                    <button type="submit" name="btn_hapus_pintar" class="w-full text-left px-4 py-2.5 text-sm font-semibold text-red-500 hover:text-red-700 hover:bg-red-50 flex items-center gap-3 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        Hapus Device
                                    </button>
                                </form>
                            </div>
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
                                <div class="mt-2 sm:mt-6 pt-2 sm:pt-6 border-t border-gray-100">
                                    <p class="text-[9px] sm:text-xs font-semibold text-muted-text uppercase mb-0.5 sm:mb-1">Broker Config</p>
                                    <p class="text-[10px] sm:text-sm text-dark-text font-medium truncate">
                                        <?= !empty($device['broker_url']) ? htmlspecialchars($device['broker_url']) : '-' ?>
                                    </p>
                                </div>
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
                <button onclick="openModal()" class="mt-4 text-accent-green font-bold hover:underline">Tambah Device Sekarang</button>
            </div>
        <?php endif; ?>
    </main>

    <div id="deviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-gray-100">
                <div class="bg-white px-8 py-8">
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold leading-6 text-gray-900" id="modal-title">Add New Device</h3>
                        <p class="mt-2 text-sm text-gray-500">Isi form di bawah ini untuk menghubungkan perangkat baru.</p>
                    </div>
                    <form method="post" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nama Device</label>
                            <input type="text" name="device_name" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50" placeholder="Cth: Inkubator Kandang 1" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Tipe Device</label>
                            <select name="device_type" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50" required>
                                <option value="" disabled selected>-- Pilih Tipe --</option>
                                <option value="esp32-inkubator">esp32-inkubator</option>
                                <option value="esp32-smartlamp">esp32-smartlamp</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Broker URL</label>
                            <input type="text" name="broker_url" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50" placeholder="broker.hivemq.com" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Websocket Port</label>
                            <input type="text" name="broker_port" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50" placeholder="8080" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">MQ User (Opsional)</label>
                                <input type="text" name="mq_user" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">MQ Password</label>
                                <input type="password" name="mq_pass" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-accent-green focus:ring-accent-green sm:text-sm py-3 px-4 bg-gray-50">
                            </div>
                        </div>
                        <div class="pt-4 flex flex-col gap-3">
                            <button type="submit" name="add_device" class="w-full justify-center rounded-xl bg-accent-green px-3 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-[#2e5910] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all">Simpan Device</button>
                            <button type="button" onclick="closeModal()" class="w-full justify-center rounded-xl bg-white px-3 py-3.5 text-sm font-bold text-gray-500 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="editDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" onclick="closeEditModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-gray-100">
                <div class="bg-white px-8 py-8">
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold leading-6 text-gray-900">Edit Device</h3>
                        <p class="mt-2 text-sm text-gray-500">Perbarui konfigurasi perangkat Anda.</p>
                    </div>
                    <form method="post" class="space-y-5">
                        <input type="hidden" name="edit_device_id" id="edit_device_id">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nama Device</label>
                            <input type="text" name="edit_device_name" id="edit_device_name" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 px-4 bg-gray-50" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Tipe Device</label>
                            <select name="edit_device_type" id="edit_device_type" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 px-4 bg-gray-50" required>
                                <option value="esp32-inkubator">esp32-inkubator</option>
                                <option value="esp32-smartlamp">esp32-smartlamp</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Broker URL</label>
                            <input type="text" name="edit_broker_url" id="edit_broker_url" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 px-4 bg-gray-50" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Websocket Port</label>
                            <input type="text" name="edit_broker_port" id="edit_broker_port" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 px-4 bg-gray-50" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">MQ User</label>
                                <input type="text" name="edit_mq_user" id="edit_mq_user" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 px-4 bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-2">MQ Pass</label>
                                <input type="password" name="edit_mq_pass" id="edit_mq_pass" class="block w-full rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 px-4 bg-gray-50">
                            </div>
                        </div>
                        <div class="pt-4 flex flex-col gap-3">
                            <button type="submit" name="edit_device" class="w-full justify-center rounded-xl bg-blue-600 px-3 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 transition-all">Update Device</button>
                            <button type="button" onclick="closeEditModal()" class="w-full justify-center rounded-xl bg-white px-3 py-3.5 text-sm font-bold text-gray-500 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal Add
        function openModal() {
            document.getElementById('deviceModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('deviceModal').classList.add('hidden');
        }

        // Modal Edit
        function openEditModal(deviceData) {
            document.getElementById('edit_device_id').value = deviceData.device_id;
            document.getElementById('edit_device_name').value = deviceData.device_name;
            document.getElementById('edit_device_type').value = deviceData.device_type;
            document.getElementById('edit_broker_url').value = deviceData.broker_url;
            document.getElementById('edit_broker_port').value = deviceData.broker_port;
            document.getElementById('edit_mq_user').value = deviceData.mq_user;
            document.getElementById('edit_mq_pass').value = deviceData.mq_pass;

            document.getElementById('editDeviceModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editDeviceModal').classList.add('hidden');
        }

        document.onkeydown = function(evt) {
            evt = evt || window.event;
            if (evt.keyCode == 27) {
                closeModal();
                closeEditModal();
                closeAllDeviceMenus();
            }
        };

        // Menu Dropdown Logic
        function toggleDeviceMenu(event, menuId) {
            event.preventDefault();
            event.stopPropagation();
            
            const menu = document.getElementById(menuId);
            const isClosed = menu.classList.contains('opacity-0');
            
            closeAllDeviceMenus();
            
            if (isClosed) {
                menu.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
                menu.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
            }
        }

        function closeAllDeviceMenus() {
            const menus = document.querySelectorAll('.device-dropdown');
            menus.forEach(menu => {
                menu.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
                menu.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
            });
        }

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.device-dropdown') && !event.target.closest('button[onclick^="toggleDeviceMenu"]')) {
                closeAllDeviceMenus();
            }
        });
    </script>
<?php include "components/footer.php"; ?>