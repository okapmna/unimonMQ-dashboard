const express = require('express');
const router = express.Router();
const AuthController = require('../controllers/AuthController');
const DeviceController = require('../controllers/DeviceController');
const CameraController = require('../controllers/CameraController');
const { authenticateToken } = require('../middleware/auth');
const pool = require('../src/config/db');

// Public API
router.post('/login', AuthController.apiLogin);

// Camera Upload API from ESP32-S3 (Direct device endpoint or MAC endpoint)
router.post('/devices/:id/camera/upload', (req, res) => CameraController.uploadCapture(req, res));
router.post('/camera/:mac/upload', (req, res) => CameraController.uploadCapture(req, res));

// Camera Captures list (Public/Embeddable or protected)
router.get('/devices/:id/camera/captures', (req, res) => CameraController.getCaptures(req, res));

// Protected Camera Trigger & Delete (Bearer Token or Web Session if present)
const optionalAuth = (req, res, next) => {
    if (req.headers.authorization) {
        return authenticateToken(req, res, next);
    }
    if (req.session && req.session.user_id) {
        return next();
    }
    return authenticateToken(req, res, next);
};

// Camera Pairing API
router.post('/devices/:id/camera/pair', optionalAuth, (req, res) => CameraController.pairCamera(req, res));
router.delete('/devices/:id/camera/unpair', optionalAuth, (req, res) => CameraController.unpairCamera(req, res));

router.post('/devices/:id/camera/capture', optionalAuth, (req, res) => CameraController.triggerCapture(req, res));
router.post('/devices/:id/camera/web-upload', optionalAuth, (req, res) => CameraController.webUploadCapture(req, res));
router.delete('/devices/:id/camera/captures/:captureId', optionalAuth, (req, res) => CameraController.deleteCapture(req, res));
router.post('/devices/:id/camera/captures/:captureId/reanalyze', optionalAuth, (req, res) => CameraController.reanalyzeCapture(req, res));

// Protected API
router.get('/devices', authenticateToken, DeviceController.apiGetDevices);

// API: Update Device Configuration
router.post('/devices/update', authenticateToken, async (req, res) => {
    const { device_id, device_name, device_type, broker_url, broker_port, mq_user, mq_pass } = req.body;
    try {
        const [ownerCheck] = await pool.query(
            "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
            [device_id, req.user.user_id]
        );
        if (ownerCheck.length === 0) return res.status(403).json({ error: 'Only owner can update device' });
        
        const [result] = await pool.query(
            "UPDATE device SET device_name=?, device_type=?, broker_url=?, broker_port=?, mq_user=?, mq_pass=? WHERE device_id=?",
            [device_name, device_type, broker_url, broker_port, mq_user, mq_pass, device_id]
        );
        if (result.affectedRows === 0) return res.status(404).json({ error: 'Device not found' });
        res.json({ message: 'Device updated' });
    } catch (e) {
        res.status(500).json({ error: 'Update failed' });
    }
});

// API: Get Logs for Charts (opsional filter ?date=YYYY-MM-DD)
router.get('/devices/:id/logs', optionalAuth, async (req, res) => {
    const device_id = req.params.id;
    const user_id = (req.user && req.user.user_id) || (req.session && req.session.user_id);
    try {
        const [accessCheck] = await pool.query(
            "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ?",
            [device_id, user_id]
        );
        if (accessCheck.length === 0) return res.status(403).json({ error: 'Access denied' });

        // Filter tanggal opsional: hanya log pada hari yang dipilih
        const dateFilter = (req.query.date && /^\d{4}-\d{2}-\d{2}$/.test(req.query.date)) ? req.query.date : null;
        let sql = "SELECT data, created_at FROM device_logs WHERE device_id = ? AND JSON_EXTRACT(data, '$.temp.avg') IS NOT NULL";
        const params = [device_id];
        if (dateFilter) {
            sql += " AND DATE(created_at) = ?";
            params.push(dateFilter);
        }
        sql += " ORDER BY created_at DESC LIMIT 200";

        const [logs] = await pool.query(sql, params);
        const processedLogs = logs.map(row => ({ created_at: row.created_at, data: JSON.parse(row.data) }));
        res.json(processedLogs);
    } catch (e) {
        res.status(500).json({ error: 'Fetch logs failed' });
    }
});

// API: Share/Unshare
router.post('/devices/:id/share', authenticateToken, DeviceController.apiShareDevice);
router.delete('/devices/:id/share/:userId', authenticateToken, DeviceController.apiUnshareDevice);

module.exports = router;
