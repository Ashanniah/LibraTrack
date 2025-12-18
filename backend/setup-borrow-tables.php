<?php
// backend/setup-borrow-tables.php
// Run this file once to create the borrow_requests and loans tables
// Access via: http://localhost/libratrack/backend/setup-borrow-tables.php

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Setting up Borrow Requests and Loans Tables</h2>";
echo "<pre>";

try {
    // Create borrow_requests table
    $sql1 = "CREATE TABLE IF NOT EXISTS `borrow_requests` (
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
      INDEX `idx_requested_at` (`requested_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        echo "✓ Table 'borrow_requests' created successfully\n";
    } else {
        echo "✗ Error creating borrow_requests: " . $conn->error . "\n";
    }
    
    // Create loans table
    $sql2 = "CREATE TABLE IF NOT EXISTS `loans` (
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
      INDEX `idx_request` (`request_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql2)) {
        echo "✓ Table 'loans' created successfully\n";
    } else {
        echo "✗ Error creating loans: " . $conn->error . "\n";
    }
    
    // Try to add foreign keys (may fail if tables don't exist yet, that's okay)
    echo "\nAttempting to add foreign keys...\n";
    
    // Add foreign keys for borrow_requests (ignore errors if they already exist)
    $fk1 = "ALTER TABLE `borrow_requests` 
            ADD CONSTRAINT `fk_br_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE";
    $fk2 = "ALTER TABLE `borrow_requests` 
            ADD CONSTRAINT `fk_br_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE";
    $fk3 = "ALTER TABLE `borrow_requests` 
            ADD CONSTRAINT `fk_br_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
    $fk4 = "ALTER TABLE `borrow_requests` 
            ADD CONSTRAINT `fk_br_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
    
    foreach ([$fk1, $fk2, $fk3, $fk4] as $fk) {
        @$conn->query($fk); // @ suppresses errors if FK already exists
    }
    
    // Add foreign keys for loans
    $fk5 = "ALTER TABLE `loans` 
            ADD CONSTRAINT `fk_loans_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE";
    $fk6 = "ALTER TABLE `loans` 
            ADD CONSTRAINT `fk_loans_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE";
    $fk7 = "ALTER TABLE `loans` 
            ADD CONSTRAINT `fk_loans_request` FOREIGN KEY (`request_id`) REFERENCES `borrow_requests`(`id`) ON DELETE SET NULL";
    
    foreach ([$fk5, $fk6, $fk7] as $fk) {
        @$conn->query($fk); // @ suppresses errors if FK already exists
    }
    
    echo "✓ Foreign keys setup complete (errors ignored if they already exist)\n";
    
    echo "\n✅ Setup complete! Tables are ready to use.\n";
    echo "\nYou can now:\n";
    echo "1. Try submitting a borrow request from the student UI\n";
    echo "2. View requests in librarian-borrow-requests.html\n";
    echo "3. View active loans in librarian-active-loans.html\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='../books.html'>Go to Books Page</a></p>";





















