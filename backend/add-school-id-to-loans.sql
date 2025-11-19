-- Add school_id column to loans table for better filtering performance
-- This migration adds school_id based on the student's school_id

-- Step 1: Add the column (nullable first)
ALTER TABLE `loans` 
ADD COLUMN `school_id` INT(11) NULL AFTER `book_id`,
ADD INDEX `idx_school_id` (`school_id`);

-- Step 2: Populate existing loans with school_id from students
UPDATE `loans` l
INNER JOIN `users` u ON u.id = l.student_id
SET l.school_id = u.school_id
WHERE l.school_id IS NULL;

-- Step 3: Make it NOT NULL (optional, but recommended for data integrity)
-- ALTER TABLE `loans` MODIFY COLUMN `school_id` INT(11) NOT NULL;

-- Step 4: Add foreign key constraint (optional)
-- ALTER TABLE `loans` 
-- ADD CONSTRAINT `fk_loans_school` FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE RESTRICT;









