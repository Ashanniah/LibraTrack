-- Add school_id column to books table for school-based isolation
-- Run this SQL script in your MySQL database (via phpMyAdmin or MySQL command line)

-- Step 1: Check if column already exists (optional - safe to run even if exists)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'books' 
  AND COLUMN_NAME = 'school_id';

-- Step 2: Add the column if it doesn't exist
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `books` ADD COLUMN `school_id` INT(11) NULL AFTER `category`, ADD INDEX `idx_school_id` (`school_id`)',
    'SELECT "Column school_id already exists in books table" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Verify the column was added
SHOW COLUMNS FROM `books` LIKE 'school_id';

-- Optional: If you want to assign existing books to a specific school (e.g., school_id = 1)
-- Uncomment the line below and change the school_id as needed:
-- UPDATE `books` SET `school_id` = 1 WHERE `school_id` IS NULL;

-- Note: If you want to keep existing books as global (visible to all), 
-- leave school_id as NULL for those books.

