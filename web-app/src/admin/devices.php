<?php
include "auth_check.php";
include "../config/koneksi.php";

$username = $_SESSION['username'];
$admin_id = $_SESSION['user_id'] ?? null;
if (!$admin_id) {
    $safe_username = mysqli_real_escape_string($koneksi, $username);
    $admin_result = mysqli_query($koneksi, "SELECT user_id FROM user WHERE user_name = '$safe_username' LIMIT 1");
    $admin = $admin_result ? mysqli_fetch_assoc($admin_result) : null;
    $admin_id = $admin['user_id'] ?? null;
    if ($admin_id) {
        $_SESSION['user_id'] = $admin_id;
    }
}

function generateDeviceAccessToken($koneksi, $device_id, $admin_id, $max_uses = 1, $expires_at = "NULL") {
    do {
        $token_code = strtoupper(bin2hex(random_bytes(4)));
        $escaped_token = mysqli_real_escape_string($koneksi, $token_code);
        $existing = mysqli_query($koneksi, "SELECT token_id FROM device_access_tokens WHERE token_code = '$escaped_token' LIMIT 1");
    } while ($existing && mysqli_num_rows($existing) > 0);

    $safe_device_id = mysqli_real_escape_string($koneksi, $device_id);
    $safe_admin_id = mysqli_real_escape_string($koneksi, $admin_id);
    $query = "INSERT INTO device_access_tokens (device_id, token_code, created_by, max_uses, expires_at)
              VALUES ('$safe_device_id', '$escaped_token', '$safe_admin_id', $max_uses, $expires_at)";

    return mysqli_query($koneksi, $query) ? $token_code : false;
}

function insertAdminAuditLog($koneksi, $admin_id, $action, $target_type, $target_id, $details) {
    $safe_admin_id = mysqli_real_escape_string($koneksi, $admin_id);
    $safe_action = mysqli_real_escape_string($koneksi, $action);
    $safe_target_type = mysqli_real_escape_string($koneksi, $target_type);
    $safe_target_id = mysqli_real_escape_string($koneksi, $target_id);
    $safe_details = mysqli_real_escape_string($koneksi, json_encode($details));

    mysqli_query($koneksi, "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details) VALUES ('$safe_admin_id', '$safe_action', '$safe_target_type', '$safe_target_id', '$safe_details')");
}

function htmlJson($value) {
    return htmlspecialchars(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
}

// Handle Add Device
if (isset($_POST['add_device'])) {
    $id_pemilik = mysqli_real_escape_string($koneksi, $_POST['owner_id']);
    $dev_name = mysqli_real_escape_string($koneksi, $_POST['device_name']);
    $dev_type = mysqli_real_escape_string($koneksi, $_POST['device_type']);
    $broker   = mysqli_real_escape_string($koneksi, $_POST['broker_url']);
    $user_mq  = mysqli_real_escape_string($koneksi, $_POST['mq_user']);
    $pass_mq  = mysqli_real_escape_string($koneksi, $_POST['mq_pass']);
    $broker_port = mysqli_real_escape_string($koneksi, $_POST['broker_port']);

    $query = "INSERT INTO device (user_id, device_name, broker_url, mq_user, mq_pass, device_type, broker_port)
              VALUES ('$id_pemilik', '$dev_name', '$broker', '$user_mq', '$pass_mq', '$dev_type', '$broker_port')";

    if (mysqli_query($koneksi, $query)) {
        $new_id = mysqli_insert_id($koneksi);
        $token_code = generateDeviceAccessToken($koneksi, $new_id, $admin_id);
        if ($token_code) {
            insertAdminAuditLog($koneksi, $admin_id, 'add_device', 'device', $new_id, ['name' => $dev_name, 'token' => $token_code]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => "Device added. Device code: $token_code"];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Device added, but failed to generate device code: ' . mysqli_error($koneksi)];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to add device: ' . mysqli_error($koneksi)];
    }
    header("Location: devices.php");
    exit;
}

// Handle Edit Device
if (isset($_POST['edit_device'])) {
    $id_device = mysqli_real_escape_string($koneksi, $_POST['edit_device_id']);
    $id_pemilik = mysqli_real_escape_string($koneksi, $_POST['edit_owner_id']);
    $dev_name  = mysqli_real_escape_string($koneksi, $_POST['edit_device_name']);
    $dev_type  = mysqli_real_escape_string($koneksi, $_POST['edit_device_type']);
    $broker    = mysqli_real_escape_string($koneksi, $_POST['edit_broker_url']);
    $broker_port = mysqli_real_escape_string($koneksi, $_POST['edit_broker_port']);
    $user_mq   = mysqli_real_escape_string($koneksi, $_POST['edit_mq_user']);
    $pass_mq   = mysqli_real_escape_string($koneksi, $_POST['edit_mq_pass']);

    $query_update = "UPDATE device SET
                     user_id     = '$id_pemilik',
                     device_name = '$dev_name',
                     device_type = '$dev_type',
                     broker_url  = '$broker',
                     broker_port = '$broker_port',
                     mq_user     = '$user_mq',
                     mq_pass     = '$pass_mq'
                     WHERE device_id = '$id_device'";

    if (mysqli_query($koneksi, $query_update)) {
        insertAdminAuditLog($koneksi, $admin_id, 'edit_device', 'device', $id_device, ['name' => $dev_name]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Device successfully updated!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to update: ' . mysqli_error($koneksi)];
    }
    header("Location: devices.php");
    exit;
}

// Handle Delete Device
if (isset($_POST['delete_device'])) {
    $id_target = mysqli_real_escape_string($koneksi, $_POST['device_id']);

    // Get info for audit log before deleting
    $res = mysqli_query($koneksi, "SELECT device_name FROM device WHERE device_id = '$id_target'");
    $dev = mysqli_fetch_assoc($res);
    $dev_name = $dev['device_name'] ?? 'Unknown';

    if (mysqli_query($koneksi, "DELETE FROM device WHERE device_id = '$id_target'")) {
        insertAdminAuditLog($koneksi, $admin_id, 'delete_device', 'device', $id_target, ['name' => $dev_name]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Device deleted successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to delete device.'];
    }
    header("Location: devices.php");
    exit;
}

// Handle Token Generation
if (isset($_POST['generate_token'])) {
    $device_id = mysqli_real_escape_string($koneksi, $_POST['device_id']);
    $max_uses = !empty($_POST['max_uses']) ? mysqli_real_escape_string($koneksi, $_POST['max_uses']) : "NULL";
    $expires_at = !empty($_POST['expires_at']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['expires_at']) . "'" : "NULL";

    $token_code = generateDeviceAccessToken($koneksi, $device_id, $admin_id, $max_uses, $expires_at);

    if ($token_code) {
        // Audit Log
        insertAdminAuditLog($koneksi, $admin_id, 'generate_token', 'device', $device_id, ['token' => $token_code]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => "Device code generated: $token_code"];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to generate device code.'];
    }
    header("Location: devices.php");
    exit;
}

// Handle Token Deactivation
if (isset($_POST['toggle_token'])) {
    $token_id = mysqli_real_escape_string($koneksi, $_POST['token_id']);
    $new_status = mysqli_real_escape_string($koneksi, $_POST['status']);

    mysqli_query($koneksi, "UPDATE device_access_tokens SET is_active = '$new_status' WHERE token_id = '$token_id'");
    header("Location: devices.php");
    exit;
}

// Fetch Devices with Owner and Access Count, Sorting, and Pagination
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$sort_col = isset($_GET['sort']) ? mysqli_real_escape_string($koneksi, $_GET['sort']) : 'device_id';
$sort_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// Pagination
$limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$allowed_sort = ['device_id', 'device_name', 'device_type', 'owner_name', 'shared_users'];
if (!in_array($sort_col, $allowed_sort)) $sort_col = 'device_id';

$where_clause = $search ? "WHERE d.device_name LIKE '%$search%' OR u.user_name LIKE '%$search%' OR d.device_type LIKE '%$search%'" : "";

$count_sql = "SELECT COUNT(*) as total FROM device d JOIN user u ON d.user_id = u.user_id $where_clause";
$total_rows = mysqli_fetch_assoc(mysqli_query($koneksi, $count_sql))['total'];
$total_pages = ceil($total_rows / $limit);

$sql_devices = "SELECT d.*, u.user_name as owner_name,
                (SELECT COUNT(*) FROM user_device_access uda WHERE uda.device_id = d.device_id AND uda.access_type = 'viewer') as shared_users
                FROM device d
                JOIN user u ON d.user_id = u.user_id
                $where_clause
                ORDER BY $sort_col $sort_dir
                LIMIT $limit OFFSET $offset";
$devices_result = mysqli_query($koneksi, $sql_devices);

// Fetch all users for owner selection
$users_result = mysqli_query($koneksi, "SELECT user_id, user_name FROM user ORDER BY user_name ASC");
$users_list = [];
while($u = mysqli_fetch_assoc($users_result)) $users_list[] = $u;

function sortLink($col, $label) {
    global $sort_col, $sort_dir, $search;
    $new_dir = ($sort_col === $col && $sort_dir === 'ASC') ? 'desc' : 'asc';
    $icon = '';
    if ($sort_col === $col) {
        $icon = $sort_dir === 'ASC' ? ' ↑' : ' ↓';
    }
    return "<a href=\"?search=$search&sort=$col&dir=$new_dir\" class=\"hover:text-accent-green transition\">$label$icon</a>";
}

$page_title = 'Admin - Device Management';
$body_class = 'bg-gray-50 text-gray-900 min-h-screen font-sans pb-20';
$base_url = '../';
include "../components/header.php";
?>

<div class="max-w-7xl mx-auto px-6 py-10">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
        <div>
            <nav class="flex text-sm text-gray-500 mb-2 gap-2 font-semibold">
                <a href="../dashboard.php" class="hover:text-accent-green">Dashboard</a>
                <span>/</span>
                <span class="text-gray-900">Admin Panel</span>
            </nav>
            <h1 class="text-4xl font-extrabold tracking-tight">Device Management</h1>
        </div>

        <div class="flex gap-4">
            <button type="button" onclick="openAddDeviceModal()" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-blue-900/10 transition hover:bg-blue-700">Add Device</button>
            <a href="users.php" class="bg-white text-gray-600 hover:text-accent-green px-6 py-2.5 rounded-xl font-bold border border-gray-200 transition">Users</a>
            <a href="devices.php" class="bg-accent-green text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-green-900/10 transition">Devices</a>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <form method="GET" class="relative w-full md:w-96">
                <input type="hidden" name="sort" value="<?= $sort_col ?>">
                <input type="hidden" name="dir" value="<?= strtolower($sort_dir) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search devices, owners..."
                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-accent-green/20 focus:border-accent-green outline-none transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </form>
            <div class="text-sm text-gray-500 font-medium">
                Showing <?= mysqli_num_rows($devices_result) ?> of <?= $total_rows ?> devices
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"><?= sortLink('device_id', 'Device ID') ?></th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"><?= sortLink('device_name', 'Device Name') ?></th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"><?= sortLink('device_type', 'Type') ?></th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider"><?= sortLink('owner_name', 'Owner') ?></th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center"><?= sortLink('shared_users', 'Shared To') ?></th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($device = mysqli_fetch_assoc($devices_result)): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-mono text-gray-400">#<?= $device['device_id'] ?></td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($device['device_name']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600">
                                    <?= str_replace('esp32-', '', $device['device_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-700"><?= htmlspecialchars($device['owner_name']) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-sm font-bold text-accent-green"><?= $device['shared_users'] ?> users</span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-1">
                                <div class="flex justify-end gap-1">
                                    <button type="button" data-device-id="<?= $device['device_id'] ?>" data-device-name="<?= htmlspecialchars($device['device_name'], ENT_QUOTES, 'UTF-8') ?>" onclick="openTokenModal(this)"
                                        class="bg-accent-green/10 text-accent-green hover:bg-accent-green hover:text-white px-2 py-1 rounded-lg text-[10px] font-bold transition">Code</button>
                                    <button type="button" data-device='<?= htmlJson($device) ?>' onclick="openEditDeviceModal(this)"
                                        class="bg-blue-600/10 text-blue-600 hover:bg-blue-600 hover:text-white px-2 py-1 rounded-lg text-[10px] font-bold transition">Edit</button>
                                    <form method="POST" onsubmit="return confirm('Delete this device permanently?');" class="inline">
                                        <input type="hidden" name="device_id" value="<?= $device['device_id'] ?>">
                                        <button type="submit" name="delete_device" class="bg-red-600/10 text-red-600 hover:bg-red-600 hover:text-white px-2 py-1 rounded-lg text-[10px] font-bold transition">Del</button>
                                    </form>
                                </div>
                                <button type="button" onclick="viewTokens(<?= $device['device_id'] ?>)"
                                    class="text-[10px] font-bold text-gray-400 hover:underline mt-1 block w-full text-right">View Codes</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="p-6 border-t border-gray-100 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?search=<?= $search ?>&sort=<?= $sort_col ?>&dir=<?= strtolower($sort_dir) ?>&page=<?= $i ?>"
                    class="px-4 py-2 rounded-xl text-sm font-bold <?= $page === $i ? 'bg-accent-green text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?> transition">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Device Modal -->
<div id="addDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeAddDeviceModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-8">
            <h3 class="text-2xl font-bold mb-6">Add New Device</h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Device Name</label>
                    <input type="text" name="device_name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Device Type</label>
                    <select name="device_type" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                        <option value="esp32-inkubator">esp32-inkubator</option>
                        <option value="esp32-smartlamp">esp32-smartlamp</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Owner</label>
                    <select name="owner_id" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                        <?php foreach($users_list as $u): ?>
                            <option value="<?= $u['user_id'] ?>" <?= $u['user_id'] == $admin_id ? 'selected' : '' ?>><?= htmlspecialchars($u['user_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Broker URL</label>
                    <input type="text" name="broker_url" required placeholder="broker.hivemq.com" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Port</label>
                    <input type="text" name="broker_port" required placeholder="8080" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">MQ User</label>
                        <input type="text" name="mq_user" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">MQ Pass</label>
                        <input type="password" name="mq_pass" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                    </div>
                </div>
                <div class="mt-8 flex gap-3">
                    <button type="submit" name="add_device" class="flex-1 bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">Save Device</button>
                    <button type="button" onclick="closeAddDeviceModal()" class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Device Modal -->
<div id="editDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeEditDeviceModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-8">
            <h3 class="text-2xl font-bold mb-6">Edit Device</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_device_id" id="edit_device_id">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Device Name</label>
                    <input type="text" name="edit_device_name" id="edit_device_name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Device Type</label>
                    <select name="edit_device_type" id="edit_device_type" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                        <option value="esp32-inkubator">esp32-inkubator</option>
                        <option value="esp32-smartlamp">esp32-smartlamp</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Owner</label>
                    <select name="edit_owner_id" id="edit_owner_id" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                        <?php foreach($users_list as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['user_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Broker URL</label>
                    <input type="text" name="edit_broker_url" id="edit_broker_url" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Port</label>
                    <input type="text" name="edit_broker_port" id="edit_broker_port" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">MQ User</label>
                        <input type="text" name="edit_mq_user" id="edit_mq_user" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">MQ Pass</label>
                        <input type="password" name="edit_mq_pass" id="edit_mq_pass" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                    </div>
                </div>
                <div class="mt-8 flex gap-3">
                    <button type="submit" name="edit_device" class="flex-1 bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition">Update Device</button>
                    <button type="button" onclick="closeEditDeviceModal()" class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Token Generation Modal -->
<div id="tokenModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeTokenModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-8">
            <h3 class="text-2xl font-bold mb-2">Generate Device Code</h3>
            <p id="tokenModalDevice" class="text-gray-500 text-sm mb-6"></p>
            <form method="POST">
                <input type="hidden" name="device_id" id="tokenModalDeviceId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Max Uses (Optional)</label>
                        <input type="number" name="max_uses" placeholder="Unlimited if empty" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Expiry Date (Optional)</label>
                        <input type="datetime-local" name="expires_at" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-accent-green/20">
                    </div>
                </div>
                <div class="mt-8 flex gap-3">
                    <button type="submit" name="generate_token" class="flex-1 bg-accent-green text-white font-bold py-3 rounded-xl hover:bg-green-700 transition">Generate Code</button>
                    <button type="button" onclick="closeTokenModal()" class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Codes Modal -->
<div id="viewTokensModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeViewTokensModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl p-8">
            <h3 class="text-2xl font-bold mb-6">Device Codes</h3>
            <div id="tokensList" class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <!-- Populated via JS/PHP -->
            </div>
            <div class="mt-8 text-right">
                <button type="button" onclick="closeViewTokensModal()" class="bg-gray-100 text-gray-600 font-bold px-6 py-2 rounded-xl hover:bg-gray-200 transition">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openAddDeviceModal() {
        document.getElementById('addDeviceModal').classList.remove('hidden');
    }
    function closeAddDeviceModal() {
        document.getElementById('addDeviceModal').classList.add('hidden');
    }

    function openEditDeviceModal(button) {
        const d = JSON.parse(button.dataset.device);
        document.getElementById('edit_device_id').value = d.device_id;
        document.getElementById('edit_device_name').value = d.device_name || '';
        document.getElementById('edit_device_type').value = d.device_type || '';
        document.getElementById('edit_owner_id').value = d.user_id;
        document.getElementById('edit_broker_url').value = d.broker_url || '';
        document.getElementById('edit_broker_port').value = d.broker_port || '';
        document.getElementById('edit_mq_user').value = d.mq_user || '';
        document.getElementById('edit_mq_pass').value = d.mq_pass || '';
        document.getElementById('editDeviceModal').classList.remove('hidden');
    }
    function closeEditDeviceModal() {
        document.getElementById('editDeviceModal').classList.add('hidden');
    }

    function openTokenModal(button) {
        const id = button.dataset.deviceId;
        const name = button.dataset.deviceName;
        document.getElementById('tokenModalDeviceId').value = id;
        document.getElementById('tokenModalDevice').innerText = "Generating device code for: " + name;
        document.getElementById('tokenModal').classList.remove('hidden');
    }

    function closeTokenModal() {
        document.getElementById('tokenModal').classList.add('hidden');
    }

    async function viewTokens(deviceId) {
        const list = document.getElementById('tokensList');
        list.innerHTML = '<p class="text-center py-10 text-gray-400">Loading codes...</p>';
        document.getElementById('viewTokensModal').classList.remove('hidden');

        try {
            const response = await fetch(`get_tokens.php?device_id=${deviceId}`);
            const tokens = await response.json();

            if (tokens.length === 0) {
                list.innerHTML = '<p class="text-center py-10 text-gray-400">No codes generated for this device.</p>';
                return;
            }

            list.innerHTML = tokens.map(t => `
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <span class="font-mono font-bold text-accent-green text-lg">${t.token_code}</span>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider ${t.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                                ${t.is_active ? 'Active' : 'Revoked'}
                            </span>
                        </div>
                        <p class="text-[10px] text-gray-400 font-semibold uppercase">
                            Uses: ${t.current_uses} / ${t.max_uses || '∞'} • Expires: ${t.expires_at || 'Never'}
                        </p>
                    </div>
                    <form method="POST" action="devices.php">
                        <input type="hidden" name="token_id" value="${t.token_id}">
                        <input type="hidden" name="toggle_token" value="1">
                        <input type="hidden" name="status" value="${t.is_active ? 0 : 1}">
                        <button type="submit" class="text-xs font-bold ${t.is_active ? 'text-red-500 hover:text-red-700' : 'text-green-500 hover:text-green-700'} transition">
                            ${t.is_active ? 'Revoke' : 'Activate'}
                        </button>
                    </form>
                </div>
            `).join('');
        } catch (e) {
            list.innerHTML = '<p class="text-center py-10 text-red-500">Failed to load codes.</p>';
        }
    }

    function closeViewTokensModal() {
        document.getElementById('viewTokensModal').classList.add('hidden');
    }
</script>

<?php include "../components/footer.php"; ?>
