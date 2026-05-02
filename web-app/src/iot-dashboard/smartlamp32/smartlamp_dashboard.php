<?php
session_start();
// 1. Cek Login
if (!isset($_SESSION['username'])) {
    header("Location: ../../index.php");
    exit;
}

// 2. Cek Parameter ID Device
if (!isset($_GET['device_id'])) {
    echo "<script>alert('Device ID tidak ditemukan!'); window.location='../../dashboard.php';</script>";
    exit;
}

include "../../config/koneksi.php"; 
$username = $_SESSION['username'];
$device_id = mysqli_real_escape_string($koneksi, $_GET['device_id']);

// 3. Ambil Data Device (Validasi kepemilikan)
$sql = "SELECT d.* FROM device d 
        JOIN user u ON d.user_id = u.user_id 
        WHERE d.device_id = '$device_id' AND u.user_name = '$username'";
$result = mysqli_query($koneksi, $sql);
$device_data = mysqli_fetch_assoc($result);

if (!$device_data) {
    echo "<script>alert('Device tidak ditemukan atau Anda tidak memiliki akses!'); window.location='../../dashboard.php';</script>";
    exit;
}

// 4. Konfigurasi Broker
$broker_host = $device_data['broker_url']; 
$mq_user     = $device_data['mq_user'];
$mq_pass     = $device_data['mq_pass'];
$broker_port = $device_data['broker_port'];

// Topik spesifik Smart Lamp
$topic_sub   = "smartlamp/" . $device_id . "/status";
$topic_pub   = "smartlamp/" . $device_id . "/control";
?>

<?php
$page_title = htmlspecialchars($device_data['device_name']) . ' - Smart Lamp';
$body_class = 'p-6 sm:p-12 md:p-24 min-h-screen font-sans text-gray-900';
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
                subscribe: { status: "<?= $topic_sub ?>" },
                publish: { control: "<?= $topic_pub ?>" }
            }
        };
    </script>

    <style>
        .back-btn { position: fixed; top: 12px; left: 12px; z-index: 50; }
        @media (min-width: 640px) { .back-btn { top: 40px; left: 40px; } }
        .back-btn a { display: flex; align-items: center; gap: 8px; background: #fff; padding: 10px 16px; sm:padding: 12px 20px; border-radius: 12px; text-decoration: none; color: #333; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .back-btn a:hover { transform: translateX(-5px); }
        .back-btn a span { display: none; }
        @media (min-width: 640px) { .back-btn a span { display: inline; } }
        
        /* Slider Styling mirip dengan input target */
        input[type=range] { -webkit-appearance: none; width: 100%; background: transparent; }
        input[type=range]::-webkit-slider-runnable-track { width: 100%; height: 4px; cursor: pointer; background: #e5e7eb; border-radius: 2px; }
        input[type=range]::-webkit-slider-thumb { height: 20px; width: 20px; border-radius: 50%; background: #1E90FF; cursor: pointer; -webkit-appearance: none; margin-top: -8px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
    </style>
<?php
$extra_head = ob_get_clean();
include "../../components/header.php";
?>

    <div class="back-btn">
        <a href="../../dashboard.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span>Back</span>
        </a>
    </div>

    <div class="max-w-6xl mx-auto w-full mt-10 sm:mt-0">
        <header class="flex flex-col md:flex-row justify-between items-start mb-10 sm:mb-20">
            <div>
                <p class="text-[10px] sm:text-xs font-bold tracking-widest text-gray-500 uppercase mb-1 sm:mb-2">CURRENT DATE</p>
                <h1 id="date-display" class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-black">Sunday, Dec 14</h1>
                <p id="status" class="mt-2 sm:mt-4 text-[10px] sm:text-sm font-bold text-orange-500 uppercase tracking-tighter sm:tracking-widest">Status: Connecting...</p>
            </div>
            <div class="mt-6 md:mt-0 flex flex-col items-start md:items-end w-full md:w-auto">
                <p class="text-[8px] sm:text-[10px] font-bold text-left md:text-right text-gray-500 uppercase mb-1 sm:mb-2">MODE</p>
                <div class="bg-[#CD853F] text-black font-extrabold px-4 sm:px-6 py-1.5 sm:py-2 rounded-full shadow-sm text-xs sm:text-sm inline-block">
                    Manual Control
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 sm:gap-12">
            <div class="bg-white rounded-3xl sm:rounded-[40px] p-8 sm:p-12 shadow-[10px_10px_30px_rgba(0,0,0,0.05)] sm:shadow-[20px_20px_40px_rgba(0,0,0,0.05)] border border-white flex flex-col justify-between min-h-[300px] sm:min-h-[400px] hover:shadow-2xl hover:-translate-y-1 hover:border-gray-100 transition-all duration-300">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h2 class="text-lg font-bold tracking-tight uppercase">POWER</h2>
                </div>
                
                <div class="flex flex-col items-center my-6 sm:my-0">
                    <div id="power-display" class="text-6xl sm:text-7xl font-bold text-gray-300 transition-colors duration-500">OFF</div>
                    <div id="status-indicator" class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-gray-300 mt-2 sm:mt-4"></div>
                </div>

                <div class="border border-gray-200 rounded-xl sm:rounded-2xl p-4 sm:p-6 flex items-center justify-between">
                    <div>
                        <p class="text-[8px] sm:text-[10px] font-bold text-gray-400 uppercase tracking-widest">SWITCH</p>
                        <p id="switch-label" class="text-lg sm:text-xl font-bold">Turn On</p>
                    </div>
                    <button onclick="togglePower()" id="power-btn" class="w-12 h-12 sm:w-14 sm:h-14 rounded-lg sm:rounded-xl bg-gray-200 text-gray-600 flex items-center justify-center text-2xl sm:text-3xl font-bold hover:scale-105 transition-all">
                        +
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-3xl sm:rounded-[40px] p-8 sm:p-12 shadow-[10px_10px_30px_rgba(0,0,0,0.05)] sm:shadow-[20px_20px_40px_rgba(0,0,0,0.05)] border border-white flex flex-col justify-between min-h-[300px] sm:min-h-[400px] hover:shadow-2xl hover:-translate-y-1 hover:border-gray-100 transition-all duration-300">
                <div class="flex items-center gap-2 sm:gap-3">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58a.996.996 0 00-1.41 0 .996.996 0 000 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37a.996.996 0 00-1.41 0 .996.996 0 000 1.41l1.06 1.06c.39.39 1.03.39 1.41 0a.996.996 0 000-1.41l-1.06-1.06zm1.06-10.96a.996.996 0 00-1.41-1.41l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36a.996.996 0 00-1.41-1.41l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/></svg>
                    <h2 class="text-base sm:text-lg font-bold tracking-tight uppercase">BRIGHTNESS</h2>
                </div>

                <div class="flex flex-col items-center my-6 sm:my-0">
                    <div class="text-6xl sm:text-7xl font-bold text-lamp-blue"><span id="brightness-val">0</span>%</div>
                    <div class="w-full px-2 sm:px-4 mt-4 sm:mt-6">
                        <input type="range" id="brightness-slider" min="0" max="100" value="0" oninput="updateBrightness(this.value)" onchange="sendControl()">
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl sm:rounded-2xl p-4 sm:p-6 flex items-center justify-between">
                    <div>
                        <p class="text-[8px] sm:text-[10px] font-bold text-gray-400 uppercase tracking-widest">PRESET</p>
                        <p class="text-lg sm:text-xl font-bold">Standard</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="updateBrightnessStep(10)" class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-lamp-blue text-white flex items-center justify-center font-bold hover:opacity-80">+</button>
                        <button onclick="updateBrightnessStep(-10)" class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-lamp-blue text-white flex items-center justify-center font-bold hover:opacity-80">-</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tanggal Dinamis
        const dateElement = document.getElementById('date-display');
        dateElement.innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

        let currentPower = 'OFF';
        let currentBrightness = 0;

        // --- MQTT.js CONNECTION ---
        const protocol = mqttConfig.useSSL ? 'wss' : 'ws';
        const brokerUrl = `${protocol}://${mqttConfig.host}:${mqttConfig.port}/mqtt`;
        const clientOptions = {
            clientId: 'web_lamp_' + Math.random().toString(16).substr(2, 8),
            username: mqttConfig.username,
            password: mqttConfig.password,
            reconnectPeriod: 3000,
            keepalive: 30,
            clean: true
        };

        const client = mqtt.connect(brokerUrl, clientOptions);

        client.on('connect', () => {
            document.getElementById('status').innerText = 'Status: Connected (Online)';
            document.getElementById('status').className = 'mt-2 sm:mt-4 text-[10px] sm:text-sm font-bold text-green-500 uppercase tracking-tighter sm:tracking-widest';
            client.subscribe(mqttConfig.topics.subscribe.status);
        });

        client.on('reconnect', () => {
            document.getElementById('status').innerText = 'Status: Reconnecting...';
            document.getElementById('status').className = 'mt-2 sm:mt-4 text-[10px] sm:text-sm font-bold text-orange-500 uppercase tracking-tighter sm:tracking-widest';
        });

        client.on('close', () => {
            document.getElementById('status').innerText = 'Status: Disconnected!';
            document.getElementById('status').className = 'mt-2 sm:mt-4 text-[10px] sm:text-sm font-bold text-red-500 uppercase tracking-tighter sm:tracking-widest';
        });

        client.on('error', (err) => {
            console.error('MQTT Error:', err);
        });

        client.on('message', (topic, message) => {
            try {
                const data = JSON.parse(message.toString());
                if (data.power) updatePowerUI(data.power);
                if (data.brightness !== undefined) updateBrightnessUI(data.brightness);
            } catch(e) { console.error('Data error', e); }
        });

        // --- UI & CONTROL LOGIC ---
        function updatePowerUI(status) {
            currentPower = status;
            const display = document.getElementById('power-display');
            const indicator = document.getElementById('status-indicator');
            const btn = document.getElementById('power-btn');
            const label = document.getElementById('switch-label');

            if (status === 'ON') {
                display.innerText = 'ON';
                display.classList.replace('text-gray-300', 'text-lamp-yellow');
                indicator.classList.replace('bg-gray-300', 'bg-lamp-yellow');
                btn.classList.replace('bg-gray-200', 'bg-red-500');
                btn.classList.replace('text-gray-600', 'text-white');
                btn.innerText = '-';
                label.innerText = 'Turn Off';
            } else {
                display.innerText = 'OFF';
                display.classList.replace('text-lamp-yellow', 'text-gray-300');
                indicator.classList.replace('bg-lamp-yellow', 'bg-gray-300');
                btn.classList.replace('bg-red-500', 'bg-gray-200');
                btn.classList.replace('text-white', 'text-gray-600');
                btn.innerText = '+';
                label.innerText = 'Turn On';
            }
        }

        function togglePower() {
            currentPower = (currentPower === 'OFF') ? 'ON' : 'OFF';
            updatePowerUI(currentPower);
            sendControl();
        }

        function updateBrightness(val) {
            currentBrightness = val;
            document.getElementById('brightness-val').innerText = val;
        }

        function updateBrightnessUI(val) {
            currentBrightness = val;
            document.getElementById('brightness-val').innerText = val;
            document.getElementById('brightness-slider').value = val;
        }

        function updateBrightnessStep(step) {
            currentBrightness = Math.min(100, Math.max(0, parseInt(currentBrightness) + step));
            updateBrightnessUI(currentBrightness);
            sendControl();
        }

        function sendControl() {
            if (client.connected) {
                const payload = JSON.stringify({
                    power: currentPower,
                    brightness: parseInt(currentBrightness)
                });
                client.publish(mqttConfig.topics.publish.control, payload);
            }
        }
    </script>
<?php include "../../components/footer.php"; ?>