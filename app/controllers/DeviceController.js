const pool = require('../src/config/db');
const crypto = require('crypto');
const deviceManager = require('../src/services/DeviceManager');
const { getDashboardConfig, matchDeviceConfig, getDeviceTypesForFilter, getDeviceTypeOptions } = require('../src/device-types/registry');

async function generateDeviceId() {
    for (let i = 0; i < 10; i++) {
        const candidate = 'inc-' + crypto.randomBytes(3).toString('hex');
        const [rows] = await pool.query('SELECT 1 FROM device WHERE device_id = ?', [candidate]);
        if (rows.length === 0) return candidate;
    }
    return 'inc-' + crypto.randomBytes(8).toString('hex');
}

class DeviceController {
    async getDashboard(req, res) {
        try {
            const [devices] = await pool.query(`
                SELECT d.*, du.role
                FROM device d
                JOIN device_user du ON d.device_id = du.device_id
                JOIN user u ON du.user_id = u.user_id
                WHERE u.user_name = ?
                ORDER BY d.device_id DESC
            `, [req.session.username]);

            const processedDevices = devices.map(device => {
                const dashConfig = getDashboardConfig(device);
                const typeConfig = matchDeviceConfig(device.device_type);
                const fallbackIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2" ry="2"/><path d="M12 2v20"/><path d="M2 12h20"/></svg>';

                return {
                    ...device,
                    id_val: device.device_id,
                    primary_key: 'device_id',
                    displayName: device.device_name || device.device_type,
                    link: dashConfig ? dashConfig.link : '#',
                    badge_color: dashConfig ? dashConfig.badge_color : 'bg-gray-100 text-gray-600',
                    current_icon: dashConfig ? dashConfig.icon : fallbackIcon,
                    filterType: typeConfig ? typeConfig.filterType : 'other',
                };
            });

            res.render('dashboard', {
                page_title: 'UNIMQ - Dashboard',
                body_class: 'bg-cream-bg text-dark-text min-h-screen font-sans selection:bg-accent-green selection:text-white pb-20',
                username: req.session.username,
                devices: processedDevices,
                deviceTypeFilters: getDeviceTypesForFilter(),
                deviceTypeOptions: getDeviceTypeOptions(),
            });
        } catch (e) {
            console.error(e);
            res.send('Error loading dashboard');
        }
    }

    async handleDashboardPost(req, res) {
        try {
            const id_pemilik = req.session.user_id;
            
            if (req.body.add_device !== undefined) {
                const { device_name, device_type, broker_url, mq_user, mq_pass, broker_port } = req.body;
                const newDeviceId = await generateDeviceId();
                await pool.query(
                    "INSERT INTO device (device_id, device_name, broker_url, mq_user, mq_pass, device_type, broker_port) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [newDeviceId, device_name, broker_url, mq_user, mq_pass, device_type, broker_port]
                );
                await pool.query(
                    "INSERT INTO device_user (device_id, user_id, role, shared_by) VALUES (?, ?, 'owner', NULL)",
                    [newDeviceId, id_pemilik]
                );
                req.session.toast = { type: 'success', message: 'Berhasil tambah device!' };
                deviceManager.syncDevices();
            } else if (req.body.edit_device !== undefined) {
                const { edit_device_id, edit_device_name, edit_device_type, edit_broker_url, edit_broker_port, edit_mq_user, edit_mq_pass } = req.body;
                const [ownerCheck] = await pool.query(
                    "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                    [edit_device_id, id_pemilik]
                );
                if (ownerCheck.length === 0) {
                    req.session.toast = { type: 'error', message: 'Hanya owner yang bisa edit device!' };
                    return res.redirect('/dashboard');
                }
                await pool.query(
                    "UPDATE device SET device_name=?, device_type=?, broker_url=?, broker_port=?, mq_user=?, mq_pass=? WHERE device_id=?",
                    [edit_device_name, edit_device_type, edit_broker_url, edit_broker_port, edit_mq_user, edit_mq_pass, edit_device_id]
                );
                req.session.toast = { type: 'success', message: 'Berhasil update device!' };
                deviceManager.syncDevices();
            } else if (req.body.btn_hapus_pintar !== undefined) {
                const { id_hapus_target, nama_kolom_target } = req.body;
                if (nama_kolom_target === 'device_id') {
                    const [ownerCheck] = await pool.query(
                        "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                        [id_hapus_target, id_pemilik]
                    );
                    if (ownerCheck.length === 0) {
                        req.session.toast = { type: 'error', message: 'Hanya owner yang bisa hapus device!' };
                        return res.redirect('/dashboard');
                    }
                    await pool.query("DELETE FROM device WHERE device_id = ?", [id_hapus_target]);
                    req.session.toast = { type: 'success', message: 'Berhasil! Device terhapus.' };
                    deviceManager.syncDevices();
                }
            }
        } catch (e) {
            req.session.toast = { type: 'error', message: 'Action failed: ' + e.message };
        }
        res.redirect('/dashboard');
    }

    async apiGetDevices(req, res) {
        try {
            const [devices] = await pool.query(`
                SELECT d.*, du.role
                FROM device d
                JOIN device_user du ON d.device_id = du.device_id
                WHERE du.user_id = ?
            `, [req.user.user_id]);
            res.json(devices);
        } catch (e) {
            res.status(500).json({ error: 'Failed to fetch devices' });
        }
    }

    async shareDevice(req, res) {
        try {
            const device_id = req.params.id;
            const owner_id = req.session.user_id;
            const { username, role } = req.body;

            const [ownerCheck] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                [device_id, owner_id]
            );
            if (ownerCheck.length === 0) {
                req.session.toast = { type: 'error', message: 'Hanya owner yang bisa share device!' };
                return res.redirect('/dashboard');
            }

            const [targetUser] = await pool.query(
                "SELECT user_id FROM user WHERE user_name = ?",
                [username]
            );
            if (targetUser.length === 0) {
                req.session.toast = { type: 'error', message: 'User tidak ditemukan!' };
                return res.redirect('/dashboard');
            }

            const targetUserId = targetUser[0].user_id;
            if (targetUserId === owner_id) {
                req.session.toast = { type: 'error', message: 'Tidak bisa share ke diri sendiri!' };
                return res.redirect('/dashboard');
            }

            const [existing] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ?",
                [device_id, targetUserId]
            );
            if (existing.length > 0) {
                req.session.toast = { type: 'error', message: 'Device sudah dishare ke user ini!' };
                return res.redirect('/dashboard');
            }

            const validRole = ['viewer', 'operator'].includes(role) ? role : 'viewer';
            await pool.query(
                "INSERT INTO device_user (device_id, user_id, role, shared_by) VALUES (?, ?, ?, ?)",
                [device_id, targetUserId, validRole, owner_id]
            );

            req.session.toast = { type: 'success', message: `Device berhasil dishare ke ${username} sebagai ${validRole}!` };
            res.redirect('/dashboard');
        } catch (e) {
            req.session.toast = { type: 'error', message: 'Share failed: ' + e.message };
            res.redirect('/dashboard');
        }
    }

    async unshareDevice(req, res) {
        try {
            const device_id = req.params.id;
            const user_id = req.params.userId;
            const owner_id = req.session.user_id;

            const [ownerCheck] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                [device_id, owner_id]
            );
            if (ownerCheck.length === 0) {
                req.session.toast = { type: 'error', message: 'Hanya owner yang bisa unshare device!' };
                return res.redirect('/dashboard');
            }

            await pool.query(
                "DELETE FROM device_user WHERE device_id = ? AND user_id = ? AND role != 'owner'",
                [device_id, user_id]
            );

            req.session.toast = { type: 'success', message: 'Akses user berhasil dicabut!' };
            res.redirect('/dashboard');
        } catch (e) {
            req.session.toast = { type: 'error', message: 'Unshare failed: ' + e.message };
            res.redirect('/dashboard');
        }
    }

    async getDeviceUsers(req, res) {
        try {
            const device_id = req.params.id;
            const user_id = req.session.user_id;

            const [accessCheck] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                [device_id, user_id]
            );
            if (accessCheck.length === 0) {
                return res.status(403).json({ error: 'Forbidden' });
            }

            const [users] = await pool.query(`
                SELECT u.user_id, u.user_name, du.role, du.created_at,
                       su.user_name AS shared_by_name
                FROM device_user du
                JOIN user u ON du.user_id = u.user_id
                LEFT JOIN user su ON du.shared_by = su.user_id
                WHERE du.device_id = ?
                ORDER BY du.role ASC, du.created_at DESC
            `, [device_id]);

            res.json(users);
        } catch (e) {
            res.status(500).json({ error: 'Failed to fetch users' });
        }
    }

    async apiShareDevice(req, res) {
        try {
            const device_id = req.params.id;
            const owner_id = req.user.user_id;
            const { username, role } = req.body;

            const [ownerCheck] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                [device_id, owner_id]
            );
            if (ownerCheck.length === 0) {
                return res.status(403).json({ error: 'Only owner can share device' });
            }

            const [targetUser] = await pool.query(
                "SELECT user_id FROM user WHERE user_name = ?",
                [username]
            );
            if (targetUser.length === 0) {
                return res.status(404).json({ error: 'User not found' });
            }

            const targetUserId = targetUser[0].user_id;
            if (targetUserId === owner_id) {
                return res.status(400).json({ error: 'Cannot share to yourself' });
            }

            const [existing] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ?",
                [device_id, targetUserId]
            );
            if (existing.length > 0) {
                return res.status(409).json({ error: 'Device already shared to this user' });
            }

            const validRole = ['viewer', 'operator'].includes(role) ? role : 'viewer';
            await pool.query(
                "INSERT INTO device_user (device_id, user_id, role, shared_by) VALUES (?, ?, ?, ?)",
                [device_id, targetUserId, validRole, owner_id]
            );

            res.json({ message: `Device shared to ${username} as ${validRole}` });
        } catch (e) {
            res.status(500).json({ error: 'Share failed: ' + e.message });
        }
    }

    async apiUnshareDevice(req, res) {
        try {
            const device_id = req.params.id;
            const target_user_id = req.params.userId;
            const owner_id = req.user.user_id;

            const [ownerCheck] = await pool.query(
                "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
                [device_id, owner_id]
            );
            if (ownerCheck.length === 0) {
                return res.status(403).json({ error: 'Only owner can unshare device' });
            }

            await pool.query(
                "DELETE FROM device_user WHERE device_id = ? AND user_id = ? AND role != 'owner'",
                [device_id, target_user_id]
            );

            res.json({ message: 'User access revoked' });
        } catch (e) {
            res.status(500).json({ error: 'Unshare failed: ' + e.message });
        }
    }
}

module.exports = new DeviceController();
