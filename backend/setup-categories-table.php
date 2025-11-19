<?php
// backend/setup-categories-table.php
// Run this file once to create the categories table
// Access via: http://localhost/libratrack/backend/setup-categories-table.php

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Setting up Categories Table</h2>";
echo "<pre>";

try {
    // Create categories table
    $sql = "CREATE TABLE IF NOT EXISTS `categories` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL,
      `description` TEXT NULL DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_name` (`name`),
      INDEX `idx_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "✓ Table 'categories' created successfully\n";
    } else {
        echo "✗ Error creating categories table: " . $conn->error . "\n";
    }
    
    // Check if books table has category column (string) or category_id (foreign key)
    $checkCategory = $conn->query("SHOW COLUMNS FROM books LIKE 'category'");
    $hasCategory = $checkCategory->num_rows > 0;
    $checkCategory->close();
    
    $checkCategoryId = $conn->query("SHOW COLUMNS FROM books LIKE 'category_id'");
    $hasCategoryId = $checkCategoryId->num_rows > 0;
    $checkCategoryId->close();
    
    echo "\nBooks table structure:\n";
    echo "  - Has 'category' column (string): " . ($hasCategory ? "Yes" : "No") . "\n";
    echo "  - Has 'category_id' column (FK): " . ($hasCategoryId ? "Yes" : "No") . "\n";
    
    // If books table uses category (string), we'll keep that for now
    // The system will work with both approaches
    
    echo "\n✓ Categories table setup complete!\n";
    echo "\nNote: The system currently uses the 'category' column in books table.\n";
    echo "Categories will be managed centrally, and books will reference them by name.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn->close();
echo "</pre>";
echo "<p><a href='../admin-categories.html'>Go to Categories Page</a></p>";






