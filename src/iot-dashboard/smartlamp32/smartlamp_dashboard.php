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
$body_class = 'p-12 md:p-24 min-h-screen font-sans text-gray-900';
$base_url = '../../';
ob_start();
?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.0.1/mqttws31.min.js"></script>

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
        .back-btn { position: fixed; top: 40px; left: 40px; z-index: 50; }
        .back-btn a { display: flex; align-items: center; gap: 8px; background: #fff; padding: 12px 20px; border-radius: 12px; text-decoration: none; color: #333; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .back-btn a:hover { transform: translateX(-5px); }
        
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

    <div class="max-w-6xl mx-auto w-full">
        <header class="flex flex-col md:flex-row justify-between items-start mb-20">
            <div>
                <p class="text-xs font-bold tracking-widest text-gray-500 uppercase mb-2">CURRENT DATE</p>
                <h1 id="date-display" class="text-5xl font-extrabold text-black">Sunday, Dec 14</h1>
                <p id="status" class="mt-4 text-sm font-bold text-orange-500 uppercase tracking-tighter tracking-widest">Status: Connecting...</p>
            </div>
            <div class="mt-8 md:mt-0">
                <p class="text-[10px] font-bold text-right text-gray-500 uppercase mb-2">MODE</p>
                <div class="bg-[#CD853F] text-black font-extrabold px-6 py-2 rounded-full shadow-sm text-sm">
                    Manual Control
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <div class="bg-white rounded-[40px] p-12 shadow-[20px_20px_40px_rgba(0,0,0,0.05)] border border-white flex flex-col justify-between min-h-[400px]">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h2 class="text-lg font-bold tracking-tight uppercase">POWER</h2>
                </div>
                
                <div class="flex flex-col items-center">
                    <div id="power-display" class="text-7xl font-bold text-gray-300 transition-colors duration-500">OFF</div>
                    <div id="status-indicator" class="w-3 h-3 rounded-full bg-gray-300 mt-4"></div>
                </div>

                <div class="border border-gray-200 rounded-2xl p-6 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">SWITCH</p>
                        <p id="switch-label" class="text-xl font-bold">Turn On</p>
                    </div>
                    <button onclick="togglePower()" id="power-btn" class="w-14 h-14 rounded-xl bg-gray-200 text-gray-600 flex items-center justify-center text-3xl font-bold hover:scale-105 transition-all">
                        +
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-[40px] p-12 shadow-[20px_20px_40px_rgba(0,0,0,0.05)] border border-white flex flex-col justify-between min-h-[400px]">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58a.996.996 0 00-1.41 0 .996.996 0 000 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37a.996.996 0 00-1.41 0 .996.996 0 000 1.41l1.06 1.06c.39.39 1.03.39 1.41 0a.996.996 0 000-1.41l-1.06-1.06zm1.06-10.96a.996.996 0 00-1.41-1.41l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36a.996.996 0 00-1.41-1.41l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/></svg>
                    <h2 class="text-lg font-bold tracking-tight uppercase">BRIGHTNESS</h2>
                </div>

                <div class="flex flex-col items-center">
                    <div class="text-7xl font-bold text-lamp-blue"><span id="brightness-val">0</span>%</div>
                    <div class="w-full px-4 mt-6">
                        <input type="range" id="brightness-slider" min="0" max="100" value="0" oninput="updateBrightness(this.value)" onchange="sendControl()">
                    </div>
                </div>

                <div class="border border-gray-200 rounded-2xl p-6 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">PRESET</p>
                        <p class="text-xl font-bold">Standard</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="updateBrightnessStep(10)" class="w-10 h-10 rounded-lg bg-lamp-blue text-white flex items-center justify-center font-bold hover:opacity-80">+</button>
                        <button onclick="updateBrightnessStep(-10)" class="w-10 h-10 rounded-lg bg-lamp-blue text-white flex items-center justify-center font-bold hover:opacity-80">-</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tanggal Dinamis
        const dateElement = document.getElementById('date-display');
        dateElement.innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

        let currentPower = "OFF";
        let currentBrightness = 0;

        // --- MQTT LOGIC ---
        const clientID = "web_lamp_" + new Date().getTime();
        let client = new Paho.MQTT.Client(mqttConfig.host, Number(mqttConfig.port), clientID);

        function connect() {
            client.connect({
                useSSL: mqttConfig.useSSL,
                userName: mqttConfig.username,
                password: mqttConfig.password,
                onSuccess: () => {
                    document.getElementById("status").innerText = "Status: Connected (Online)";
                    document.getElementById("status").className = "mt-4 text-sm font-bold text-green-500 uppercase tracking-widest";
                    client.subscribe(mqttConfig.topics.subscribe.status);
                },
                onFailure: (e) => {
                    document.getElementById("status").innerText = "Status: Connection Failed";
                    document.getElementById("status").className = "mt-4 text-sm font-bold text-red-500 uppercase tracking-widest";
                    setTimeout(connect, 5000);
                }
            });
        }

        client.onMessageArrived = (message) => {
            try {
                const data = JSON.parse(message.payloadString);
                if(data.power) updatePowerUI(data.power);
                if(data.brightness !== undefined) updateBrightnessUI(data.brightness);
            } catch(e) { console.error("Data error", e); }
        };

        // --- UI & CONTROL LOGIC ---
        function updatePowerUI(status) {
            currentPower = status;
            const display = document.getElementById('power-display');
            const indicator = document.getElementById('status-indicator');
            const btn = document.getElementById('power-btn');
            const label = document.getElementById('switch-label');

            if(status === "ON") {
                display.innerText = "ON";
                display.classList.replace('text-gray-300', 'text-lamp-yellow');
                indicator.classList.replace('bg-gray-300', 'bg-lamp-yellow');
                btn.classList.replace('bg-gray-200', 'bg-red-500');
                btn.classList.replace('text-gray-600', 'text-white');
                btn.innerText = "-";
                label.innerText = "Turn Off";
            } else {
                display.innerText = "OFF";
                display.classList.replace('text-lamp-yellow', 'text-gray-300');
                indicator.classList.replace('bg-lamp-yellow', 'bg-gray-300');
                btn.classList.replace('bg-red-500', 'bg-gray-200');
                btn.classList.replace('text-white', 'text-gray-600');
                btn.innerText = "+";
                label.innerText = "Turn On";
            }
        }

        function togglePower() {
            currentPower = (currentPower === "OFF") ? "ON" : "OFF";
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
            if (client.isConnected()) {
                const payload = JSON.stringify({ 
                    power: currentPower, 
                    brightness: parseInt(currentBrightness) 
                });
                const message = new Paho.MQTT.Message(payload);
                message.destinationName = mqttConfig.topics.publish.control;
                client.send(message);
            }
        }

        connect();
    </script>
<?php include "../../components/footer.php"; ?>