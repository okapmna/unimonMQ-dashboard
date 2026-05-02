<?php
include "auth_check.php";
include "../config/koneksi.php";

$username = $_SESSION['username'];
$admin_id = $_SESSION['user_id'];

// Handle Token Generation
if (isset($_POST['generate_token'])) {
    $device_id = mysqli_real_escape_string($koneksi, $_POST['device_id']);
    $max_uses = !empty($_POST['max_uses']) ? mysqli_real_escape_string($koneksi, $_POST['max_uses']) : "NULL";
    $expires_at = !empty($_POST['expires_at']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['expires_at']) . "'" : "NULL";
    
    // Generate unique human-readable token (8 chars)
    $token_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    
    $query = "INSERT INTO device_access_tokens (device_id, token_code, created_by, max_uses, expires_at) 
              VALUES ('$device_id', '$token_code', '$admin_id', $max_uses, $expires_at)";
    
    if (mysqli_query($koneksi, $query)) {
        // Audit Log
        mysqli_query($koneksi, "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details) VALUES ('$admin_id', 'generate_token', 'device', '$device_id', '{\"token\": \"$token_code\"}')");
        $_SESSION['toast'] = ['type' => 'success', 'message' => "Token generated: $token_code"];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Failed to generate token.'];
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
                            <td class="px-6 py-4 text-right space-x-2">
                                <button onclick="openTokenModal(<?= $device['device_id'] ?>, '<?= htmlspecialchars($device['device_name']) ?>')" 
                                    class="bg-accent-green/10 text-accent-green hover:bg-accent-green hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition">Generate Token</button>
                                <button onclick="viewTokens(<?= $device['device_id'] ?>)" 
                                    class="text-xs font-bold text-gray-500 hover:underline">View Tokens</button>
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

<!-- Token Generation Modal -->
<div id="tokenModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeTokenModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-8">
            <h3 class="text-2xl font-bold mb-2">Generate Access Token</h3>
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
                    <button type="submit" name="generate_token" class="flex-1 bg-accent-green text-white font-bold py-3 rounded-xl hover:bg-green-700 transition">Generate</button>
                    <button type="button" onclick="closeTokenModal()" class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Tokens Modal (AJAX could be used here, for now a simple list via a hidden section) -->
<div id="viewTokensModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeViewTokensModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl p-8">
            <h3 class="text-2xl font-bold mb-6">Device Tokens</h3>
            <div id="tokensList" class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <!-- Populated via JS/PHP -->
            </div>
            <div class="mt-8 text-right">
                <button onclick="closeViewTokensModal()" class="bg-gray-100 text-gray-600 font-bold px-6 py-2 rounded-xl hover:bg-gray-200 transition">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openTokenModal(id, name) {
        document.getElementById('tokenModalDeviceId').value = id;
        document.getElementById('tokenModalDevice').innerText = "Generating sharing token for: " + name;
        document.getElementById('tokenModal').classList.remove('hidden');
    }
    
    function closeTokenModal() {
        document.getElementById('tokenModal').classList.add('hidden');
    }

    async function viewTokens(deviceId) {
        const list = document.getElementById('tokensList');
        list.innerHTML = '<p class="text-center py-10 text-gray-400">Loading tokens...</p>';
        document.getElementById('viewTokensModal').classList.remove('hidden');
        
        try {
            const response = await fetch(`get_tokens.php?device_id=${deviceId}`);
            const tokens = await response.json();
            
            if (tokens.length === 0) {
                list.innerHTML = '<p class="text-center py-10 text-gray-400">No tokens generated for this device.</p>';
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
            list.innerHTML = '<p class="text-center py-10 text-red-500">Failed to load tokens.</p>';
        }
    }

    function closeViewTokensModal() {
        document.getElementById('viewTokensModal').classList.add('hidden');
    }
</script>

<?php include "../components/footer.php"; ?>