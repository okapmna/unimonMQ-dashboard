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

// Convert numeric device_id to string ID (e.g. 'inc-312fb4')
async function migrateDeviceIdToString() {
  const [cols] = await pool.query("SHOW COLUMNS FROM device LIKE 'device_id'");
  if (cols.length === 0 || !/int/i.test(cols[0].Type)) return;

  console.log('[DB Init] Migrating device_id from int to varchar...');

  const [fks] = await pool.query(`
    SELECT TABLE_NAME, CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME = 'device'
      AND REFERENCED_COLUMN_NAME = 'device_id'
  `);
  for (const fk of fks) {
    await pool.query(`ALTER TABLE \`${fk.TABLE_NAME}\` DROP FOREIGN KEY \`${fk.CONSTRAINT_NAME}\``).catch(() => {});
  }

  await pool.query("SET FOREIGN_KEY_CHECKS = 0");

  await pool.query(`DROP TABLE IF EXISTS device_id_map`);
  await pool.query(`CREATE TABLE device_id_map (old_id int NOT NULL PRIMARY KEY, new_id varchar(32) NOT NULL)`);
  await pool.query(`ALTER TABLE device CHANGE device_id device_id_old int(10) NOT NULL`);
  await pool.query(`ALTER TABLE device ADD COLUMN device_id varchar(32) NOT NULL AFTER device_id_old`);
  await pool.query(`UPDATE device SET device_id = CONCAT('inc-', LPAD(HEX(device_id_old), 6, '0'))`);
  await pool.query(`UPDATE device SET device_id = 'inc-312fb4' WHERE device_id_old = 14`);
  await pool.query(`INSERT INTO device_id_map (old_id, new_id) SELECT device_id_old, device_id FROM device`);
  await pool.query(`ALTER TABLE device DROP PRIMARY KEY, DROP COLUMN device_id_old, ADD PRIMARY KEY (device_id)`);

  const childTables = ['device_user', 'device_logs', 'camera_captures'];
  for (const table of childTables) {
    const [exists] = await pool.query("SHOW TABLES LIKE ?", [table]);
    if (exists.length === 0) continue;

    await pool.query(`ALTER TABLE \`${table}\` CHANGE device_id device_id_old int(10) NOT NULL`);
    await pool.query(`ALTER TABLE \`${table}\` ADD COLUMN device_id varchar(32) NOT NULL AFTER device_id_old`);
    await pool.query(`UPDATE \`${table}\` t JOIN device_id_map m ON t.device_id_old = m.old_id SET t.device_id = m.new_id`);
    await pool.query(`ALTER TABLE \`${table}\` DROP COLUMN device_id_old`);

    // Re-create the foreign key to device
    await pool.query(`
      ALTER TABLE \`${table}\`
      ADD CONSTRAINT fk_device_string FOREIGN KEY (device_id)
      REFERENCES device (device_id) ON DELETE CASCADE
    `).catch(() => {});
  }

  await pool.query("ALTER TABLE device MODIFY linked_incubator_id varchar(32) DEFAULT NULL").catch(() => {});
  await pool.query(`DROP TABLE IF EXISTS device_id_map`);
  await pool.query("SET FOREIGN_KEY_CHECKS = 1");

  console.log('[DB Init] device_id migration finished.');
}

// Auto-check and create camera_captures table if not exists
async function initDatabaseTables() {
  try {
    await migrateDeviceIdToString();

    await pool.query(`
      CREATE TABLE IF NOT EXISTS camera_captures (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        device_id varchar(32) NOT NULL,
        incubator_id varchar(32) DEFAULT NULL,
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
      await pool.query("ALTER TABLE device ADD COLUMN linked_incubator_id varchar(32) DEFAULT NULL AFTER broker_port");
    } else if (/int/i.test(cols[0].Type)) {
      await pool.query("ALTER TABLE device MODIFY linked_incubator_id varchar(32) DEFAULT NULL");
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
