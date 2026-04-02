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
$username = $_SESSION['username'];
$device_id = mysqli_real_escape_string($koneksi, $_GET['device_id']);

$sql = "SELECT d.* FROM device d 
        JOIN user u ON d.user_id = u.user_id 
        WHERE d.device_id = '$device_id' AND u.user_name = '$username'";
$result = mysqli_query($koneksi, $sql);
$device_data = mysqli_fetch_assoc($result);

if (!$device_data) {
    echo "<script>alert('Akses ditolak!'); window.location='../../dashboard.php';</script>";
    exit;
}

$broker_host = $device_data['broker_url']; 
$mq_user     = $device_data['mq_user'];
$mq_pass     = $device_data['mq_pass'];
$broker_port = $device_data['broker_port'];

$topic_sub   = "incubator/" . $device_id . "/data";
$topic_pub   = "incubator/" . $device_id . "/con";
?>

<?php
$page_title = htmlspecialchars($device_data['device_name']) . ' - Control';
$body_class = 'p-6 md:p-12 min-h-screen flex flex-col font-sans text-gray-800';
$base_url = '../../';
ob_start();
?>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>

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
                    <button onclick="toggleBatchDropdown(event)" id="batch-dropdown-btn" class="bg-[#C69C6D] text-black font-semibold px-5 py-2 rounded-full shadow-sm text-xs sm:text-sm flex items-center gap-2 hover:bg-[#b8885a] transition-colors duration-200">
                        <span id="batch-dropdown-label">Chicken (Ayam)</span>
                        <svg id="batch-dropdown-arrow" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="batch-dropdown-menu" id="batch-dropdown-menu">
                        <button onclick="selectBatch('chicken', 'Chicken (Ayam)')" class="active">Chicken (Ayam)</button>
                        <button onclick="selectBatch('duck', 'Duck (Bebek)')">Duck (Bebek)</button>
                        <button onclick="selectBatch('quail', 'Quail (Puyuh)')">Quail (Puyuh)</button>
                    </div>
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
                        <div class="flex gap-1.5 sm:gap-2">
                            <button onclick="updateTarget('temp', -0.1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#386628] text-white flex items-center justify-center font-bold text-sm">-</button>
                            <button onclick="updateTarget('temp', 0.1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#386628] text-white flex items-center justify-center font-bold text-sm">+</button>
                        </div>
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
                        <div class="flex gap-1.5 sm:gap-2">
                            <button onclick="updateTarget('hum', -1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center font-bold text-sm">-</button>
                            <button onclick="updateTarget('hum', 1)" class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center font-bold text-sm">+</button>
                        </div>
                    </div>
                    <div class="h-px bg-gray-200 my-1.5 sm:my-2 hidden sm:block"></div>
                    <p id="info-optimal-hum" class="text-[0.60rem] sm:text-[0.65rem] font-semibold hidden sm:block">Optimal: 55%-60%</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('date-display').innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

        let currentTargetTemp = 37.5;
        let currentTargetHum = 55;

        // --- MQTT.js CONNECTION ---
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