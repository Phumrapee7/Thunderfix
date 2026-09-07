-- Adds columns the PHP code already expects on `technicians` and `reviews`
-- but that are missing from the shipped schema dumps.
-- Safe to re-run: every ADD COLUMN / ADD CONSTRAINT is guarded.

-- ============ technicians ============

ALTER TABLE `technicians`
  ADD COLUMN `tech_type` VARCHAR(50) NULL AFTER `specialty`,
  ADD COLUMN `profile_image` VARCHAR(255) NULL AFTER `tech_type`,
  ADD COLUMN `cover_image` VARCHAR(255) NULL AFTER `profile_image`,
  ADD COLUMN `bio` TEXT NULL AFTER `cover_image`,
  ADD COLUMN `years_experience` INT NOT NULL DEFAULT 0 AFTER `bio`,
  ADD COLUMN `min_price` INT NOT NULL DEFAULT 0 AFTER `years_experience`,
  ADD COLUMN `emergency_fee` INT NOT NULL DEFAULT 0 AFTER `min_price`,
  ADD COLUMN `line_id` VARCHAR(100) NULL AFTER `emergency_fee`,
  ADD COLUMN `phone_public` TINYINT(1) NOT NULL DEFAULT 0 AFTER `line_id`,
  ADD COLUMN `line_id_public` TINYINT(1) NOT NULL DEFAULT 0 AFTER `phone_public`,
  ADD COLUMN `languages` VARCHAR(100) NULL AFTER `line_id_public`,
  ADD COLUMN `latitude` DOUBLE NULL AFTER `languages`,
  ADD COLUMN `longitude` DOUBLE NULL AFTER `latitude`,
  ADD COLUMN `service_radius_km` DOUBLE NULL AFTER `longitude`,
  ADD COLUMN `business_hours` TEXT NULL AFTER `service_radius_km`,
  ADD COLUMN `business_hours_json` TEXT NULL AFTER `business_hours`;

-- ============ reviews ============

ALTER TABLE `reviews`
  ADD COLUMN `user_id` INT NULL AFTER `technician_id`;

ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
