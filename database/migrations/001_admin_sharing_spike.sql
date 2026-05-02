-- 1. Update user table
ALTER TABLE `user` ADD COLUMN `role` ENUM('admin', 'user') DEFAULT 'user' AFTER `password`;

-- 2. Update device table
ALTER TABLE `device` ADD COLUMN `last_logged_values` JSON NULL AFTER `broker_port`;

-- 3. Update device_logs table
ALTER TABLE `device_logs` ADD COLUMN `log_type` ENUM('aggregation', 'change_event') DEFAULT 'aggregation' AFTER `data`;

-- 4. Create device_access_tokens table
CREATE TABLE `device_access_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(10) NOT NULL,
  `token_code` varchar(50) NOT NULL,
  `created_by` int(10) NOT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `current_uses` int(11) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token_code` (`token_code`),
  KEY `device_id` (`device_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `device_access_tokens_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE,
  CONSTRAINT `device_access_tokens_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create user_device_access table
CREATE TABLE `user_device_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `device_id` int(10) NOT NULL,
  `access_type` ENUM('owner', 'viewer') NOT NULL,
  `redeemed_via_token_id` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_device` (`user_id`, `device_id`),
  KEY `user_id` (`user_id`),
  KEY `device_id` (`device_id`),
  KEY `redeemed_via_token_id` (`redeemed_via_token_id`),
  CONSTRAINT `user_device_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_device_access_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE,
  CONSTRAINT `user_device_access_ibfk_3` FOREIGN KEY (`redeemed_via_token_id`) REFERENCES `device_access_tokens` (`token_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create admin_audit_log table
CREATE TABLE `admin_audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `admin_audit_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Seed Admin Role
UPDATE `user` SET `role` = 'admin' WHERE `user_name` = 'rusdingawi';
