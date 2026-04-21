const mqtt = require('mqtt');
const mysql = require('mysql2/promise');

const DB_CONFIG = {
  host: process.env.DB_HOST || 'db',
  user: process.env.DB_USER || 'user_app',
  password: process.env.DB_PASS || 'password_app',
  database: process.env.DB_NAME || 'unimq',
};

const pool = mysql.createPool({
  ...DB_CONFIG,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

const mqttClients = {};
const deviceBuffers = {};

async function startWorker() {
  console.log('--- MQTT BACKGROUND WORKER DEBUG MODE ---');
  setInterval(syncDevices, 20000);
  syncDevices();
}

async function syncDevices() {
  try {
    const [rows] = await pool.query('SELECT * FROM device');
    const currentDeviceIds = rows.map((r) => r.device_id.toString());
    
    for (const device of rows) {
      if (!mqttClients[device.device_id]) {
        connectDevice(device);
      }
    }
    
    for (const id in mqttClients) {
      if (!currentDeviceIds.includes(id)) {
        console.log(`[System] Removing device ${id}`);
        if (mqttClients[id]) mqttClients[id].end();
        delete mqttClients[id];
        delete deviceBuffers[id];
      }
    }
  } catch (err) {
    console.error('[System] Sync Database Error:', err.message);
  }
}

function connectDevice(device) {
  const protocol = (device.broker_port == 8883 || device.broker_port == 8884) ? 'wss' : 'ws';
  const brokerUrl = `${protocol}://${device.broker_url}:${device.broker_port}/mqtt`;
  
  console.log(`[Device ${device.device_id}] Connecting to ${brokerUrl}...`);
  
  const client = mqtt.connect(brokerUrl, {
    clientId: `worker_debug_${device.device_id}_${Math.random().toString(16).substr(2, 4)}`,
    username: device.mq_user,
    password: device.mq_pass,
    reconnectPeriod: 5000,
  });

  mqttClients[device.device_id] = client;
  deviceBuffers[device.device_id] = {
    type: device.device_type,
    temps: [],
    hums: [],
    lastPower: null,
    lastSave: Date.now()
  };

  client.on('connect', () => {
    console.log(`[Device ${device.device_id}] CONNECTED! Waiting for messages...`);
    
    if (device.device_type.includes('incubator') || device.device_type.includes('inkubator')) {
      const topic = `incubator/${device.device_id}/data`;
      client.subscribe(topic);
      console.log(`[Device ${device.device_id}] Subscribed to: ${topic}`);
    } else if (device.device_type.includes('smartlamp')) {
      const topic = `smartlamp/${device.device_id}/status`;
      client.subscribe(topic);
      console.log(`[Device ${device.device_id}] Subscribed to: ${topic}`);
    }
  });

  client.on('message', async (topic, message) => {
    const rawMsg = message.toString();
    console.log(`[Device ${device.device_id}] Received message on [${topic}]: ${rawMsg}`);

    try {
      const data = JSON.parse(rawMsg);
      const buffer = deviceBuffers[device.device_id];
      if (!buffer) return;

      // --- INCUBATOR LOGIC ---
      if (buffer.type.includes('incubator') || buffer.type.includes('inkubator')) {
        let hasData = false;
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

      // --- SMARTLAMP LOGIC ---
      if (buffer.type.includes('smartlamp') && data.power !== undefined) {
        if (data.power !== buffer.lastPower) {
          const logEntry = { event: "Power Switched", status: data.power };
          await pool.query('INSERT INTO device_logs (device_id, data) VALUES (?, ?)', 
            [device.device_id, JSON.stringify(logEntry)]);
          
          console.log(`[Device ${device.device_id}] LOG SAVED (Power Change):`, data.power);
          buffer.lastPower = data.power;
        }
      }
    } catch (e) {
      console.error(`[Device ${device.device_id}] JSON Parse Error:`, e.message);
    }
  });

  client.on('error', (err) => {
    console.log(`[Device ${device.device_id}] Connection Error Path: ${err.message}`);
  });
}

startWorker();
