const fs = require('fs');
const path = require('path');
const pool = require('../src/config/db');
const deviceManager = require('../src/services/DeviceManager');

const UPLOAD_DIR = path.join(__dirname, '..', 'public', 'uploads', 'captures');
const SERVER_URL = process.env.SERVER_URL || 'http://10.10.10.30:8180';

function getUserId(req) {
    if (req.user && req.user.user_id) return req.user.user_id;
    if (req.session && req.session.user_id) return req.session.user_id;
    return null;
}

async function getDeviceAccess(deviceId, userId) {
    const [rows] = await pool.query(
        'SELECT role FROM device_user WHERE device_id = ? AND user_id = ?',
        [deviceId, userId]
    );
    return rows.length > 0 ? rows[0].role : null;
}

async function broadcast(req, deviceId, event, payload) {
    const io = req.app.get('io');
    if (io) io.to(`device:${deviceId}`).emit(event, payload);
}

async function pairCamera(req, res) {
    const deviceId = req.params.id;
    const userId = getUserId(req);
    const { camera_mac } = req.body || {};

    if (!camera_mac || !camera_mac.trim()) {
        return res.status(400).json({ success: false, error: 'camera_mac wajib diisi' });
    }

    try {
        const role = await getDeviceAccess(deviceId, userId);
        if (!role) return res.status(403).json({ success: false, error: 'Access denied' });
        if (role === 'viewer') return res.status(403).json({ success: false, error: 'Viewer tidak dapat mengubah sandingan kamera' });

        const mac = camera_mac.trim();
        await pool.query('UPDATE device SET camera_mac = ? WHERE device_id = ?', [mac, deviceId]);

        broadcast(req, deviceId, 'camera-paired', { deviceId, camera_mac: mac });
        deviceManager.requestCameraPing(deviceId);

        res.json({ success: true, message: 'Kamera berhasil disandingkan!', camera_mac: mac });
    } catch (e) {
        console.error('[CameraController pairCamera]', e.message);
        res.status(500).json({ success: false, error: 'Gagal menyandingkan kamera' });
    }
}

async function unpairCamera(req, res) {
    const deviceId = req.params.id;
    const userId = getUserId(req);

    try {
        const role = await getDeviceAccess(deviceId, userId);
        if (!role) return res.status(403).json({ success: false, error: 'Access denied' });
        if (role === 'viewer') return res.status(403).json({ success: false, error: 'Viewer tidak dapat mengubah sandingan kamera' });

        await pool.query('UPDATE device SET camera_mac = NULL WHERE device_id = ?', [deviceId]);

        broadcast(req, deviceId, 'camera-unpaired', { deviceId });

        res.json({ success: true, message: 'Kamera berhasil dilepas' });
    } catch (e) {
        console.error('[CameraController unpairCamera]', e.message);
        res.status(500).json({ success: false, error: 'Gagal melepas kamera' });
    }
}

async function triggerCapture(req, res) {
    const deviceId = req.params.id;
    const userId = getUserId(req);

    try {
        const role = await getDeviceAccess(deviceId, userId);
        if (!role) return res.status(403).json({ success: false, error: 'Access denied' });
        if (role === 'viewer') return res.status(403).json({ success: false, error: 'Akses viewer tidak dapat mengambil foto' });

        const [rows] = await pool.query('SELECT camera_mac FROM device WHERE device_id = ?', [deviceId]);
        if (rows.length === 0) return res.status(404).json({ success: false, error: 'Device tidak ditemukan' });

        const camera_mac = rows[0].camera_mac || null;
        const cmd = JSON.stringify({
            action: 'capture',
            incubator_id: deviceId,
            camera_mac: camera_mac,
            server_url: SERVER_URL,
            upload_url: `${SERVER_URL}/api/devices/${deviceId}/camera/upload?cam_mac=${camera_mac || ''}`,
            timestamp: Date.now()
        });

        const published = deviceManager.publish(deviceId, cmd);
        if (camera_mac) {
            deviceManager.publish(deviceId, cmd, `camera/${camera_mac}/con`);
        }

        broadcast(req, deviceId, 'camera-status', { status: 'capturing', message: 'Trigger foto terkirim...' });

        res.json({ success: true, published });
    } catch (e) {
        console.error('[CameraController triggerCapture]', e.message);
        res.status(500).json({ success: false, error: 'Gagal memicu capture' });
    }
}

async function resolveCaptureTarget(req) {
    const camMac = (req.query.cam_mac || '').trim();

    if (req.params.id) {
        const [rows] = await pool.query('SELECT * FROM device WHERE device_id = ?', [req.params.id]);
        if (rows.length === 0) return null;
        return { device: rows[0], incubatorId: rows[0].device_id };
    }

    if (camMac) {
        const [paired] = await pool.query('SELECT * FROM device WHERE camera_mac = ? LIMIT 1', [camMac]);
        if (paired.length > 0) {
            return { device: paired[0], incubatorId: paired[0].device_id };
        }
        const [camDevice] = await pool.query('SELECT * FROM device WHERE device_id = ? LIMIT 1', [camMac]);
        if (camDevice.length > 0) {
            return { device: camDevice[0], incubatorId: camDevice[0].linked_incubator_id || null };
        }
    }

    return null;
}

async function uploadCapture(req, res) {
    try {
        const target = await resolveCaptureTarget(req);
        if (!target) return res.status(404).json({ success: false, error: 'Device/kamera tidak dikenal' });

        const buf = Buffer.isBuffer(req.body) ? req.body : Buffer.from(req.body || []);
        if (!buf || buf.length === 0) {
            return res.status(400).json({ success: false, error: 'Body gambar kosong' });
        }

        if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

        const safeId = String(target.device.device_id).replace(/[^a-zA-Z0-9_-]/g, '');
        const filename = `${safeId}-${Date.now()}.jpg`;
        const fullPath = path.join(UPLOAD_DIR, filename);
        fs.writeFileSync(fullPath, buf);

        const filepath = `/uploads/captures/${filename}`;
        const [result] = await pool.query(
            'INSERT INTO camera_captures (device_id, incubator_id, filename, filepath, filesize, trigger_source) VALUES (?, ?, ?, ?, ?, ?)',
            [target.device.device_id, target.incubatorId, filename, filepath, buf.length, 'camera']
        );

        const [saved] = await pool.query('SELECT * FROM camera_captures WHERE id = ?', [result.insertId]);
        const photo = saved[0] || { id: result.insertId, filename, filepath, captured_at: new Date() };

        const roomId = target.incubatorId || target.device.device_id;
        broadcast(req, roomId, 'camera-photo-received', photo);

        console.log(`[CameraController] Uploaded ${filepath} (${buf.length} bytes) for ${target.device.device_id}`);
        res.json({ success: true, id: photo.id, filename, filepath, filesize: buf.length });
    } catch (e) {
        console.error('[CameraController uploadCapture]', e.message);
        res.status(500).json({ success: false, error: 'Gagal menyimpan foto' });
    }
}

async function getCaptures(req, res) {
    const deviceId = req.params.id;
    try {
        const [photos] = await pool.query(
            'SELECT * FROM camera_captures WHERE incubator_id = ? OR device_id = ? ORDER BY captured_at DESC LIMIT 50',
            [deviceId, deviceId]
        );
        res.json({ success: true, captures: photos });
    } catch (e) {
        console.error('[CameraController getCaptures]', e.message);
        res.status(500).json({ success: false, error: 'Gagal memuat foto' });
    }
}

async function deleteCapture(req, res) {
    const deviceId = req.params.id;
    const captureId = req.params.captureId;
    const userId = getUserId(req);

    try {
        const role = await getDeviceAccess(deviceId, userId);
        if (!role) return res.status(403).json({ success: false, error: 'Access denied' });
        if (role === 'viewer') return res.status(403).json({ success: false, error: 'Viewer tidak dapat menghapus foto' });

        const [rows] = await pool.query(
            'SELECT * FROM camera_captures WHERE id = ? AND (device_id = ? OR incubator_id = ?)',
            [captureId, deviceId, deviceId]
        );
        if (rows.length === 0) return res.status(404).json({ success: false, error: 'Foto tidak ditemukan' });

        const capture = rows[0];
        if (capture.filepath && capture.filepath.startsWith('/uploads/')) {
            const baseDir = path.dirname(path.join(UPLOAD_DIR, '..'));
            const fullPath = path.join(baseDir, path.normalize(capture.filepath).replace(/^([.][.][/\\])+/, ''));
            if (fs.existsSync(fullPath)) fs.unlinkSync(fullPath);
        }

        await pool.query('DELETE FROM camera_captures WHERE id = ?', [captureId]);

        res.json({ success: true });
    } catch (e) {
        console.error('[CameraController deleteCapture]', e.message);
        res.status(500).json({ success: false, error: 'Gagal menghapus foto' });
    }
}

module.exports = {
    pairCamera,
    unpairCamera,
    triggerCapture,
    uploadCapture,
    getCaptures,
    deleteCapture
};
