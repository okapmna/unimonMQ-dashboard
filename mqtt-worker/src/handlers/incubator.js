const pool = require('../config/db');

async function handleIncubator(device, data, buffer) {
  let hasData = false;
  
  // 1. Spike Detection Logic
  if (data.temperature !== undefined || data.humidity !== undefined) {
    const temp = data.temperature !== undefined ? parseFloat(data.temperature) : null;
    const hum = data.humidity !== undefined ? parseFloat(data.humidity) : null;
    
    // Fetch last logged values from DB
    const [rows] = await pool.query('SELECT last_logged_values FROM device WHERE device_id = ?', [device.device_id]);
    const lastState = rows[0]?.last_logged_values || {};
    
    let spikeDetected = false;
    let logPayload = { event: 'change_detected', changes: [] };

    if (temp !== null) {
      const lastTemp = lastState.temperature !== undefined ? parseFloat(lastState.temperature) : null;
      if (lastTemp === null || Math.abs(temp - lastTemp) >= 1.0) {
        spikeDetected = true;
        logPayload.changes.push({ sensor: 'temperature', previous: lastTemp, new: temp, delta: lastTemp !== null ? temp - lastTemp : 0 });
        lastState.temperature = temp;
      }
    }

    if (hum !== null) {
      const lastHum = lastState.humidity !== undefined ? parseFloat(lastState.humidity) : null;
      if (lastHum === null || Math.abs(hum - lastHum) >= 1.0) {
        spikeDetected = true;
        logPayload.changes.push({ sensor: 'humidity', previous: lastHum, new: hum, delta: lastHum !== null ? hum - lastHum : 0 });
        lastState.humidity = hum;
      }
    }

    if (spikeDetected) {
      console.log(`[Device ${device.device_id}] Spike detected! Logging change_event.`);
      // Insert spike log
      await pool.query(
        'INSERT INTO device_logs (device_id, data, log_type) VALUES (?, ?, ?)',
        [device.device_id, JSON.stringify(logPayload), 'change_event']
      );
      // Update last state in device table
      await pool.query(
        'UPDATE device SET last_logged_values = ? WHERE device_id = ?',
        [JSON.stringify(lastState), device.device_id]
      );
    }
  }

  // 2. Existing Aggregation Logic
  if (data.temperature !== undefined) {
    buffer.temps.push(parseFloat(data.temperature));
    hasData = true;
  }
  if (data.humidity !== undefined) {
    buffer.hums.push(parseFloat(data.humidity));
    hasData = true;
  }
  
  if (hasData) {
    console.log(`[Device ${device.device_id}] Sample added. Total samples: ${buffer.temps.length}`);
  } else {
    console.log(`[Device ${device.device_id}] Warning: Received data but 'temperature' or 'humidity' keys are missing!`);
  }
  
  const now = Date.now();
  if (now - buffer.lastSave >= 300000) { // 5 Minutes
    if (buffer.temps.length > 0 || buffer.hums.length > 0) {
      
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

      const summary = {
        temp: getStats(buffer.temps),
        hum: getStats(buffer.hums),
        samples: buffer.temps.length,
        period: "5m"
      };

      await pool.query('INSERT INTO device_logs (device_id, data) VALUES (?, ?)', 
        [device.device_id, JSON.stringify(summary)]);
      
      console.log(`[Device ${device.device_id}] LOG SAVED TO DATABASE:`, JSON.stringify(summary));
    } else {
      console.log(`[Device ${device.device_id}] 5 minutes passed, but no valid samples were collected.`);
    }
    
    buffer.temps = [];
    buffer.hums = [];
    buffer.lastSave = now;
  }
}

module.exports = {
  handleIncubator
};
