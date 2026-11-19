-- ============================================================
-- Caroline's Party Helper - database schema
-- Import in phpMyAdmin: open phpMyAdmin > "Import" tab > choose this file > Go.
-- (It creates the database "party_booking" for you.)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `party_booking`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `party_booking`;

-- ------------------------------------------------------------
-- Administrators (people who may log in to /admin)
-- Create your account with:  php scripts/create-admin.php
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `administrators` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,           -- created with password_hash()
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Bookings
-- booking_status: pending_email -> pending_review -> approved / rejected
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_reference`        VARCHAR(20)  NOT NULL,
  `customer_name`            VARCHAR(100) NOT NULL,
  `email`                    VARCHAR(190) NOT NULL,
  `phone`                    VARCHAR(30)  NOT NULL,
  `event_type`               VARCHAR(60)  NOT NULL,
  `event_location`           VARCHAR(255) NOT NULL,
  `guests`                   INT UNSIGNED NULL,
  `help_needed`              VARCHAR(255) NULL,     -- e.g. "Setup, Serving"
  `start_datetime`           DATETIME     NOT NULL,
  `end_datetime`             DATETIME     NOT NULL,
  `message`                  TEXT         NULL,
  `verification_token_hash`  CHAR(64)     NULL,     -- SHA-256 of the emailed token (never the token itself)
  `verification_expires_at`  DATETIME     NULL,
  `email_verified`           TINYINT(1)   NOT NULL DEFAULT 0,
  `verified_at`              DATETIME     NULL,
  `booking_status`           ENUM('pending_email','pending_review','approved','rejected')
                                          NOT NULL DEFAULT 'pending_email',
  `admin_notes`              TEXT         NULL,     -- PRIVATE: never shown to customers
  `rejection_reason`         TEXT         NULL,     -- optional, shown to the customer in the rejection email
  `reviewed_by_admin_id`     INT UNSIGNED NULL,
  `reviewed_at`              DATETIME     NULL,
  `created_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_reference` (`booking_reference`),
  UNIQUE KEY `uq_verification_token` (`verification_token_hash`),
  KEY `idx_bookings_email` (`email`),
  KEY `idx_bookings_status` (`booking_status`),
  KEY `idx_bookings_schedule` (`start_datetime`, `end_datetime`),
  KEY `idx_bookings_created` (`created_at`),
  CONSTRAINT `fk_bookings_admin` FOREIGN KEY (`reviewed_by_admin_id`)
    REFERENCES `administrators` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_booking_dates` CHECK (`end_datetime` > `start_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Booking history (every important action)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booking_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `admin_id`   INT UNSIGNED NULL,                   -- NULL = action by the customer/system
  `action`     VARCHAR(40)  NOT NULL,               -- submitted, verified, approved, rejected, note_updated ...
  `details`    VARCHAR(500) NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_booking` (`booking_id`, `created_at`),
  CONSTRAINT `fk_logs_booking` FOREIGN KEY (`booking_id`)
    REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `administrators` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Email log (only "sent" when PHPMailer confirmed it)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NULL,
  `recipient`  VARCHAR(190) NOT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `type`       VARCHAR(30)  NOT NULL,               -- verification, admin_notice, approved, rejected, inquiry
  `status`     ENUM('sent','failed') NOT NULL,
  `error`      VARCHAR(500) NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_booking` (`booking_id`, `created_at`),
  CONSTRAINT `fk_email_booking` FOREIGN KEY (`booking_id`)
    REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Rate limiting (stores a hash of the visitor's IP, never the raw IP)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`          VARCHAR(40) NOT NULL,           -- e.g. booking_submit, admin_login
  `identifier_hash` CHAR(64)    NOT NULL,
  `created_at`      DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate_lookup` (`bucket`, `identifier_hash`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Availability and calendar management (same as migration-002-availability.sql)
-- ============================================================
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
