-- Migration: ESP32-S3 Camera Support for Incubator System
-- Menambahkan tabel camera_captures dan kolom linked_incubator_id pada tabel device

-- 1. Tambah kolom linked_incubator_id pada device (jika belum ada)
-- Kolom ini digunakan jika ESP32-CAM dipasang khusus untuk memantau satu inkubator tertentu
ALTER TABLE `device` 
ADD COLUMN IF NOT EXISTS `linked_incubator_id` int(10) DEFAULT NULL AFTER `broker_port`;

-- 2. Buat tabel camera_captures
CREATE TABLE IF NOT EXISTS `camera_captures` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `device_id` int(10) NOT NULL,
  `incubator_id` int(10) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `filesize` int(11) DEFAULT 0,
  `trigger_source` varchar(50) DEFAULT 'dashboard',
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `incubator_id` (`incubator_id`),
  CONSTRAINT `fk_camera_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
