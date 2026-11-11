/**
 * @file simulate_camera.js
 * @brief Simulator Modul ESP32-S3 Cam berbasis MAC Address untuk testing Trigger & Upload
 * 
 * Penggunaan:
 *   node simulate_camera.js [INCUBATOR_ID] [CAM_MAC]
 * Contoh:
 *   node simulate_camera.js 14 cam-d4e5f6
 */

const mqtt = require('mqtt');
const http = require('http');

const INCUBATOR_ID = process.argv[2] || 14;
const CAM_MAC = process.argv[3] || 'cam-d4e5f6';
const BROKER_URL = process.env.BROKER_URL || 'mqtt://broker.hivemq.com:1883';
const SERVER_URL = process.env.SERVER_URL || 'http://localhost:8080';

const TOPIC_CAM_CON = `camera/${CAM_MAC}/con`;
const TOPIC_CAM_DATA = `camera/${CAM_MAC}/data`;
const TOPIC_INC_CON = `incubator/${INCUBATOR_ID}/con`;
const TOPIC_INC_DATA = `incubator/${INCUBATOR_ID}/data`;

console.log('====================================================');
console.log('       ESP32-S3 CAM SIMULATOR (MAC-BASED)          ');
console.log('====================================================');
console.log(`[Simulator] ID Kamera (MAC) : ${CAM_MAC}`);
console.log(`[Simulator] Inkubator ID   : ${INCUBATOR_ID}`);
console.log(`[Simulator] Broker MQTT    : ${BROKER_URL}`);
console.log(`[Simulator] Dashboard URL  : ${SERVER_URL}`);
console.log(`[Simulator] Topic Listen   : ${TOPIC_CAM_CON} & ${TOPIC_INC_CON}`);
console.log('====================================================');

const client = mqtt.connect(BROKER_URL, {
    clientId: `sim_${CAM_MAC}_${Math.random().toString(16).substr(2, 6)}`
});

client.on('connect', () => {
    console.log(`[MQTT] Connected to broker!`);
    client.subscribe([TOPIC_CAM_CON, TOPIC_INC_CON], () => {
        console.log(`[MQTT] Subscribed to [${TOPIC_CAM_CON}] and [${TOPIC_INC_CON}]`);
    });

    // Kirim heartbeat online
    client.publish(TOPIC_CAM_DATA, JSON.stringify({
        status: 'cam_online',
        camera_mac: CAM_MAC,
        incubator_id: parseInt(INCUBATOR_ID),
        ip: '192.168.1.199',
        rssi: -48,
        uptime: 10
    }));
});

client.on('message', async (topic, payload) => {
    const msg = payload.toString();
    console.log(`\n[MQTT IN] Message on [${topic}]: ${msg}`);

    let shouldCapture = false;
    let targetIncubatorId = INCUBATOR_ID;

    if (msg === 'capture' || msg === 'dev_capture') {
        shouldCapture = true;
    } else {
        try {
            const data = JSON.parse(msg);
            if (data.action === 'capture') {
                shouldCapture = true;
                if (data.incubator_id) targetIncubatorId = data.incubator_id;
            }
        } catch (e) {}
    }

    if (shouldCapture) {
        console.log(`[Simulator] Capture command received! Uploading photo for Incubator ${targetIncubatorId}...`);
        await simulateCaptureAndUpload(targetIncubatorId);
    }
});

async function simulateCaptureAndUpload(targetIncId) {
    // Valid 1x1 dummy JPEG buffer
    const dummyJpeg = Buffer.from([
        0xFF, 0xD8, 0xFF, 0xE0, 0x00, 0x10, 0x4A, 0x46, 0x49, 0x46, 0x00, 0x01,
        0x01, 0x01, 0x00, 0x48, 0x00, 0x48, 0x00, 0x00, 0xFF, 0xDB, 0x00, 0x43,
        0x00, 0x08, 0x06, 0x06, 0x07, 0x06, 0x05, 0x08, 0x07, 0x07, 0x07, 0x09,
        0x09, 0x08, 0x0A, 0x0C, 0x14, 0x0D, 0x0C, 0x0B, 0x0B, 0x0C, 0x19, 0x12,
        0x13, 0x0F, 0x14, 0x1D, 0x1A, 0x1F, 0x1E, 0x1D, 0x1A, 0x1C, 0x1C, 0x20,
        0x24, 0x2E, 0x27, 0x20, 0x22, 0x2C, 0x23, 0x1C, 0x1C, 0x28, 0x37, 0x29,
        0x2C, 0x30, 0x31, 0x34, 0x34, 0x34, 0x1F, 0x27, 0x39, 0x3D, 0x38, 0x32,
        0x3C, 0x2E, 0x33, 0x34, 0x32, 0xFF, 0xC0, 0x00, 0x0B, 0x08, 0x00, 0x01,
        0x00, 0x01, 0x01, 0x01, 0x11, 0x00, 0xFF, 0xC4, 0x00, 0x1F, 0x00, 0x00,
        0x01, 0x05, 0x01, 0x01, 0x01, 0x01, 0x01, 0x01, 0x00, 0x00, 0x00, 0x00,
        0x00, 0x00, 0x00, 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08,
        0x09, 0x0A, 0x0B, 0xFF, 0xDA, 0x00, 0x08, 0x01, 0x01, 0x00, 0x00, 0x3F,
        0x00, 0xBF, 0x00, 0xFF, 0xD9
    ]);

    const url = new URL(`${SERVER_URL}/api/devices/${targetIncId}/camera/upload?cam_mac=${CAM_MAC}`);
    const options = {
        hostname: url.hostname,
        port: url.port || 80,
        path: url.pathname + url.search,
        method: 'POST',
        headers: {
            'Content-Type': 'image/jpeg',
            'Content-Length': dummyJpeg.length,
            'X-Trigger': 'simulator-test',
            'X-Camera-Mac': CAM_MAC
        }
    };

    const req = http.request(options, (res) => {
        let respData = '';
        res.on('data', chunk => { respData += chunk; });
        res.on('end', () => {
            console.log(`[HTTP POST] Status: ${res.statusCode}`);
            console.log(`[HTTP POST] Response: ${respData}`);

            // Kirim konfirmasi ke MQTT
            client.publish(TOPIC_CAM_DATA, JSON.stringify({
                status: 'captured',
                camera_mac: CAM_MAC,
                incubator_id: parseInt(targetIncId),
                size: dummyJpeg.length,
                http_code: res.statusCode
            }));
        });
    });

    req.on('error', (e) => {
        console.error(`[HTTP ERROR] Upload failed: ${e.message}`);
    });

    req.write(dummyJpeg);
    req.end();
}
