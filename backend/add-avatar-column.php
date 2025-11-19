<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Error: Database connection not found.\n");
}
$conn->set_charset('utf8mb4');

echo "Checking for avatar column in users table...\n";

// Check if column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'avatar'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    echo "Avatar column already exists.\n";
    $checkColumn->free();
} else {
    echo "Avatar column does not exist. Creating it...\n";
    
    // Add avatar column
    $sql = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER status";
    
    if ($conn->query($sql)) {
        echo "Success: Avatar column created.\n";
    } else {
        echo "Error creating avatar column: " . $conn->error . "\n";
        exit(1);
    }
}

// Show current structure
echo "\nCurrent users table structure:\n";
$result = $conn->query("SHOW COLUMNS FROM users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    $result->free();
}

$conn->close();
echo "\nDone.\n";






