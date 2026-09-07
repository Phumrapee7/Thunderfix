-- Secure "forgot password" flow: request -> emailed (logged) token -> reset.
-- A separate table instead of columns on users/technicians because
-- forgot_password.php looks accounts up across BOTH tables, and this way
-- neither table needs touching, "used" is just `used_at IS NULL`, and old
-- requests naturally keep a history instead of being overwritten.

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `account_type` ENUM('user','technician') NOT NULL,
  `account_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_account` (`account_type`, `account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
