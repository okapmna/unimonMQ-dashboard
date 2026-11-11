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
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000
});

// Auto-check and create camera_captures table if not exists
async function initDatabaseTables() {
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS camera_captures (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        device_id int(10) NOT NULL,
        incubator_id int(10) DEFAULT NULL,
        filename varchar(255) NOT NULL,
        filepath varchar(255) NOT NULL,
        filesize int(11) DEFAULT 0,
        trigger_source varchar(50) DEFAULT 'dashboard',
        captured_at timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        KEY device_id (device_id),
        KEY incubator_id (incubator_id),
        CONSTRAINT fk_camera_device FOREIGN KEY (device_id) REFERENCES device (device_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    `);

    // Ensure linked_incubator_id column exists in device table
    const [cols] = await pool.query("SHOW COLUMNS FROM device LIKE 'linked_incubator_id'");
    if (cols.length === 0) {
      await pool.query("ALTER TABLE device ADD COLUMN linked_incubator_id int(10) DEFAULT NULL AFTER broker_port");
    }

    // Ensure camera_mac column exists in device table for hardware MAC pairing
    const [colsMac] = await pool.query("SHOW COLUMNS FROM device LIKE 'camera_mac'");
    if (colsMac.length === 0) {
      await pool.query("ALTER TABLE device ADD COLUMN camera_mac varchar(64) DEFAULT NULL AFTER broker_port");
    }
  } catch (err) {
    console.error('[DB Init] Table check error (may be fine if db not ready):', err.message);
  }
}

initDatabaseTables();

module.exports = pool;
