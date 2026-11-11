CREATE DATABASE IF NOT EXISTS `unimq`;
USE `unimq`;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Table structure for `user`
CREATE TABLE `user` (
  `user_id` int(10) NOT NULL,
  `user_name` varchar(20) NOT NULL,
  `password` varchar(225) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user` (`user_id`, `user_name`, `password`) VALUES
(11, 'rusdingawi', '$2y$10$mAlEsUu84Iio1NRM8RmRnuc/SWCe1Yy5aNimQ28JKrUKeYTHAmiL.');

-- Table structure for `device`
CREATE TABLE `device` (
  `device_id` int(10) NOT NULL,
  `broker_url` varchar(500) NOT NULL,
  `mq_pass` varchar(20) DEFAULT NULL,
  `mq_user` varchar(20) DEFAULT NULL,
  `device_type` varchar(50) NOT NULL,
  `device_name` varchar(50) NOT NULL,
  `broker_port` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `device` (`device_id`, `broker_url`, `mq_pass`, `mq_user`, `device_type`, `device_name`, `broker_port`) VALUES
(14, 'test.mosquitto.org', '', '', 'esp32-inkubator', 'Inkubator Ternak 1', '8080'),
(19, '000000000000000.s1.eu.hivemq.cloud', '54321JJJ', 'cihuyyy', 'esp32-inkubator', 'Inkubator burung', '8884'),
(20, '0000000000000000.s1.eu.hivemq.cloud', '54321JJJ', 'itsabirds', 'esp32-inkubator', 'Inkubator burung', '8884');

-- Table structure for `user_tokens`
CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(10) NOT NULL,
  `selector` char(12) NOT NULL,
  `hashed_validator` char(64) NOT NULL,
  `expiry` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Indexes and Constraints

-- Indexes for `user`
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

-- Indexes for `device`
ALTER TABLE `device`
  ADD PRIMARY KEY (`device_id`);

-- Indexes for `user_tokens`
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

-- AUTO_INCREMENT for `user`
ALTER TABLE `user`
  MODIFY `user_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

-- AUTO_INCREMENT for `device`
ALTER TABLE `device`
  MODIFY `device_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

-- AUTO_INCREMENT for `user_tokens`
ALTER TABLE `user_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Junction table for device-user many-to-many relationship
CREATE TABLE `device_user` (
  `device_id` int(10) NOT NULL,
  `user_id` int(10) NOT NULL,
  `role` enum('owner', 'viewer', 'operator') NOT NULL DEFAULT 'viewer',
  `shared_by` int(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`device_id`, `user_id`),
  FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`shared_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `device_user` (`device_id`, `user_id`, `role`, `shared_by`, `created_at`)
SELECT `device_id`, 11, 'owner', NULL, NOW() FROM `device`;

-- Foreign Key Relationship
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

-- Table structure for `device_logs`
CREATE TABLE `device_logs` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `device_id` int(10) NOT NULL,
  `data` json NOT NULL, 
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  CONSTRAINT `device_logs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `camera_captures`
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
  CONSTRAINT `camera_captures_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;