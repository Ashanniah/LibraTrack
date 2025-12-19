-- Create email_logs table for storing all email notifications
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `role` VARCHAR(20) NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body_preview` TEXT NULL,
  `status` ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
  `error_message` TEXT NULL,
  `related_entity_type` VARCHAR(50) NULL,
  `related_entity_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_role` (`role`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


