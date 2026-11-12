const path = require('path');
const fs = require('fs');
const pool = require('../src/config/db');
const deviceManager = require('../src/services/DeviceManager');

class CameraController {
  /**
   * Endpoint upload gambar dari ESP32-S3 CAM
   * POST /api/devices/:id/camera/upload atau POST /api/camera/:mac/upload
   * Menerima raw binary JPEG / PNG atau JSON base64
   */
  async uploadCapture(req, res) {
    try {
      let deviceId = req.params.id;
      const camMacParam = req.params.mac || req.query.cam_mac || req.query.cam_id || req.headers['x-camera-mac'] || req.headers['x-camera-id'];
      
      // Jika identifier berupa string MAC (misal "cam-xxx" atau "e8:6b:..."), cari device inkubatornya
      let device = null;
      if (deviceId) {
        const [deviceRows] = await pool.query('SELECT * FROM device WHERE device_id = ?', [deviceId]);
        if (deviceRows.length > 0) {
          device = deviceRows[0];
        }
      }

      // Jika tidak ketemu berdasarkan integer ID tapi ada camMacParam, cari berdasarkan camera_mac
      if (!device && camMacParam) {
        const [macRows] = await pool.query('SELECT * FROM device WHERE LOWER(camera_mac) = LOWER(?)', [camMacParam.trim()]);
        if (macRows.length > 0) {
          device = macRows[0];
          deviceId = device.device_id;
        }
      }

      if (!device) {
        return res.status(404).json({ error: 'Device inkubator tidak ditemukan untuk kamera ini' });
      }

      let imageBuffer = null;

      // Cek format request
      if (Buffer.isBuffer(req.body) && req.body.length > 0) {
        // Raw binary body (image/jpeg, application/octet-stream)
        imageBuffer = req.body;
      } else if (req.body && req.body.image) {
        // Base64 encoded string
        const base64Data = req.body.image.replace(/^data:image\/\w+;base64,/, '');
        imageBuffer = Buffer.from(base64Data, 'base64');
      } else if (req.rawBody && Buffer.isBuffer(req.rawBody)) {
        imageBuffer = req.rawBody;
      }

      if (!imageBuffer || imageBuffer.length === 0) {
        return res.status(400).json({ error: 'No image data provided' });
      }

      // Pastikan direktori penyimpanan tersedia
      const uploadsDir = path.join(__dirname, '../public/uploads/captures');
      if (!fs.existsSync(uploadsDir)) {
        fs.mkdirSync(uploadsDir, { recursive: true });
      }

      const timestamp = Date.now();
      const filename = `cam_${deviceId}_${timestamp}.jpg`;
      const diskPath = path.join(uploadsDir, filename);
      const publicPath = `/uploads/captures/${filename}`;

      // Tulis file ke disk
      await fs.promises.writeFile(diskPath, imageBuffer);

      // Tentukan apakah ada inkubator terkait
      let incubatorId = device.linked_incubator_id || device.device_id;
      if (req.query.incubator_id) {
        incubatorId = req.query.incubator_id;
      }

      const triggerSource = req.headers['x-trigger'] || req.query.trigger || 'esp32-auto';
      const fileSize = imageBuffer.length;

      // Simpan metadata ke database
      const [insertResult] = await pool.query(
        `INSERT INTO camera_captures 
         (device_id, incubator_id, filename, filepath, filesize, trigger_source, captured_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())`,
        [deviceId, incubatorId, filename, publicPath, fileSize, triggerSource]
      );

      const captureRecord = {
        id: insertResult.insertId,
        device_id: deviceId,
        incubator_id: incubatorId,
        filename,
        filepath: publicPath,
        filesize: fileSize,
        trigger_source: triggerSource,
        captured_at: new Date().toISOString()
      };

      console.log(`[Camera] New capture saved for Incubator ${deviceId} (MAC: ${device.camera_mac || 'none'}): ${filename} (${(fileSize / 1024).toFixed(1)} KB)`);

      // Broadcast update ke client WebSocket secara realtime
      const io = req.app.get('io');
      if (io) {
        // Emit ke room device inkubator
        io.to(`device:${deviceId}`).emit('camera-photo-received', captureRecord);
        io.to(`device:${deviceId}`).emit('camera-status', { status: 'idle', message: 'Foto berhasil disimpan' });

        if (incubatorId && String(incubatorId) !== String(deviceId)) {
          io.to(`device:${incubatorId}`).emit('camera-photo-received', captureRecord);
        }
      }

      return res.status(201).json({
        status: 'success',
        message: 'Photo uploaded successfully',
        data: captureRecord
      });
    } catch (err) {
      console.error('[Camera Upload Error]:', err);
      return res.status(500).json({ error: 'Failed to upload photo: ' + err.message });
    }
  }

  /**
   * Sandingkan Kamera (Pair Camera) ke Inkubator via MAC Address
   * POST /api/devices/:id/camera/pair
   */
  async pairCamera(req, res) {
    try {
      const deviceId = req.params.id;
      const { camera_mac } = req.body;
      const userId = req.session ? req.session.user_id : (req.user ? req.user.user_id : null);

      if (!camera_mac || typeof camera_mac !== 'string' || camera_mac.trim().length === 0) {
        return res.status(400).json({ error: 'ID Kamera / MAC Address wajib diisi' });
      }

      const cleanMac = camera_mac.trim().toLowerCase();

      // Cek hak akses jika ada session user
      if (userId) {
        const [access] = await pool.query(
          "SELECT role FROM device_user WHERE device_id = ? AND user_id = ? AND role IN ('owner', 'operator')",
          [deviceId, userId]
        );
        if (access.length === 0) {
          return res.status(403).json({ error: 'Hanya owner atau operator yang bisa menyandingkan kamera' });
        }
      }

      const [deviceRows] = await pool.query('SELECT * FROM device WHERE device_id = ?', [deviceId]);
      if (deviceRows.length === 0) {
        return res.status(404).json({ error: 'Device inkubator tidak ditemukan' });
      }

      // Update camera_mac di device
      await pool.query('UPDATE device SET camera_mac = ? WHERE device_id = ?', [cleanMac, deviceId]);

      // Sinkronkan device di MQTT DeviceManager agar langganan topik camera/<mac>/data aktif
      deviceManager.syncDevices();

      // Kirim event pairing via MQTT ke topik unik kamera agar disimpan ke Flash ESP32
      const protocol = req.protocol || 'http';
      const hostHeader = req.get('host') || '10.10.10.30:8180';
      const serverUrl = hostHeader.includes('localhost') ? 'http://10.10.10.30:8180' : `${protocol}://${hostHeader}`;

      const pairPayload = JSON.stringify({
        action: 'pair',
        incubator_id: deviceId,
        server_url: serverUrl,
        timestamp: Date.now()
      });
      deviceManager.publish(deviceId, pairPayload, `camera/${cleanMac}/con`);

      console.log(`[Camera Pairing] Incubator ${deviceId} paired with Camera MAC: ${cleanMac}`);

      // Broadcast update ke Socket.IO
      const io = req.app.get('io');
      if (io) {
        io.to(`device:${deviceId}`).emit('camera-paired', {
          deviceId,
          camera_mac: cleanMac
        });
      }

      return res.json({
        success: true,
        message: `Kamera (${cleanMac}) berhasil disandingkan dengan inkubator!`,
        camera_mac: cleanMac
      });
    } catch (err) {
      console.error('[Pair Camera Error]:', err);
      return res.status(500).json({ error: 'Gagal menyandingkan kamera: ' + err.message });
    }
  }

  /**
   * Lepas Sandingan Kamera (Unpair Camera)
   * DELETE /api/devices/:id/camera/unpair
   */
  async unpairCamera(req, res) {
    try {
      const deviceId = req.params.id;
      const userId = req.session ? req.session.user_id : (req.user ? req.user.user_id : null);

      // Cek hak akses jika ada session user
      if (userId) {
        const [access] = await pool.query(
          "SELECT role FROM device_user WHERE device_id = ? AND user_id = ? AND role IN ('owner', 'operator')",
          [deviceId, userId]
        );
        if (access.length === 0) {
          return res.status(403).json({ error: 'Hanya owner atau operator yang bisa melepas kamera' });
        }
      }

      const [deviceRows] = await pool.query('SELECT * FROM device WHERE device_id = ?', [deviceId]);
      if (deviceRows.length === 0) {
        return res.status(404).json({ error: 'Device inkubator tidak ditemukan' });
      }

      const prevMac = deviceRows[0].camera_mac;
      await pool.query('UPDATE device SET camera_mac = NULL WHERE device_id = ?', [deviceId]);

      deviceManager.syncDevices();

      // Beritahu modul kamera bahwa sandingan dilepas
      if (prevMac) {
        const unpairPayload = JSON.stringify({
          action: 'unpair',
          incubator_id: 0,
          timestamp: Date.now()
        });
        deviceManager.publish(deviceId, unpairPayload, `camera/${prevMac}/con`);
      }

      console.log(`[Camera Unpairing] Incubator ${deviceId} unpaired from Camera MAC: ${prevMac}`);

      const io = req.app.get('io');
      if (io) {
        io.to(`device:${deviceId}`).emit('camera-unpaired', {
          deviceId
        });
      }

      return res.json({
        success: true,
        message: 'Kamera berhasil dilepas dari inkubator'
      });
    } catch (err) {
      console.error('[Unpair Camera Error]:', err);
      return res.status(500).json({ error: 'Gagal melepas kamera: ' + err.message });
    }
  }

  /**
   * Trigger capture dari dashboard (Web / API)
   * POST /api/devices/:id/camera/capture
   */
  async triggerCapture(req, res) {
    try {
      const deviceId = req.params.id;
      const userId = req.session ? req.session.user_id : (req.user ? req.user.user_id : null);

      // Verifikasi akses user ke device
      if (userId) {
        const [access] = await pool.query(
          'SELECT role FROM device_user WHERE device_id = ? AND user_id = ?',
          [deviceId, userId]
        );
        if (access.length === 0) {
          return res.status(403).json({ error: 'Access denied' });
        }
      }

      const [deviceRows] = await pool.query('SELECT * FROM device WHERE device_id = ?', [deviceId]);
      if (deviceRows.length === 0) {
        return res.status(404).json({ error: 'Device not found' });
      }

      const device = deviceRows[0];

      const protocol = req.protocol || 'http';
      const hostHeader = req.get('host') || '10.10.10.30:8180';
      // Jika diakses via localhost di laptop, berikan IP LAN laptop agar bisa dijangkau ESP32
      const serverUrl = hostHeader.includes('localhost') ? 'http://10.10.10.30:8180' : `${protocol}://${hostHeader}`;

      // Publish perintah capture ke ESP32-S3 CAM via MQTT
      const commandPayload = {
        action: 'capture',
        incubator_id: deviceId,
        camera_mac: device.camera_mac || null,
        server_url: serverUrl,
        upload_url: `${serverUrl}/api/devices/${deviceId}/camera/upload?cam_mac=${device.camera_mac || ''}`,
        timestamp: Date.now(),
        quality: req.body.quality || 10
      };

      const payloadStr = JSON.stringify(commandPayload);

      // 1. Publish ke topik inkubator standar: incubator/<id>/con
      const published = deviceManager.publish(deviceId, payloadStr);

      // 2. Jika inkubator tersanding dengan camera_mac, publish juga langsung ke camera/<camera_mac>/con
      if (device.camera_mac) {
        const camTopic = `camera/${device.camera_mac}/con`;
        deviceManager.publish(deviceId, payloadStr, camTopic);
        console.log(`[Trigger Capture] Sent command to Camera MAC topic [${camTopic}]`);
      }

      // Emit status ke Socket.IO room
      const io = req.app.get('io');
      if (io) {
        io.to(`device:${deviceId}`).emit('camera-status', { 
          status: 'capturing', 
          message: 'Meminta ESP32-S3 mengambil foto...' 
        });

        if (device.linked_incubator_id) {
          io.to(`device:${device.linked_incubator_id}`).emit('camera-status', {
            status: 'capturing',
            message: 'Meminta ESP32-S3 mengambil foto...'
          });
        }
      }

      return res.json({
        success: true,
        message: published
          ? 'Capture trigger sent to camera'
          : 'Perintah capture dikirim, TAPI MQTT broker belum terhubung untuk device ini. Cek koneksi broker di server.',
        published,
        mqtt_connected: !!published,
        camera_mac: device.camera_mac || null,
        timestamp: commandPayload.timestamp
      });
    } catch (err) {
      console.error('[Trigger Capture Error]:', err);
      return res.status(500).json({ error: 'Failed to trigger capture: ' + err.message });
    }
  }

  /**
   * Ambil riwayat foto untuk suatu device
   * GET /api/devices/:id/camera/captures
   */
  async getCaptures(req, res) {
    try {
      const deviceId = req.params.id;
      const limit = parseInt(req.query.limit) || 30;

      const [rows] = await pool.query(
        'SELECT * FROM camera_captures WHERE device_id = ? OR incubator_id = ? ORDER BY captured_at DESC LIMIT ?',
        [deviceId, deviceId, limit]
      );

      return res.json({ success: true, captures: rows });
    } catch (err) {
      console.error('[Get Captures Error]:', err);
      return res.status(500).json({ error: 'Failed to fetch captures' });
    }
  }

  /**
   * Hapus foto capture
   * DELETE /api/devices/:id/camera/captures/:captureId
   */
  async deleteCapture(req, res) {
    try {
      const { id: deviceId, captureId } = req.params;
      const userId = req.session ? req.session.user_id : (req.user ? req.user.user_id : null);

      if (userId) {
        const [access] = await pool.query(
          "SELECT 1 FROM device_user WHERE device_id = ? AND user_id = ? AND role = 'owner'",
          [deviceId, userId]
        );
        if (access.length === 0) {
          return res.status(403).json({ error: 'Hanya owner yang bisa menghapus foto' });
        }
      }

      const [rows] = await pool.query('SELECT * FROM camera_captures WHERE id = ?', [captureId]);
      if (rows.length === 0) {
        return res.status(404).json({ error: 'Photo not found' });
      }

      const capture = rows[0];

      // Hapus file di disk jika ada
      const diskPath = path.join(__dirname, '../public', capture.filepath);
      if (fs.existsSync(diskPath)) {
        try {
          fs.unlinkSync(diskPath);
        } catch (e) {
          console.warn('[Delete Capture File Warning]:', e.message);
        }
      }

      // Hapus dari database
      await pool.query('DELETE FROM camera_captures WHERE id = ?', [captureId]);

      const io = req.app.get('io');
      if (io) {
        io.to(`device:${deviceId}`).emit('camera-photo-deleted', { captureId });
      }

      return res.json({ success: true, message: 'Photo deleted successfully' });
    } catch (err) {
      console.error('[Delete Capture Error]:', err);
      return res.status(500).json({ error: 'Failed to delete photo: ' + err.message });
    }
  }
}

module.exports = new CameraController();
