-- Migration: convert device_id from int to varchar (string ID like 'inc-312fb4')
-- Device 14 -> 'inc-312fb4'. Other devices get 'inc-' + 6-digit hex of the old numeric ID.
-- Run once on an existing `unimq` database.

USE `unimq`;

SET FOREIGN_KEY_CHECKS = 0;

-- 0. Drop existing foreign keys referencing device(device_id)
--    NOTE: adjust constraint names if they differ in your DB (check information_schema.KEY_COLUMN_USAGE).
ALTER TABLE `device_user` DROP FOREIGN KEY `device_user_ibfk_1`;
ALTER TABLE `device_logs` DROP FOREIGN KEY `device_logs_ibfk_1`;
ALTER TABLE `camera_captures` DROP FOREIGN KEY `camera_captures_ibfk_1`;
ALTER TABLE `camera_captures` DROP FOREIGN KEY `fk_camera_device`;

-- Helper mapping table (old numeric ID -> new string ID)
CREATE TABLE IF NOT EXISTS `device_id_map` (
  `old_id` int(10) NOT NULL,
  `new_id` varchar(32) NOT NULL,
  PRIMARY KEY (`old_id`)
) ENGINE=InnoDB;

-- 1. Convert `device`
ALTER TABLE `device` CHANGE `device_id` `device_id_old` int(10) NOT NULL;
ALTER TABLE `device` ADD COLUMN `device_id` varchar(32) NOT NULL AFTER `device_id_old`;
UPDATE `device` SET `device_id` = CONCAT('inc-', LPAD(HEX(`device_id_old`), 6, '0'));
UPDATE `device` SET `device_id` = 'inc-312fb4' WHERE `device_id_old` = 14;
INSERT INTO `device_id_map` (`old_id`, `new_id`) SELECT `device_id_old`, `device_id` FROM `device`;
ALTER TABLE `device` DROP PRIMARY KEY, DROP COLUMN `device_id_old`, ADD PRIMARY KEY (`device_id`);

-- 2. Convert `device_user`
ALTER TABLE `device_user` CHANGE `device_id` `device_id_old` int(10) NOT NULL;
ALTER TABLE `device_user` ADD COLUMN `device_id` varchar(32) NOT NULL AFTER `device_id_old`;
UPDATE `device_user` du JOIN `device_id_map` m ON du.`device_id_old` = m.`old_id` SET du.`device_id` = m.`new_id`;
ALTER TABLE `device_user` DROP PRIMARY KEY, DROP COLUMN `device_id_old`, ADD PRIMARY KEY (`device_id`, `user_id`);

-- 3. Convert `device_logs`
--    NOTE: if FK fails with collation mismatch, run first:
--    ALTER TABLE `device_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `device_logs` DROP INDEX `device_logs_ibfk_1`;
ALTER TABLE `device_logs` CHANGE `device_id` `device_id_old` int(10) NOT NULL;
ALTER TABLE `device_logs` ADD COLUMN `device_id` varchar(32) NOT NULL AFTER `device_id_old`;
UPDATE `device_logs` dl JOIN `device_id_map` m ON dl.`device_id_old` = m.`old_id` SET dl.`device_id` = m.`new_id`;
ALTER TABLE `device_logs` DROP COLUMN `device_id_old`, ADD INDEX `device_id` (`device_id`);

-- 4. Convert `camera_captures` (skip/remove this section if the table does not exist yet)
ALTER TABLE `camera_captures` CHANGE `device_id` `device_id_old` int(10) NOT NULL;
ALTER TABLE `camera_captures` CHANGE `incubator_id` `incubator_id` varchar(32) DEFAULT NULL;
ALTER TABLE `camera_captures` ADD COLUMN `device_id` varchar(32) NOT NULL AFTER `device_id_old`;
UPDATE `camera_captures` cc JOIN `device_id_map` m ON cc.`device_id_old` = m.`old_id` SET cc.`device_id` = m.`new_id`;
UPDATE `camera_captures` cc JOIN `device_id_map` m ON cc.`incubator_id` = m.`old_id` SET cc.`incubator_id` = m.`new_id`;
ALTER TABLE `camera_captures` DROP INDEX `device_id`, DROP COLUMN `device_id_old`, ADD INDEX `device_id` (`device_id`);

-- 5. Re-create foreign keys referencing device(device_id)
--    NOTE: adjust constraint names if they differ in your DB.
ALTER TABLE `device_user`
  ADD CONSTRAINT `device_user_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE;

ALTER TABLE `device_logs`
  ADD CONSTRAINT `device_logs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE;

ALTER TABLE `camera_captures`
  ADD CONSTRAINT `camera_captures_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `device` (`device_id`) ON DELETE CASCADE;

-- 6. Convert linked_incubator_id column on device (if exists)
ALTER TABLE `device` MODIFY `linked_incubator_id` varchar(32) DEFAULT NULL;

DROP TABLE IF EXISTS `device_id_map`;

SET FOREIGN_KEY_CHECKS = 1;
