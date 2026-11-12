const pool = require('../config/db');

async function handleEspcam(device, data, buffer) {
  try {
    if (!buffer) return;

    buffer.lastSeen = Date.now();

    if (data.ip) {
      buffer.ip = data.ip;
    }

    if (data.status) {
      buffer.status = data.status;
    }

    if (data.flash !== undefined) {
      buffer.flash = data.flash;
    }

    // Jika kamera melaporkan event capture via MQTT
    if (data.status === 'captured') {
      // Foto sudah tercatat di tabel camera_captures (via upload HTTP).
      // Jangan tulis ke device_logs agar tidak mengotori riwayat suhu/kelembapan.
      console.log(`[Device ${device.device_id}] ESP32-CAM captured photo:`, data.filename);
    }
  } catch (err) {
    console.error(`[Device ${device.device_id}] handleEspcam error:`, err.message);
  }
}

module.exports = {
  handleEspcam
};
