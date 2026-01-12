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

include "../../config/koneksi.php"; // Sesuaikan path jika perlu
$username = $_SESSION['username'];
$device_id = mysqli_real_escape_string($koneksi, $_GET['device_id']);

// 3. Ambil Data Device Secara Spesifik (Validasi kepemilikan user juga)
$sql = "SELECT d.* FROM device d 
        JOIN user u ON d.user_id = u.user_id 
        WHERE d.device_id = '$device_id' AND u.user_name = '$username'";
$result = mysqli_query($koneksi, $sql);
$device_data = mysqli_fetch_assoc($result);

if (!$device_data) {
    echo "<script>alert('Device tidak ditemukan atau Anda tidak memiliki akses!'); window.location='../../dashboard.php';</script>";
    exit;
}

// 4. Siapkan Data untuk JavaScript
// Default Port Websocket (biasanya 8080 atau 8083 untuk WSS, 9001, dll)
// Karena di database tidak ada kolom port, kita hardcode atau buat logika default
$broker_host = $device_data['broker_url']; 
$mq_user     = $device_data['mq_user'];
$mq_pass     = $device_data['mq_pass'];
$broker_port = $device_data['broker_port'];

// Membuat Topik Unik berdasarkan ID Device agar tidak bentrok antar user
// Format: incubator/{device_id}/data
$topic_sub   = "incubator/" . $device_id . "/data";
$topic_pub   = "incubator/" . $device_id . "/con";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($device_data['device_name']) ?> - Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.0.1/mqttws31.min.js"></script>

    <script>
        // Inject Data dari PHP ke Variable JavaScript
        const mqttConfig = {
            host: "<?= $broker_host ?>",
            port: <?= $broker_port ?>, // Pastikan broker Anda mendukung WebSocket di port ini (bukan 1883)
            username: "<?= $mq_user ?>", 
            password: "<?= $mq_pass ?>", 
            useSSL: <?= ($broker_port == 8883 || $broker_port == 8884) ? 'true' : 'false' ?>,
            topics: {
                subscribe: {
                    data: "<?= $topic_sub ?>"
                },
                publish: {
                    control: "<?= $topic_pub ?>",
                }
            }
        };
        
        // Debugging di Console Browser
        console.log("Loaded Config for Device ID: <?= $device_id ?>");
        console.log("Broker:", mqttConfig.host);
        console.log("Topic Sub:", mqttConfig.topics.subscribe.data);
    </script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Manrope', 'sans-serif'] },
                    colors: {
                        background: '#FFF8EC',
                        'batch-brown': '#CD853F',
                        'temp-green': '#3E7B27',
                        'hum-blue': '#1E90FF',
                    }
                }
            }
        }
    </script>

    <script>
        // Data preset untuk jenis telur (disimpan di JS client-side)
        const batchPresets = {
            chicken: { temp: 37.5, hum: 55, infoTemp: "Optimal: 37.2°C - 37.8°C", infoHum: "Optimal: 55% - 60%" },
            duck:    { temp: 37.8, hum: 60, infoTemp: "Optimal: 37.5°C - 38.0°C", infoHum: "Optimal: 60% - 65%" },
            quail:   { temp: 37.7, hum: 50, infoTemp: "Optimal: 37.5°C - 37.8°C", infoHum: "Optimal: 45% - 55%" }
        };
    </script>

    <style>
        body { background-color: #FFF8EC; }
        select { -webkit-appearance: none; appearance: none; text-align-last: center; }
        .back-btn { position: fixed; top: 20px; left: 20px; z-index: 50; }
        .back-btn a { display: flex; align-items: center; gap: 8px; background: #fff; padding: 10px 16px; border-radius: 12px; text-decoration: none; color: #333; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .back-btn a:hover { background: #f0f0f0; transform: translateY(-2px); }
    </style>
</head>

<body class="p-8 md:p-12 min-h-screen flex flex-col font-sans text-gray-800">

    <div class="back-btn">
        <a href="../../dashboard.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Back</span>
        </a>
    </div>

    <div class="max-w-5xl mx-auto w-full">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6 border-b border-gray-300 pb-8">
            <div>
                <p class="text-sm font-semibold tracking-wider text-gray-600 uppercase mb-1">
                    <?= htmlspecialchars($device_data['device_name']) ?> </p>
                <h1 id="date-display" class="text-4xl md:text-5xl font-bold text-black">Loading...</h1>
                <p id="status" class="mt-2 text-sm font-medium text-orange-600">Status: Menghubungkan...</p>
                <p class="text-xs text-gray-400 mt-1">Host: <?= htmlspecialchars($broker_host) ?></p>
            </div>
            <div class="flex flex-col items-end">
                <p class="text-xs font-bold tracking-wider text-black uppercase mb-2">BATCH TYPE</p>
                <div class="relative">
                    <select id="batch-select" onchange="changeBatchType()" 
                        class="bg-[#C69C6D] text-black font-semibold px-8 py-2 rounded-full shadow-sm text-sm outline-none cursor-pointer hover:bg-[#b08b61] transition border border-[#C69C6D]">
                        <option value="chicken">Chicken (Ayam)</option>
                        <option value="duck">Duck (Bebek)</option>
                        <option value="quail">Quail (Puyuh)</option>
                    </select>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
            <div class="bg-white rounded-3xl p-8 relative shadow-[10px_10px_20px_rgba(0,0,0,0.1)] border border-white">
                <div class="flex items-center gap-3 mb-6">
                    <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="-2 -2 28 28" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v13.5m0 0a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"></path>
                    </svg>
                    <h2 class="text-lg font-bold tracking-wide uppercase">TEMPERATURE</h2>
                </div>
                <div class="text-[3.5rem] font-bold text-[#386628] mb-8 leading-none">
                    <span id="suhu">--</span>°C
                </div>
                <div class="border border-black rounded-xl p-4 flex flex-col gap-1">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase tracking-wider mb-1">TARGET</p>
                            <p id="target-temp" class="text-xl font-bold">37.5 °C</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="updateTarget('temp', 0.1)" class="w-8 h-8 rounded bg-[#386628] text-white flex items-center justify-center text-xl font-bold hover:opacity-90 transition">+</button>
                            <button onclick="updateTarget('temp', -0.1)" class="w-8 h-8 rounded bg-[#386628] text-white flex items-center justify-center text-xl font-bold hover:opacity-90 transition">-</button>
                        </div>
                    </div>
                    <div class="h-px bg-black my-2"></div>
                    <div class="flex items-center gap-1 text-[0.65rem] font-semibold">
                        <span id="info-optimal-temp">Optimal: 37.2°C - 37.8°C</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-8 relative shadow-[10px_10px_20px_rgba(0,0,0,0.1)] border border-white">
                <div class="flex items-center gap-3 mb-6">
                    <svg class="w-6 h-6 text-black" fill="currentColor" viewBox="-2 -2 28 28">
                        <path d="M12 2.25c-5.385 5.965-8.25 10.975-8.25 14.25a8.25 8.25 0 0016.5 0c0-3.275-2.865-8.285-8.25-14.25z" />
                    </svg>
                    <h2 class="text-lg font-bold tracking-wide uppercase">HUMIDITY</h2>
                </div>
                <div class="text-[3.5rem] font-bold text-[#1E88E5] mb-8 leading-none">
                    <span id="kelembapan">--</span>%
                </div>
                <div class="border border-black rounded-xl p-4 flex flex-col gap-1">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase tracking-wider mb-1">TARGET</p>
                            <p id="target-hum" class="text-xl font-bold">55%</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="updateTarget('hum', 1)" class="w-8 h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center text-xl font-bold hover:opacity-90 transition">+</button>
                            <button onclick="updateTarget('hum', -1)" class="w-8 h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center text-xl font-bold hover:opacity-90 transition">-</button>
                        </div>
                    </div>
                    <div class="h-px bg-black my-2"></div>
                    <div class="flex items-center gap-1 text-[0.65rem] font-semibold">
                        <span id="info-optimal-hum">Optimal : 55%-60%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set Tanggal
        const dateElement = document.getElementById('date-display');
        dateElement.innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

        let currentTargetTemp = 37.5;
        let currentTargetHum = 55;

        // --- MQTT SETUP MENGGUNAKAN CONFIG DARI PHP ---
        const clientID = "web_client_" + new Date().getTime();
        
        // Menggunakan mqttConfig yang sudah di-inject oleh PHP di atas
        let client = new Paho.MQTT.Client(mqttConfig.host, Number(mqttConfig.port), clientID);
        let reconnectTimeout = null;

        client.onConnectionLost = function (responseObject) {
            console.log("Connection Lost: " + responseObject.errorMessage);
            document.getElementById("status").innerText = "Status: Terputus!";
            document.getElementById("status").className = "mt-2 text-sm font-medium text-red-600";
            if (reconnectTimeout) clearTimeout(reconnectTimeout);
            reconnectTimeout = setTimeout(connect, 3000);
        };

        client.onMessageArrived = function (message) {
            if (message.destinationName === mqttConfig.topics.subscribe.data) {
                try {
                    const sensorData = JSON.parse(message.payloadString);
                    if (sensorData.temperature !== undefined) document.getElementById("suhu").innerText = sensorData.temperature;
                    if (sensorData.humidity !== undefined) document.getElementById("kelembapan").innerText = sensorData.humidity;
                } catch (error) { console.error("JSON Error:", error); }
            }
        };

        const options = {
            useSSL: mqttConfig.useSSL,
            userName: mqttConfig.username,
            password: mqttConfig.password,
            onSuccess: onConnect,
            onFailure: doFail,
            keepAliveInterval: 30,
            timeout: 10
        };

        function connect() {
            document.getElementById("status").innerText = "Status: Menghubungkan...";
            document.getElementById("status").className = "mt-2 text-sm font-medium text-orange-600";
            try { client.connect(options); } catch (e) { doFail(e); }
        }

        function onConnect() {
            console.log("Connected to " + mqttConfig.host);
            document.getElementById("status").innerText = "Status: Terhubung (Online)";
            document.getElementById("status").className = "mt-2 text-sm font-medium text-green-600";
            client.subscribe(mqttConfig.topics.subscribe.data);
        }

        function doFail(e) {
            console.log("Connect Failed:", e);
            document.getElementById("status").innerText = "Status: Gagal Koneksi";
            document.getElementById("status").className = "mt-2 text-sm font-medium text-red-600";
            if (reconnectTimeout) clearTimeout(reconnectTimeout);
            reconnectTimeout = setTimeout(connect, 5000);
        }

        // --- LOGIC GANTI BATCH & CONTROL ---
        function changeBatchType() {
            const type = document.getElementById('batch-select').value;
            const settings = batchPresets[type];
            if (settings) {
                currentTargetTemp = settings.temp;
                currentTargetHum = settings.hum;
                document.getElementById('target-temp').innerText = currentTargetTemp + " °C";
                document.getElementById('target-hum').innerText = currentTargetHum + "%";
                document.getElementById('info-optimal-temp').innerText = settings.infoTemp;
                document.getElementById('info-optimal-hum').innerText = settings.infoHum;
                sendTargetData();
            }
        }

        function updateTarget(type, change) {
            if (type === 'temp') {
                currentTargetTemp = parseFloat((currentTargetTemp + change).toFixed(1));
                document.getElementById('target-temp').innerText = currentTargetTemp + " °C";
            } else if (type === 'hum') {
                currentTargetHum = currentTargetHum + change;
                document.getElementById('target-hum').innerText = currentTargetHum + "%";
            }
            sendTargetData();
        }

        function sendTargetData() {
            const payload = JSON.stringify({ target_temp: currentTargetTemp, target_hum: currentTargetHum });
            if (client.isConnected()) {
                const message = new Paho.MQTT.Message(payload);
                message.destinationName = mqttConfig.topics.publish.control;
                client.send(message);
                console.log("Published to " + mqttConfig.topics.publish.control + ": " + payload);
            }
        }

        connect();
    </script>
</body>
</html>