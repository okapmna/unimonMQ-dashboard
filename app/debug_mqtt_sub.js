const mqtt = require('mqtt');

const client = mqtt.connect('wss://broker.hivemq.com:8884/mqtt', {
  clientId: 'debug_sub_' + Math.random().toString(16).slice(2, 8),
  keepalive: 30,
  reconnectPeriod: 5000,
});

client.on('connect', () => {
  console.log('[SUB] connected to broker.hivemq.com:8884');
  client.subscribe(['incubator/#', 'camera/#', 'inc-312fb4/#'], (err) => {
    console.log('[SUB] subscribe done', err ? err.message : 'OK');
  });
});

client.on('message', (topic, msg) => {
  console.log(`[${new Date().toISOString()}] ${topic}: ${msg.toString().slice(0, 300)}`);
});

client.on('error', (e) => console.log('[ERR]', e.message));

setTimeout(() => {
  console.log('[SUB] 60s elapsed, exiting');
  process.exit(0);
}, 60000);
