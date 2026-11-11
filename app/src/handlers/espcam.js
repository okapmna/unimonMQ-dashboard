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
      const logEntry = {
        event: 'Photo Captured',
        filename: data.filename || 'unknown.jpg',
        size: data.size || 0,
        time: new Date().toISOString()
      };

      await pool.query('INSERT INTO device_logs (device_id, data) VALUES (?, ?)', [
        device.device_id,
        JSON.stringify(logEntry)
      ]);

      console.log(`[Device ${device.device_id}] ESP32-CAM captured photo:`, data.filename);
    }
  } catch (err) {
    console.error(`[Device ${device.device_id}] handleEspcam error:`, err.message);
  }
}

module.exports = {
  handleEspcam
};
