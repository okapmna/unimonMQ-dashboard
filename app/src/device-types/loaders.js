const pool = require('../config/db');
const { matchDeviceConfig } = require('./registry');
const deviceSettings = require('../services/deviceSettings');

const loaders = {
  incubator: async (deviceId) => {
    // Target tersimpan (terakhir diatur dari dashboard, walau perangkat offline)
    const saved_targets = await deviceSettings.getSettings(deviceId);

    const [logs] = await pool.query(
      "SELECT data, created_at FROM device_logs WHERE device_id = ? AND JSON_EXTRACT(data, '$.temp.avg') IS NOT NULL ORDER BY created_at DESC LIMIT 15",
      [deviceId]
    );

    const chart_labels = [];
    const chart_temp_avg = [];
    const chart_temp_high = [];
    const chart_temp_low = [];
    const chart_hum_avg = [];
    const chart_hum_high = [];
    const chart_hum_low = [];

    logs.forEach(row => {
      try {
        const data = JSON.parse(row.data);
        if (data.temp && data.temp.avg !== undefined) {
          chart_labels.push(row.created_at);
          chart_temp_avg.push(data.temp.avg);
          chart_temp_high.push(data.temp.high);
          chart_temp_low.push(data.temp.low);
          chart_hum_avg.push(data.hum.avg);
          chart_hum_high.push(data.hum.high);
          chart_hum_low.push(data.hum.low);
        }
      } catch (e) {}
    });

    // Ambil riwayat foto kamera untuk inkubator ini
    let camPhotos = [];
    try {
      const [photos] = await pool.query(
        "SELECT * FROM camera_captures WHERE incubator_id = ? OR device_id = ? ORDER BY captured_at DESC LIMIT 50",
        [deviceId, deviceId]
      );
      camPhotos = photos;
    } catch (err) {
      console.error('[Loader incubator camera photos error]:', err.message);
    }

    return {
      saved_targets,
      chart_labels: chart_labels.reverse(),
      chart_temp_avg: chart_temp_avg.reverse(),
      chart_temp_high: chart_temp_high.reverse(),
      chart_temp_low: chart_temp_low.reverse(),
      chart_hum_avg: chart_hum_avg.reverse(),
      chart_hum_high: chart_hum_high.reverse(),
      chart_hum_low: chart_hum_low.reverse(),
      latestCamPhoto: camPhotos.length > 0 ? camPhotos[0] : null,
      recentCamPhotos: camPhotos,
      totalCamPhotos: camPhotos.length
    };
  },
  smartlamp: async (deviceId) => {
    return {};
  },
};

async function loadDeviceViewData(device) {
  const config = matchDeviceConfig(device.device_type);
  if (!config) return {};
  const loader = loaders[config.key];
  if (!loader) return {};
  return loader(device.device_id);
}

module.exports = { loadDeviceViewData, loaders };
