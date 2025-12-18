-- Database schema for borrow requests and loans
-- Run this SQL to create the necessary tables

-- Borrow Requests table
CREATE TABLE IF NOT EXISTS `borrow_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `book_id` INT(11) NOT NULL,
  `duration_days` INT(11) NOT NULL DEFAULT 7,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `rejected_at` DATETIME NULL DEFAULT NULL,
  `approved_by` INT(11) NULL DEFAULT NULL,
  `rejected_by` INT(11) NULL DEFAULT NULL,
  `rejection_reason` TEXT NULL DEFAULT NULL,
  `librarian_note` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_book` (`book_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_requested_at` (`requested_at`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`rejected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loans table (active and returned loans)
CREATE TABLE IF NOT EXISTS `loans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) NULL DEFAULT NULL,
  `student_id` INT(11) NOT NULL,
  `book_id` INT(11) NOT NULL,
  `borrowed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_at` DATE NOT NULL,
  `returned_at` DATETIME NULL DEFAULT NULL,
  `extended_due_at` DATE NULL DEFAULT NULL,
  `status` ENUM('borrowed', 'returned') NOT NULL DEFAULT 'borrowed',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_book` (`book_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_due_at` (`due_at`),
  INDEX `idx_borrowed_at` (`borrowed_at`),
  INDEX `idx_request` (`request_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`request_id`) REFERENCES `borrow_requests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





















