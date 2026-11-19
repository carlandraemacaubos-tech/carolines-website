-- ============================================================
-- Migration 002: availability and calendar management
-- Run ONCE on an existing database (phpMyAdmin > select "party_booking" > Import).
-- New installs do not need this file: database/schema.sql already includes it.
-- Existing bookings, administrators and logs are NOT touched.
-- ============================================================

USE `party_booking`;

-- ------------------------------------------------------------
-- Weekly opening hours. weekday: 0 = Sunday ... 6 = Saturday.
-- A weekday may have several ranges (e.g. 08:00-12:00 and 13:00-20:00).
-- A weekday with NO rows is closed every week.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `weekly_hours` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `weekday`    TINYINT UNSIGNED NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time`   TIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_weekly_weekday` (`weekday`),
  CONSTRAINT `chk_weekly_range` CHECK (`end_time` > `start_time` AND `weekday` <= 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Changes for one specific date, overriding the weekly hours:
--   mode 'closed' -> the whole date is unavailable (manually blocked)
--   mode 'custom' -> only the listed time ranges are available that date
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `availability_overrides` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `date_value`          DATE NOT NULL,
  `mode`                ENUM('closed','custom') NOT NULL,
  `start_time`          TIME NULL,                -- only for 'custom'
  `end_time`            TIME NULL,                -- only for 'custom'
  `note`                VARCHAR(255) NULL,        -- private note, e.g. "Family trip"
  `created_by_admin_id` INT UNSIGNED NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_override_date` (`date_value`),
  CONSTRAINT `fk_override_admin` FOREIGN KEY (`created_by_admin_id`)
    REFERENCES `administrators` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Simple key/value settings (slot_minutes = length of the booking time steps)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(50)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('slot_minutes', '30');

-- ------------------------------------------------------------
-- STARTING weekly hours (only inserted when the table is empty):
-- every day 8:00 AM - 8:00 PM. Change them any time in Admin > Availability.
-- ------------------------------------------------------------
INSERT INTO `weekly_hours` (`weekday`, `start_time`, `end_time`)
SELECT d.n, '08:00:00', '20:00:00'
  FROM (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
        UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) AS d
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM `weekly_hours` LIMIT 1) AS existing);
