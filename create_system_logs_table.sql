-- Create system_logs table for audit logging
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `actor_name` VARCHAR(120) NOT NULL,
  `actor_role` ENUM('admin','librarian','student') NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_action` (`action`),
  INDEX `idx_actor_role` (`actor_role`),
  INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






