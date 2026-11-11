const express = require('express');
const router = express.Router();
const AuthController = require('../controllers/AuthController');
const DeviceController = require('../controllers/DeviceController');
const CameraController = require('../controllers/CameraController');
const { checkWebAuth } = require('../middleware/auth');
const pool = require('../src/config/db');
const { matchDeviceConfig } = require('../src/device-types/registry');
const { loadDeviceViewData } = require('../src/device-types/loaders');

// Public Routes
router.get('/', AuthController.renderLogin);
router.get('/login', (req, res) => res.redirect('/'));
router.post('/login', AuthController.handleLogin);
router.get('/logout', AuthController.logout);

// Protected Routes (Web)
router.get('/dashboard', checkWebAuth, DeviceController.getDashboard);
router.post('/dashboard', checkWebAuth, DeviceController.handleDashboardPost);
router.post('/devices/:id/share', checkWebAuth, DeviceController.shareDevice);
router.delete('/devices/:id/share/:userId', checkWebAuth, DeviceController.unshareDevice);
router.get('/devices/:id/users', checkWebAuth, DeviceController.getDeviceUsers);
router.post('/devices/:id/camera/capture', checkWebAuth, (req, res) => CameraController.triggerCapture(req, res));
router.delete('/devices/:id/camera/captures/:captureId', checkWebAuth, (req, res) => CameraController.deleteCapture(req, res));
router.post('/devices/:id/camera/pair', checkWebAuth, (req, res) => CameraController.pairCamera(req, res));
router.delete('/devices/:id/camera/unpair', checkWebAuth, (req, res) => CameraController.unpairCamera(req, res));

// Dynamic Device Routing
async function renderDeviceView(req, res, viewName) {
    try {
        const device_id = req.params.id;
        const [rows] = await pool.query(`
            SELECT d.*, du.role
            FROM device d
            JOIN device_user du ON d.device_id = du.device_id
            JOIN user u ON du.user_id = u.user_id
            WHERE d.device_id = ? AND u.user_name = ?
        `, [device_id, req.session.username]);
        if (rows.length === 0) return res.redirect('/dashboard');

        const device_data = rows[0];
        const typeConfig = matchDeviceConfig(device_data.device_type);
        const viewConfig = typeConfig ? typeConfig.views[viewName] : null;
        if (!viewConfig) return res.redirect('/dashboard');

        const extraData = await loadDeviceViewData(device_data);

        res.render(viewConfig.template, {
            page_title: device_data.device_name + ' - Control',
            body_class: viewConfig.body_class,
            device_data,
            role: device_data.role,
            username: req.session.username,
            userId: req.session.user_id,
            ...extraData
        });
    } catch (e) {
        res.redirect('/dashboard');
    }
}

router.get('/device/:id', checkWebAuth, (req, res) => renderDeviceView(req, res, 'main'));
router.get('/device/:id/:view', checkWebAuth, (req, res) => renderDeviceView(req, res, req.params.view));

router.get('/profile', checkWebAuth, (req, res) => {
    res.render('profile', {
        page_title: 'User Profile - UNIMQ',
        body_class: 'bg-cream-bg text-dark-text min-h-screen font-sans selection:bg-accent-green selection:text-white pb-20',
        username: req.session.username,
        user_id: req.session.user_id
    });
});

module.exports = router;
