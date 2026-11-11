const registry = {
  incubator: {
    match: (deviceType) => deviceType.includes('inkubator'),
    key: 'incubator',
    views: {
      main: {
        template: 'iot-dashboard/incubator32/incubator',
        body_class: 'p-6 md:p-12 min-h-screen flex flex-col font-sans text-gray-800',
      },
      servo: {
        template: 'iot-dashboard/incubator32/servo',
        body_class: 'p-6 md:p-12 min-h-screen flex flex-col font-sans text-gray-800',
      },
    },
    topics: {
      subscribe: (device) => {
        const id = (device && typeof device === 'object') ? device.device_id : device;
        const mac = (device && typeof device === 'object') ? device.camera_mac : null;
        const list = [
          `incubator/${id}/data`,
          `incubator/${id}/camera/data`
        ];
        if (mac) {
          list.push(`camera/${mac}/data`);
          list.push(`camera/${mac}/status`);
        }
        return list;
      },
      publish: (device) => {
        const id = (device && typeof device === 'object') ? device.device_id : device;
        return `incubator/${id}/con`;
      },
    },
    dashboard: {
      link: (deviceId) => `/device/${deviceId}`,
      icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent-brown" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9a4 4 0 0 0-2 7.5M12 3v2M6.6 18.4l-1.4 1.4M18.8 4.2l-1.4 1.4M2 12h2M20 12h2M6.6 5.6l-1.4-1.4M18.8 19.8l-1.4-1.4"/></svg>',
      badge_color: 'bg-[#FFF8EC] text-accent-brown border border-accent-brown/20',
    },
    defaultName: 'Incubator',
    hasChart: true,
    filterType: 'inkubator',
  },
  smartlamp: {
    match: (deviceType) => deviceType.includes('lamp'),
    key: 'smartlamp',
    views: {
      main: {
        template: 'iot-dashboard/smartlamp32/smartlamp',
        body_class: 'p-6 sm:p-12 md:p-24 min-h-screen font-sans text-gray-900',
      },
    },
    topics: {
      subscribe: (deviceId) => `smartlamp/${deviceId}/status`,
      publish: (deviceId) => `smartlamp/${deviceId}/control`,
    },
    dashboard: {
      link: (deviceId) => `/device/${deviceId}`,
      icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-accent-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 16.5 8 4.5 4.5 0 0 0 12 3.5 4.5 4.5 0 0 0 7.5 8c0 1.5.81 2.82 2 3.5.76.76 1.23 1.52 1.41 2.5"/></svg>',
      badge_color: 'bg-blue-50 text-accent-blue border border-accent-blue/20',
    },
    defaultName: 'Smart Lamp',
    hasChart: false,
    filterType: 'smartlamp',
  },
};

function matchDeviceConfig(deviceType) {
  for (const config of Object.values(registry)) {
    if (config.match(deviceType)) return config;
  }
  return null;
}

function getTopicConfig(device) {
  const config = matchDeviceConfig(device.device_type);
  if (!config) return null;
  return {
    subscribe: config.topics.subscribe(device.device_id),
    publish: config.topics.publish(device.device_id),
  };
}

function getDashboardConfig(device) {
  const config = matchDeviceConfig(device.device_type);
  if (!config) return null;
  return {
    link: config.dashboard.link(device.device_id),
    icon: config.dashboard.icon,
    badge_color: config.dashboard.badge_color,
  };
}

function getDeviceTypesForFilter() {
  return Object.values(registry).map(c => ({
    type: c.filterType,
    label: c.defaultName,
  }));
}

function getDeviceTypeOptions() {
  return Object.values(registry).map(c => ({
    value: `esp32-${c.filterType}`,
    label: `esp32-${c.filterType}`,
  }));
}

module.exports = {
  registry,
  matchDeviceConfig,
  getTopicConfig,
  getDashboardConfig,
  getDeviceTypesForFilter,
  getDeviceTypeOptions,
};
