<?php
/**
 * Migration script to add school_id to loans table for school-based filtering
 * Run this once to populate school_id for existing loans
 * 
 * Access via: http://localhost/libratrack/backend/migrate-school-isolation.php
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>School Isolation Migration</h2>";
echo "<pre>";

try {
    // Step 1: Add school_id column to loans table (if it doesn't exist)
    echo "Step 1: Checking loans table structure...\n";
    $checkSchoolCol = $conn->query("SHOW COLUMNS FROM loans LIKE 'school_id'");
    if (!$checkSchoolCol || $checkSchoolCol->num_rows === 0) {
        echo "Adding school_id column to loans table...\n";
        $sql = "ALTER TABLE loans 
                ADD COLUMN school_id INT(11) NULL AFTER book_id,
                ADD INDEX idx_school_id (school_id)";
        if ($conn->query($sql)) {
            echo "✓ school_id column added successfully\n";
        } else {
            echo "✗ Error adding school_id column: " . $conn->error . "\n";
        }
    } else {
        echo "✓ school_id column already exists\n";
    }
    if ($checkSchoolCol) $checkSchoolCol->close();
    
    // Step 2: Populate existing loans with school_id from students
    echo "\nStep 2: Populating school_id for existing loans...\n";
    $updateSql = "UPDATE loans l
                  INNER JOIN users u ON u.id = l.student_id
                  SET l.school_id = u.school_id
                  WHERE l.school_id IS NULL OR l.school_id = 0";
    
    if ($conn->query($updateSql)) {
        $affected = $conn->affected_rows;
        echo "✓ Updated $affected loan(s) with school_id from students\n";
    } else {
        echo "✗ Error updating loans: " . $conn->error . "\n";
    }
    
    // Step 3: Add school_id to logs table (if it doesn't exist)
    echo "\nStep 3: Checking logs table structure...\n";
    $checkLogsCol = $conn->query("SHOW COLUMNS FROM logs LIKE 'school_id'");
    if (!$checkLogsCol || $checkLogsCol->num_rows === 0) {
        echo "Adding school_id column to logs table...\n";
        $sql = "ALTER TABLE logs 
                ADD COLUMN school_id INT(11) NULL AFTER details,
                ADD INDEX idx_school_id (school_id)";
        if ($conn->query($sql)) {
            echo "✓ school_id column added to logs table\n";
        } else {
            echo "✗ Error adding school_id to logs: " . $conn->error . "\n";
        }
    } else {
        echo "✓ school_id column already exists in logs table\n";
    }
    if ($checkLogsCol) $checkLogsCol->close();
    
    echo "\n✅ Migration complete! School-based isolation is now active.\n";
    echo "\nNext steps:\n";
    echo "1. Verify that all librarians have a school_id assigned\n";
    echo "2. Test that librarians can only see their school's data\n";
    echo "3. Test that admins can see all data (no filtering)\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='../dashboard.html'>Go to Dashboard</a></p>";











