-- Remove unique constraint from ISBN column to allow same ISBN for different users
-- This allows different librarians to add books with the same ISBN
-- Duplicate check is now handled at application level (per user)

-- Step 1: Check and remove unique constraint/index on ISBN
-- MySQL
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'books' 
  AND INDEX_NAME = 'books_isbn_unique';

SET @sql = IF(@index_exists > 0,
    'ALTER TABLE `books` DROP INDEX `books_isbn_unique`',
    'SELECT "ISBN unique index does not exist" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add regular (non-unique) index on ISBN for performance
SET @regular_index_exists = 0;
SELECT COUNT(*) INTO @regular_index_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'books' 
  AND INDEX_NAME = 'books_isbn_index';

SET @sql2 = IF(@regular_index_exists = 0,
    'ALTER TABLE `books` ADD INDEX `books_isbn_index` (`isbn`)',
    'SELECT "ISBN regular index already exists" AS message');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Step 3: Verify
SHOW INDEX FROM `books` WHERE Column_name = 'isbn';

-- Note: After this change:
-- - Different users can add books with the same ISBN
-- - Application-level validation prevents same user from adding duplicate ISBN
-- - ISBN index remains for query performance (but not unique)


