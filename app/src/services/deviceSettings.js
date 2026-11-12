const pool = require('../config/db');

// Field yang diizinkan disimpan (sinkron dengan perintah MQTT inkubator)
const ALLOWED = ['target_temp', 'target_hum', 'fan_speed', 'servo_mode', 'servo_interval'];

/**
 * Simpan target dari perintah dashboard. Hanya field yang dikirim yang di-update
 * (partial update), sehingga field lain tidak tertimpa.
 */
async function saveSettings(deviceId, command) {
  if (!command || typeof command !== 'object') return false;
  const updates = {};
  for (const key of ALLOWED) {
    if (command[key] === undefined || command[key] === null) continue;
    const v = parseFloat(command[key]);
    if (Number.isNaN(v)) continue;
    updates[key] = v;
  }
  if (Object.keys(updates).length === 0) return false;

  const cols = Object.keys(updates);
  const placeholders = cols.map(() => '?').join(', ');
  const setSql = cols.map(c => `\`${c}\` = VALUES(\`${c}\`)`).join(', ');
  await pool.query(
    `INSERT INTO device_settings (device_id, ${cols.map(c => `\`${c}\``).join(', ')})
     VALUES (?, ${placeholders})
     ON DUPLICATE KEY UPDATE ${setSql}`,
    [deviceId, ...cols.map(c => updates[c])]
  );
  return true;
}

/** Ambil target tersimpan untuk sebuah device (null bila belum ada) */
async function getSettings(deviceId) {
  try {
    const [rows] = await pool.query(
      'SELECT target_temp, target_hum, fan_speed, servo_mode, servo_interval FROM device_settings WHERE device_id = ?',
      [deviceId]
    );
    return rows[0] || null;
  } catch (e) {
    return null;
  }
}

/** Susun objek perintah MQTT dari baris tersimpan (skip kolom NULL) */
function toCommand(row) {
  if (!row) return null;
  const cmd = {};
  for (const key of ALLOWED) {
    if (row[key] !== null && row[key] !== undefined) cmd[key] = row[key];
  }
  return Object.keys(cmd).length > 0 ? cmd : null;
}

module.exports = { saveSettings, getSettings, toCommand, ALLOWED };
