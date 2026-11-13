const pool = require('../config/db');

async function handleIncubator(device, data, buffer) {
  if (buffer.lastTemp === undefined) {
    buffer.lastTemp = parseFloat(data.temperature) || 0;
  }
  if (buffer.lastHum === undefined) {
    buffer.lastHum = parseFloat(data.humidity) || 0;
  }

  let hasData = false;

  if (data.temperature !== undefined) {
    buffer.temps.push(parseFloat(data.temperature));
    hasData = true;
  }

  if (data.humidity !== undefined) {
    buffer.hums.push(parseFloat(data.humidity));
    hasData = true;
  }

  if (!hasData) {
    // Heartbeat dari perangkat (sensor bermasalah / status online): bukan error
    if (data.sensor_ok === false || data.status === 'online') return;
    console.log(`[Device ${device.device_id}] Warning: Received data but 'temperature' or 'humidity' keys are missing!`);
    return;
  }

  console.log(`[Device ${device.device_id}] Sample added. Total samples: ${buffer.temps.length}`);

  const now = Date.now();
  if (now - buffer.lastSave < 300000) return;

  const getStats = (arr) => {
    if (arr.length === 0) return { avg: 0, median: 0, high: 0, low: 0 };
    const sorted = [...arr].sort((a, b) => a - b);
    const avg = arr.reduce((a, b) => a + b, 0) / arr.length;
    const median = sorted[Math.floor(sorted.length / 2)];
    return {
      avg: parseFloat(avg.toFixed(2)),
      median: parseFloat(median.toFixed(2)),
      high: parseFloat(sorted[sorted.length - 1].toFixed(2)),
      low: parseFloat(sorted[0].toFixed(2))
    };
  };

  const tempStats = getStats(buffer.temps);
  const humStats = getStats(buffer.hums);

  const tempDiff = Math.abs(tempStats.avg - buffer.lastTemp);
  const humDiff = Math.abs(humStats.avg - buffer.lastHum);
  const drastic = tempDiff >= 1 || humDiff >= 10;

  // Simpan ringkasan tiap 5 menit. Penanda 'drastic_change' dipertahankan agar
  // lonjakan bisa dibedakan dari data rutin, tapi data stabil tetap tersimpan
  // sebagai sejarah grafik.
  const summary = {
    temp: tempStats,
    hum: humStats,
    samples: buffer.temps.length,
    reason: drastic ? 'drastic_change' : 'interval_5min'
  };

  await pool.query('INSERT INTO device_logs (device_id, data) VALUES (?, ?)',
    [device.device_id, JSON.stringify(summary)]);

  console.log(`[Device ${device.device_id}] LOG SAVED (${summary.reason}):`, JSON.stringify(summary));

  buffer.lastTemp = tempStats.avg;
  buffer.lastHum = humStats.avg;
  buffer.temps = [];
  buffer.hums = [];
  buffer.lastSave = now;
}

module.exports = {
  handleIncubator
};
