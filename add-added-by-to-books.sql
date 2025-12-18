-- Add added_by column to books table for librarian-level isolation
-- Run this SQL script in your MySQL database (via phpMyAdmin or MySQL command line)

-- Step 1: Check if column already exists (optional - safe to run even if exists)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'books' 
  AND COLUMN_NAME = 'added_by';

-- Step 2: Add the column if it doesn't exist
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `books` ADD COLUMN `added_by` INT(11) NULL AFTER `school_id`, ADD INDEX `idx_added_by` (`added_by`)',
    'SELECT "Column added_by already exists in books table" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Verify the column was added
SHOW COLUMNS FROM `books` LIKE 'added_by';

-- Note: After adding the column, existing books will have NULL added_by.
-- New books added by librarians will automatically get their user ID in added_by.


