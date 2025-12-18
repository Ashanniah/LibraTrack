-- Add added_by column to books table for librarian-level isolation
-- Run this SQL in phpMyAdmin

ALTER TABLE `books` 
ADD COLUMN `added_by` INT(11) NULL AFTER `category`,
ADD INDEX `idx_added_by` (`added_by`);

-- Verify it was added
SHOW COLUMNS FROM `books` LIKE 'added_by';


