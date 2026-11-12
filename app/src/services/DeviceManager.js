const mqtt = require('mqtt');
const pool = require('../config/db');
const eventBus = require('./eventBus');
const { matchDeviceConfig, getTopicConfig } = require('../device-types/registry');
const deviceSettings = require('./deviceSettings');
const { handleIncubator } = require('../handlers/incubator');
const { handleSmartlamp } = require('../handlers/smartlamp');
const { handleEspcam } = require('../handlers/espcam');

const handlerMap = {
  incubator: handleIncubator,
  smartlamp: handleSmartlamp,
  espcam: handleEspcam,
};

class DeviceManager {
  constructor() {
    this.mqttClients = {};
    this.deviceBuffers = {};
    this.deviceInfo = {};
  }

  async syncDevices() {
    try {
      const [rows] = await pool.query('SELECT * FROM device');
      const currentDeviceIds = rows.map((r) => r.device_id.toString());
      
      for (const device of rows) {
        this.deviceInfo[device.device_id] = device;
        if (!this.mqttClients[device.device_id]) {
          this.connectDevice(device);
        }
      }
      
      for (const id in this.mqttClients) {
        if (!currentDeviceIds.includes(id)) {
          console.log(`[System] Removing device ${id}`);
          if (this.mqttClients[id]) this.mqttClients[id].end();
          delete this.mqttClients[id];
          delete this.deviceBuffers[id];
        }
      }
    } catch (err) {
      console.error('[System] Sync Database Error:', err.message);
    }
  }

  connectDevice(device) {
    let brokerUrl;
    const port = parseInt(device.broker_port) || 1883;
    if (port === 1883) {
      brokerUrl = `mqtt://${device.broker_url}:${port}`;
    } else if (port === 8883) {
      brokerUrl = `mqtts://${device.broker_url}:${port}`;
    } else if (port === 8884) {
      brokerUrl = `wss://${device.broker_url}:${port}/mqtt`;
    } else if (port === 8000) {
      brokerUrl = `ws://${device.broker_url}:${port}/mqtt`;
    } else {
      brokerUrl = `wss://${device.broker_url}:${port}/mqtt`;
    }
    
    console.log(`[Device ${device.device_id}] Connecting to ${brokerUrl}...`);
    
    const client = mqtt.connect(brokerUrl, {
      clientId: `worker_${device.device_id}_${Math.random().toString(16).substr(2, 4)}`,
      username: device.mq_user,
      password: device.mq_pass,
      reconnectPeriod: 5000,
    });

    this.mqttClients[device.device_id] = client;
    this.deviceInfo[device.device_id] = device;
    this.deviceBuffers[device.device_id] = {
      type: device.device_type,
      temps: [],
      hums: [],
      lastPower: null,
      lastSave: Date.now()
    };

    client.on('connect', async () => {
      console.log(`[Device ${device.device_id}] CONNECTED! Waiting for messages...`);

      const topicConfig = getTopicConfig(device);
      if (topicConfig) {
        const topics = Array.isArray(topicConfig.subscribe) ? topicConfig.subscribe : [topicConfig.subscribe];
        topics.forEach(t => client.subscribe(t));
        console.log(`[Device ${device.device_id}] Subscribed to: ${topics.join(', ')}`);
      }

      // Terapkan target yang disimpan saat dashboard (mis. diubah ketika inkubator offline)
      // begitu perangkat tersambung kembali ke broker.
      try {
        const savedCmd = deviceSettings.toCommand(await deviceSettings.getSettings(device.device_id));
        if (savedCmd && topicConfig) {
          client.publish(topicConfig.publish, JSON.stringify(savedCmd));
          console.log(`[Device ${device.device_id}] Saved targets pushed on connect: ${JSON.stringify(savedCmd)}`);
        }
      } catch (e) {
        console.error(`[Device ${device.device_id}] Push saved targets error:`, e.message);
      }
    });

    client.on('message', async (topic, message) => {
      const rawMsg = message.toString();
      console.log(`[Device ${device.device_id}] Received message on [${topic}]: ${rawMsg}`);

      try {
        const data = JSON.parse(rawMsg);
        const buffer = this.deviceBuffers[device.device_id];
        if (!buffer) return;

        // Broadcast to Socket.IO clients via event bus
        eventBus.emit('device-data', {
          deviceId: device.device_id,
          topic,
          data
        });

        // Jika pesan datang dari kamera (topik camera/... atau data status kamera)
        if (topic.includes('camera') || data.status === 'cam_online' || data.camera_mac || (device.camera_mac && topic.includes(device.camera_mac))) {
          const espcamHandler = handlerMap['espcam'];
          if (espcamHandler) {
            await espcamHandler(device, data, buffer);
          }
        } else {
          const handlerConfig = matchDeviceConfig(device.device_type);
          const handler = handlerConfig ? handlerMap[handlerConfig.key] : null;
          if (handler) {
            await handler(device, data, buffer);
          }
        }
      } catch (e) {
        console.error(`[Device ${device.device_id}] JSON Parse/Process Error:`, e.message);
      }
    });

    client.on('error', (err) => {
      if (!err.message.includes('Not authorized')) {
        console.log(`[Device ${device.device_id}] Connection Error: ${err.message}`);
      }
    });
  }
  
  publish(deviceId, payload, topicOverride = null) {
    const client = this.mqttClients[deviceId];
    const device = this.deviceInfo[deviceId];
    if (!client || !client.connected || !device) return false;

    const topicConfig = getTopicConfig(device);
    if (!topicConfig) return false;

    const targetTopic = topicOverride || topicConfig.publish;
    const msg = typeof payload === 'string' ? payload : JSON.stringify(payload);
    client.publish(targetTopic, msg);
    console.log(`[Device ${deviceId}] Published to [${targetTopic}]: ${msg}`);
    return true;
  }

  requestCameraPing(deviceId) {
    const device = this.deviceInfo[deviceId];
    if (!device) return false;

    const pingPayload = JSON.stringify({
      action: 'ping',
      incubator_id: deviceId,
      timestamp: Date.now()
    });

    if (device.camera_mac) {
      this.publish(deviceId, pingPayload, `camera/${device.camera_mac}/con`);
    }
    this.publish(deviceId, pingPayload, `incubator/${deviceId}/con`);
    console.log(`[Device ${deviceId}] Ping sent to camera (MAC: ${device.camera_mac || 'none'})`);
    return true;
  }

  gracefulShutdown() {
    console.log('[System] Shutting down MQTT clients...');
    for (const id in this.mqttClients) {
      if (this.mqttClients[id]) {
        this.mqttClients[id].end();
      }
    }
  }
}

module.exports = new DeviceManager();
