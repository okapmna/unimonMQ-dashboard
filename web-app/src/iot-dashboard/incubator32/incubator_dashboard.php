<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../../index.php");
    exit;
}

if (!isset($_GET['device_id'])) {
    echo "<script>alert('Device ID tidak ditemukan!'); window.location='../../dashboard.php';</script>";
    exit;
}

include "../../config/koneksi.php"; 
$user_id = $_SESSION['user_id'];
$device_id = mysqli_real_escape_string($koneksi, $_GET['device_id']);

// Check access (Owner OR Viewer)
if ($_SESSION['role'] === 'admin') {
    $sql_access = "SELECT d.*, 'owner' as access_type FROM device d WHERE d.device_id = '$device_id'";
} else {
    $sql_access = "SELECT d.*, 'owner' as access_type FROM device d WHERE d.device_id = '$device_id' AND d.user_id = '$user_id'
                   UNION
                   SELECT d.*, uda.access_type FROM device d
                   JOIN user_device_access uda ON d.device_id = uda.device_id
                   WHERE d.device_id = '$device_id' AND uda.user_id = '$user_id'";
}
$res_access = mysqli_query($koneksi, $sql_access);
$device_data = mysqli_fetch_assoc($res_access);

if (!$device_data) {
    echo "<script>alert('Akses ditolak!'); window.location='../../dashboard.php';</script>";
    exit;
}

$access_type = $device_data['access_type'];
$is_viewer = ($access_type === 'viewer');

$broker_host = $device_data['broker_url']; 
$mq_user     = $device_data['mq_user'];
$mq_pass     = $device_data['mq_pass'];
$broker_port = $device_data['broker_port'];

$topic_sub   = "incubator/" . $device_id . "/data";
$topic_pub   = "incubator/" . $device_id . "/con";
// Fetch historical data for charts
$log_sql = "SELECT data, created_at FROM device_logs WHERE device_id = '$device_id' AND log_type = 'aggregation' ORDER BY created_at DESC LIMIT 15";
$log_result = mysqli_query($koneksi, $log_sql);

// Fetch Significant Events (Spikes)
$spike_sql = "SELECT data, created_at FROM device_logs WHERE device_id = '$device_id' AND log_type = 'change_event' ORDER BY created_at DESC LIMIT 10";
$spike_result = mysqli_query($koneksi, $spike_sql);

$chart_labels = [];
$chart_temp_avg = [];
$chart_temp_high = [];
$chart_temp_low = [];
$chart_hum_avg = [];
$chart_hum_high = [];
$chart_hum_low = [];

while($row = mysqli_fetch_assoc($log_result)) {
    $data = json_decode($row['data'], true);
    if(isset($data['temp']['avg'])) {
        $chart_labels[] = $row['created_at'];
        $chart_temp_avg[] = $data['temp']['avg'];
        $chart_temp_high[] = $data['temp']['high'];
        $chart_temp_low[] = $data['temp']['low'];
        
        $chart_hum_avg[] = $data['hum']['avg'];
        $chart_hum_high[] = $data['hum']['high'];
        $chart_hum_low[] = $data['hum']['low'];
    }
}
$chart_labels = array_reverse($chart_labels);
$chart_temp_avg = array_reverse($chart_temp_avg);
$chart_temp_high = array_reverse($chart_temp_high);
$chart_temp_low = array_reverse($chart_temp_low);

$chart_hum_avg = array_reverse($chart_hum_avg);
$chart_hum_high = array_reverse($chart_hum_high);
$chart_hum_low = array_reverse($chart_hum_low);
?>

<?php
$page_title = htmlspecialchars($device_data['device_name']) . ' - Control';
$body_class = 'p-6 md:p-12 min-h-screen flex flex-col font-sans text-gray-800';
$base_url = '../../';
ob_start();
?>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const mqttConfig = {
            host: "<?= $broker_host ?>",
            port: <?= $broker_port ?>, 
            username: "<?= $mq_user ?>", 
            password: "<?= $mq_pass ?>", 
            useSSL: <?= ($broker_port == 8883 || $broker_port == 8884) ? 'true' : 'false' ?>,
            topics: {
                subscribe: "<?= $topic_sub ?>",
                publish: "<?= $topic_pub ?>"
            }
        };

        const batchPresets = {
            chicken: { temp: 37.5, hum: 55, infoTemp: "Optimal: 37.2°C - 37.8°C", infoHum: "Optimal: 55% - 60%" },
            duck:    { temp: 37.8, hum: 60, infoTemp: "Optimal: 37.5°C - 38.0°C", infoHum: "Optimal: 60% - 65%" },
            quail:   { temp: 37.7, hum: 50, infoTemp: "Optimal: 37.5°C - 37.8°C", infoHum: "Optimal: 45% - 55%" }
        };
    </script>

    <style>
        .back-btn { position: fixed; top: 12px; left: 12px; z-index: 50; }
        @media (min-width: 640px) { .back-btn { top: 20px; left: 20px; } }
        .back-btn a { display: flex; align-items: center; gap: 8px; background: #fff; padding: 10px 16px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: 0.3s; }
        .back-btn a span { display: none; }
        @media (min-width: 640px) { .back-btn a span { display: inline; } }

        /* Custom Batch Dropdown */
        .batch-dropdown { position: relative; }
        .batch-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            min-width: 100%;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
            z-index: 100;
            opacity: 0;
            transform: translateY(-8px) scale(0.97);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .batch-dropdown-menu.open {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .batch-dropdown-menu button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 18px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #333;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: background 0.15s ease;
            white-space: nowrap;
        }
        .batch-dropdown-menu button:hover { background: #f3ede5; }
        .batch-dropdown-menu button.active { background: #C69C6D; color: #fff; }
        @media (min-width: 640px) {
            .batch-dropdown-menu { left: auto; right: 0; }
        }
    </style>
<?php
$extra_head = ob_get_clean();
include "../../components/header.php";
?>

    <div class="back-btn">
        <a href="../../dashboard.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span class="font-semibold">Back</span>
        </a>
    </div>

    <div class="max-w-5xl mx-auto w-full mt-10 sm:mt-0">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 sm:mb-12 gap-4 sm:gap-6 border-b border-gray-300 pb-4 sm:pb-8">
            <div>
                <p class="text-xs sm:text-sm font-semibold tracking-wider text-gray-600 uppercase mb-1"><?= htmlspecialchars($device_data['device_name']) ?></p>
                <h1 id="date-display" class="text-3xl sm:text-4xl md:text-5xl font-bold text-black">Loading...</h1>
                <p id="status" class="mt-2 text-xs sm:text-sm font-medium text-orange-600">Status: Connecting...</p>
            </div>
            <div class="flex flex-col items-start md:items-end mt-2 md:mt-0">
                <p class="text-xs font-bold uppercase mb-2">BATCH TYPE</p>
                <!-- Hidden native select for changeBatchType() compatibility -->
                <select id="batch-select" onchange="changeBatchType()" class="hidden">
                    <option value="chicken">Chicken (Ayam)</option>
                    <option value="duck">Duck (Bebek)</option>
                    <option value="quail">Quail (Puyuh)</option>
                </select>
                <!-- Custom Dropdown -->
                <div class="batch-dropdown" id="batch-dropdown">
                    <button onclick="toggleBatchDropdown(event)" id="batch-dropdown-btn" class="bg-[#C69C6D] text-black font-semibold px-5 py-2 rounded-full shadow-sm text-xs sm:text-sm flex items-center gap-2 hover:bg-[#b8885a] transition-colors duration-200 <?= $is_viewer ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $is_viewer ? 'disabled' : '' ?>>
                        <span id="batch-dropdown-label">Chicken (Ayam)</span>
                        <svg id="batch-dropdown-arrow" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <?php if (!$is_viewer): ?>
                    <div class="batch-dropdown-menu" id="batch-dropdown-menu">
                        <button onclick="selectBatch('chicken', 'Chicken (Ayam)')" class="active">Chicken (Ayam)</button>
                        <button onclick="selectBatch('duck', 'Duck (Bebek)')">Duck (Bebek)</button>
                        <button onclick="selectBatch('quail', 'Quail (Puyuh)')">Quail (Puyuh)</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-2 gap-3 sm:gap-8 md:gap-12">
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-white flex flex-col justify-between hover:shadow-2xl hover:-translate-y-1 hover:border-gray-100 transition-all duration-300">
                <div class="flex items-center gap-1.5 sm:gap-3 mb-3 sm:mb-6 font-bold uppercase tracking-wide text-[11px] sm:text-base">
                    <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 3v13.5m0 0a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"></path></svg>
                    <h2>TEMPERATURE</h2>
                </div>
                <div class="text-3xl sm:text-[3.5rem] font-bold text-[#386628] mb-3 sm:mb-8 leading-none"><span id="suhu">--</span>°C</div>
                <div class="border border-black rounded-lg sm:rounded-xl p-2.5 sm:p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[8px] sm:text-[0.65rem] font-bold uppercase mb-0.5 sm:mb-1">TARGET</p>
                            <p id="target-temp" class="text-sm sm:text-xl font-bold">37.5 °C</p>
                        </div>
                        <?php if (!$is_viewer): ?>
                        <div class="flex gap-1.5 sm:gap-2">
                            <button onclick="updateTarget('temp', -0.1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#386628] text-white flex items-center justify-center font-bold text-sm">-</button>
                            <button onclick="updateTarget('temp', 0.1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#386628] text-white flex items-center justify-center font-bold text-sm">+</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="h-px bg-gray-200 my-1.5 sm:my-2 hidden sm:block"></div>
                    <p id="info-optimal-temp" class="text-[0.60rem] sm:text-[0.65rem] font-semibold hidden sm:block">Optimal: 37.2°C - 37.8°C</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-white flex flex-col justify-between hover:shadow-2xl hover:-translate-y-1 hover:border-gray-100 transition-all duration-300">
                <div class="flex items-center gap-1.5 sm:gap-3 mb-3 sm:mb-6 font-bold uppercase tracking-wide text-[11px] sm:text-base">
                    <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.25c-5.385 5.965-8.25 10.975-8.25 14.25a8.25 8.25 0 0016.5 0c0-3.275-2.865-8.285-8.25-14.25z" /></svg>
                    <h2>HUMIDITY</h2>
                </div>
                <div class="text-3xl sm:text-[3.5rem] font-bold text-[#1E88E5] mb-3 sm:mb-8 leading-none"><span id="kelembapan">--</span>%</div>
                <div class="border border-black rounded-lg sm:rounded-xl p-2.5 sm:p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[8px] sm:text-[0.65rem] font-bold uppercase mb-0.5 sm:mb-1">TARGET</p>
                            <p id="target-hum" class="text-sm sm:text-xl font-bold">55%</p>
                        </div>
                        <?php if (!$is_viewer): ?>
                        <div class="flex gap-1.5 sm:gap-2">
                            <button onclick="updateTarget('hum', -1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center font-bold text-sm">-</button>
                            <button onclick="updateTarget('hum', 1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center font-bold text-sm">+</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="h-px bg-gray-200 my-1.5 sm:my-2 hidden sm:block"></div>
                    <p id="info-optimal-hum" class="text-[0.60rem] sm:text-[0.65rem] font-semibold hidden sm:block">Optimal: 55%-60%</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6 sm:gap-12 mt-6 sm:mt-12">
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-gray-100 hover:-translate-y-1 hover:shadow-2xl transition-all duration-300">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xs sm:text-sm font-bold uppercase text-gray-500 tracking-wider">Temperature History</h3>
                    <div class="flex gap-4 text-[10px] font-bold uppercase tracking-tight">
                        <div class="flex items-center gap-1.5"><span class="w-3 h-1 bg-[#ef4444] rounded-full"></span> High</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-1 bg-[#386628] rounded-full"></span> Avg</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-1 bg-[#3b82f6] rounded-full"></span> Low</div>
                    </div>
                </div>
                <div class="relative w-full h-64 sm:h-80">
                    <canvas id="tempChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-gray-100 hover:-translate-y-1 hover:shadow-2xl transition-all duration-300">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xs sm:text-sm font-bold uppercase text-gray-500 tracking-wider">Humidity History</h3>
                    <div class="flex gap-4 text-[10px] font-bold uppercase tracking-tight">
                        <div class="flex items-center gap-1.5"><span class="w-3 h-1 bg-[#f59e0b] rounded-full"></span> High</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-1 bg-[#1E88E5] rounded-full"></span> Avg</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-1 bg-[#6366f1] rounded-full"></span> Low</div>
                    </div>
                </div>
                <div class="relative w-full h-64 sm:h-80">
                    <canvas id="humChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-gray-100 hover:-translate-y-1 hover:shadow-2xl transition-all duration-300">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xs sm:text-sm font-bold uppercase text-gray-500 tracking-wider">Significant Events (Spikes)</h3>
                </div>
                <div class="space-y-4">
                    <?php if (mysqli_num_rows($spike_result) > 0): ?>
                        <?php while ($spike = mysqli_fetch_assoc($spike_result)): ?>
                            <?php $s_data = json_decode($spike['data'], true); ?>
                            <div class="flex items-center gap-4 p-4 bg-red-50 rounded-2xl border border-red-100">
                                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-bold text-red-900">Change Detected</p>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                                        <?php foreach ($s_data['changes'] as $change): ?>
                                            <span class="text-[10px] font-semibold uppercase text-red-600">
                                                <?= $change['sensor'] ?>: <?= $change['previous'] ?> → <?= $change['new'] ?> (Δ <?= ($change['delta'] > 0 ? '+' : '') . $change['delta'] ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase"><?= date('H:i', strtotime($spike['created_at'])) ?></p>
                                    <p class="text-[8px] font-medium text-gray-300 uppercase"><?= date('d M', strtotime($spike['created_at'])) ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center py-6 text-gray-400 text-sm italic">No significant changes detected recently.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('date-display').innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

        let currentTargetTemp = 37.5;
        let currentTargetHum = 55;

        // --- CHART INTERFACE ---
        const ctxTemp = document.getElementById('tempChart').getContext('2d');
        const tempChart = new Chart(ctxTemp, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>.map(t => new Date(t + " UTC").toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: false})),
                datasets: [
                    {
                        label: 'High (°C)',
                        data: <?= json_encode($chart_temp_high) ?>,
                        borderColor: '#ef4444',
                        borderDash: [5, 5],
                        borderWidth: 1,
                        fill: false,
                        pointRadius: 0,
                        tension: 0.4
                    },
                    {
                        label: 'Average (°C)',
                        data: <?= json_encode($chart_temp_avg) ?>,
                        borderColor: '#386628',
                        backgroundColor: 'rgba(56, 102, 40, 0.05)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2
                    },
                    {
                        label: 'Low (°C)',
                        data: <?= json_encode($chart_temp_low) ?>,
                        borderColor: '#3b82f6',
                        borderDash: [5, 5],
                        borderWidth: 1,
                        fill: false,
                        pointRadius: 0,
                        tension: 0.4
                    }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false } 
                },
                scales: {
                    y: { 
                        grace: 5,
                        ticks: { font: { size: 10 } } 
                    },
                    x: { ticks: { font: { size: 10 } } }
                }
            }
        });

        const ctxHum = document.getElementById('humChart').getContext('2d');
        const humChart = new Chart(ctxHum, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>.map(t => new Date(t + " UTC").toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: false})),
                datasets: [
                    {
                        label: 'High (%)',
                        data: <?= json_encode($chart_hum_high) ?>,
                        borderColor: '#f59e0b',
                        borderDash: [5, 5],
                        borderWidth: 1,
                        fill: false,
                        pointRadius: 0,
                        tension: 0.4
                    },
                    {
                        label: 'Average (%)',
                        data: <?= json_encode($chart_hum_avg) ?>,
                        borderColor: '#1E88E5',
                        backgroundColor: 'rgba(30, 136, 229, 0.05)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2
                    },
                    {
                        label: 'Low (%)',
                        data: <?= json_encode($chart_hum_low) ?>,
                        borderColor: '#6366f1',
                        borderDash: [5, 5],
                        borderWidth: 1,
                        fill: false,
                        pointRadius: 0,
                        tension: 0.4
                    }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false } 
                },
                scales: {
                    y: { 
                        grace: 5,
                        ticks: { font: { size: 10 } } 
                    },
                    x: { ticks: { font: { size: 10 } } }
                }
            }
        });
        
        let lastChartUpdate = Date.now();

        // MQTT.js CONNECTION
        const protocol = mqttConfig.useSSL ? 'wss' : 'ws';
        const brokerUrl = `${protocol}://${mqttConfig.host}:${mqttConfig.port}/mqtt`;
        const clientOptions = {
            clientId: 'web_incubator_' + Math.random().toString(16).substr(2, 8),
            username: mqttConfig.username,
            password: mqttConfig.password,
            reconnectPeriod: 3000,
            keepalive: 30,
            clean: true
        };

        const client = mqtt.connect(brokerUrl, clientOptions);

        client.on('connect', () => {
            updateStatus('Connected (Online)', 'text-green-600');
            client.subscribe(mqttConfig.topics.subscribe);
            // Request data awal dari ESP32
            client.publish(mqttConfig.topics.publish, 'dev_getinfo');
        });

        client.on('reconnect', () => {
            updateStatus('Reconnecting...', 'text-orange-500');
        });

        client.on('close', () => {
            updateStatus('Disconnected!', 'text-red-600');
        });

        client.on('error', (err) => {
            updateStatus('Connection Error', 'text-red-600');
            console.error('MQTT Error:', err);
        });

        client.on('message', (topic, message) => {
            try {
                const data = JSON.parse(message.toString());

                if (data.temperature !== undefined) {
                    document.getElementById('suhu').innerText = parseFloat(data.temperature).toFixed(2);
                }
                if (data.humidity !== undefined) {
                    document.getElementById('kelembapan').innerText = parseFloat(data.humidity).toFixed(2);
                }
                if (data.target_temp !== undefined || data.t_temp !== undefined) {
                    currentTargetTemp = parseFloat(data.target_temp || data.t_temp);
                    document.getElementById('target-temp').innerText = currentTargetTemp.toFixed(1) + ' °C';
                }
                if (data.target_hum !== undefined || data.t_hum !== undefined) {
                    currentTargetHum = parseFloat(data.target_hum || data.t_hum);
                    document.getElementById('target-hum').innerText = Math.round(currentTargetHum) + '%';
                }

                // Append live data to chart every 1 minute
                if (Date.now() - lastChartUpdate >= 60000 && data.temperature !== undefined && data.humidity !== undefined) {
                    const timeNow = new Date().toLocaleTimeString('en-US', {hour12: false, hour: '2-digit', minute:'2-digit'});
                    const liveTemp = parseFloat(data.temperature).toFixed(2);
                    const liveHum = parseFloat(data.humidity).toFixed(2);
                    
                    // Update Temp Chart (Add current live data to all lines for realism)
                    tempChart.data.labels.push(timeNow);
                    tempChart.data.datasets[0].data.push(liveTemp); // High
                    tempChart.data.datasets[1].data.push(liveTemp); // Avg
                    tempChart.data.datasets[2].data.push(liveTemp); // Low
                    if(tempChart.data.labels.length > 15) { 
                        tempChart.data.labels.shift(); 
                        tempChart.data.datasets.forEach(ds => ds.data.shift());
                    }
                    tempChart.update('none');

                    // Update Hum Chart
                    humChart.data.labels.push(timeNow);
                    humChart.data.datasets[0].data.push(liveHum); // High
                    humChart.data.datasets[1].data.push(liveHum); // Avg
                    humChart.data.datasets[2].data.push(liveHum); // Low
                    if(humChart.data.labels.length > 15) { 
                        humChart.data.labels.shift(); 
                        humChart.data.datasets.forEach(ds => ds.data.shift());
                    }
                    humChart.update('none');

                    lastChartUpdate = Date.now();
                }

            } catch (e) { console.warn('Parsing Error:', e); }
        });

        function updateTarget(type, change) {
            if (type === 'temp') currentTargetTemp = parseFloat((currentTargetTemp + change).toFixed(1));
            else currentTargetHum = currentTargetHum + change;

            document.getElementById('target-temp').innerText = currentTargetTemp.toFixed(1) + ' °C';
            document.getElementById('target-hum').innerText = Math.round(currentTargetHum) + '%';
            sendTargetData();
        }

        function changeBatchType() {
            const settings = batchPresets[document.getElementById('batch-select').value];
            if (settings) {
                currentTargetTemp = settings.temp;
                currentTargetHum = settings.hum;
                document.getElementById('target-temp').innerText = currentTargetTemp + ' °C';
                document.getElementById('target-hum').innerText = currentTargetHum + '%';
                document.getElementById('info-optimal-temp').innerText = settings.infoTemp;
                document.getElementById('info-optimal-hum').innerText = settings.infoHum;
                sendTargetData();
            }
        }

        function sendTargetData() {
            if (client.connected) {
                const payload = JSON.stringify({ target_temp: currentTargetTemp, target_hum: currentTargetHum });
                client.publish(mqttConfig.topics.publish, payload);
            }
        }

        function updateStatus(text, colorClass) {
            const s = document.getElementById('status');
            s.innerText = 'Status: ' + text;
            s.className = 'mt-2 text-sm font-medium ' + colorClass;
        }

        // Custom batch dropdown logic
        function toggleBatchDropdown(e) {
            e.stopPropagation();
            const menu = document.getElementById('batch-dropdown-menu');
            const arrow = document.getElementById('batch-dropdown-arrow');
            const isOpen = menu.classList.contains('open');
            menu.classList.toggle('open', !isOpen);
            arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
        }

        function selectBatch(value, label) {
            document.getElementById('batch-dropdown-label').textContent = label;
            document.querySelectorAll('#batch-dropdown-menu button').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            const sel = document.getElementById('batch-select');
            sel.value = value;
            changeBatchType();
            document.getElementById('batch-dropdown-menu').classList.remove('open');
            document.getElementById('batch-dropdown-arrow').style.transform = '';
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('batch-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                document.getElementById('batch-dropdown-menu').classList.remove('open');
                document.getElementById('batch-dropdown-arrow').style.transform = '';
            }
        });
    </script>
<?php include "../../components/footer.php"; ?>