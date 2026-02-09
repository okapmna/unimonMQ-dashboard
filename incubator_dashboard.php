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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($device_data['device_name']) ?> - Control</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.0.1/mqttws31.min.js"></script>

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

        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Manrope', 'sans-serif'] },
                    colors: { background: '#FFF8EC' }
                }
            }
        }
    </script>

    <style>
        body { background-color: #FFF8EC; }
        .back-btn { position: fixed; top: 20px; left: 20px; z-index: 50; }
        .back-btn a { display: flex; align-items: center; gap: 8px; background: #fff; padding: 10px 16px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: 0.3s; }
    </style>
</head>

<body class="p-8 md:p-12 min-h-screen flex flex-col font-sans text-gray-800">

    <div class="back-btn">
        <a href="../../dashboard.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span class="font-semibold">Back</span>
        </a>
    </div>

    <div class="max-w-5xl mx-auto w-full">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6 border-b border-gray-300 pb-8">
            <div>
                <p class="text-sm font-semibold tracking-wider text-gray-600 uppercase mb-1"><?= htmlspecialchars($device_data['device_name']) ?></p>
                <h1 id="date-display" class="text-4xl md:text-5xl font-bold text-black">Loading...</h1>
                <p id="status" class="mt-2 text-sm font-medium text-orange-600">Status: Connecting...</p>
            </div>
            <div class="flex flex-col items-end">
                <p class="text-xs font-bold uppercase mb-2">BATCH TYPE</p>
                <select id="batch-select" onchange="changeBatchType()" class="bg-[#C69C6D] text-black font-semibold px-8 py-2 rounded-full shadow-sm text-sm outline-none border border-[#C69C6D]">
                    <option value="chicken">Chicken (Ayam)</option>
                    <option value="duck">Duck (Bebek)</option>
                    <option value="quail">Quail (Puyuh)</option>
                </select>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
            <div class="bg-white rounded-3xl p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-white">
                <div class="flex items-center gap-3 mb-6 font-bold uppercase tracking-wide">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 3v13.5m0 0a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"></path></svg>
                    <h2>TEMPERATURE</h2>
                </div>
                <div class="text-[3.5rem] font-bold text-[#386628] mb-8 leading-none"><span id="suhu">--</span>°C</div>
                <div class="border border-black rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase mb-1">TARGET</p>
                            <p id="target-temp" class="text-xl font-bold">37.5 °C</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="updateTarget('temp', 0.1)" class="w-8 h-8 rounded bg-[#386628] text-white flex items-center justify-center font-bold">+</button>
                            <button onclick="updateTarget('temp', -0.1)" class="w-8 h-8 rounded bg-[#386628] text-white flex items-center justify-center font-bold">-</button>
                        </div>
                    </div>
                    <div class="h-px bg-gray-200 my-2"></div>
                    <p id="info-optimal-temp" class="text-[0.65rem] font-semibold">Optimal: 37.2°C - 37.8°C</p>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-8 shadow-[10px_10px_20px_rgba(0,0,0,0.05)] border border-white">
                <div class="flex items-center gap-3 mb-6 font-bold uppercase tracking-wide">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.25c-5.385 5.965-8.25 10.975-8.25 14.25a8.25 8.25 0 0016.5 0c0-3.275-2.865-8.285-8.25-14.25z" /></svg>
                    <h2>HUMIDITY</h2>
                </div>
                <div class="text-[3.5rem] font-bold text-[#1E88E5] mb-8 leading-none"><span id="kelembapan">--</span>%</div>
                <div class="border border-black rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase mb-1">TARGET</p>
                            <p id="target-hum" class="text-xl font-bold">55%</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="updateTarget('hum', 1)" class="w-8 h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center font-bold">+</button>
                            <button onclick="updateTarget('hum', -1)" class="w-8 h-8 rounded bg-[#1E88E5] text-white flex items-center justify-center font-bold">-</button>
                        </div>
                    </div>
                    <div class="h-px bg-gray-200 my-2"></div>
                    <p id="info-optimal-hum" class="text-[0.65rem] font-semibold">Optimal: 55%-60%</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('date-display').innerText = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });

        let currentTargetTemp = 37.5;
        let currentTargetHum = 55;

        const clientID = "web_client_" + Math.random().toString(16).substr(2, 8);
        const client = new Paho.MQTT.Client(mqttConfig.host, Number(mqttConfig.port), clientID);

        function connect() {
            updateStatus("Connecting...", "text-orange-600");
            client.connect({
                useSSL: mqttConfig.useSSL,
                userName: mqttConfig.username,
                password: mqttConfig.password,
                onSuccess: onConnect,
                onFailure: (e) => { console.log(e); setTimeout(connect, 5000); },
                keepAliveInterval: 30
            });
        }

        function onConnect() {
            updateStatus("Connected (Online)", "text-green-600");
            client.subscribe(mqttConfig.topics.subscribe);
            
            // Request data awal dari ESP32
            const msg = new Paho.MQTT.Message("dev_getinfo");
            msg.destinationName = mqttConfig.topics.publish;
            client.send(msg);
        }

        client.onConnectionLost = (res) => {
            updateStatus("Disconnected!", "text-red-600");
            setTimeout(connect, 3000);
        };

        client.onMessageArrived = (message) => {
            try {
                const data = JSON.parse(message.payloadString);
                
                // --- PERUBAHAN DI SINI ---
                // Menggunakan parseFloat() dan .toFixed(2) untuk memformat angka
                if (data.temperature !== undefined) {
                    document.getElementById("suhu").innerText = parseFloat(data.temperature).toFixed(2);
                }
                if (data.humidity !== undefined) {
                    document.getElementById("kelembapan").innerText = parseFloat(data.humidity).toFixed(2);
                }
                // -------------------------

                // Sinkronisasi Target dari Alat
                if (data.target_temp !== undefined || data.t_temp !== undefined) {
                    currentTargetTemp = parseFloat(data.target_temp || data.t_temp);
                    document.getElementById("target-temp").innerText = currentTargetTemp.toFixed(1) + " °C";
                }
                if (data.target_hum !== undefined || data.t_hum !== undefined) {
                    currentTargetHum = parseFloat(data.target_hum || data.t_hum);
                    document.getElementById("target-hum").innerText = Math.round(currentTargetHum) + "%";
                }
            } catch (e) { console.warn("Parsing Error:", e); }
        };

        function updateTarget(type, change) {
            if (type === 'temp') currentTargetTemp = parseFloat((currentTargetTemp + change).toFixed(1));
            else currentTargetHum = currentTargetHum + change;
            
            document.getElementById('target-temp').innerText = currentTargetTemp.toFixed(1) + " °C";
            document.getElementById('target-hum').innerText = Math.round(currentTargetHum) + "%";
            sendTargetData();
        }

        function changeBatchType() {
            const settings = batchPresets[document.getElementById('batch-select').value];
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

        function sendTargetData() {
            if (client.isConnected()) {
                const payload = JSON.stringify({ target_temp: currentTargetTemp, target_hum: currentTargetHum });
                const msg = new Paho.MQTT.Message(payload);
                msg.destinationName = mqttConfig.topics.publish;
                client.send(msg);
            }
        }

        function updateStatus(text, colorClass) {
            const s = document.getElementById("status");
            s.innerText = "Status: " + text;
            s.className = "mt-2 text-sm font-medium " + colorClass;
        }

        connect();
    </script>
</body>
</html>