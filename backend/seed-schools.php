<?php
/**
 * Seed script to add all Cebu schools to the database
 * Run this once to populate the schools table
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

// List of schools in alphabetical order
$schools = [
    'Asian College of Technology (ACT)',
    'Cebu Doctors\' University (CDU)',
    'Cebu Eastern College (CEC)',
    'Cebu Institute of Technology - University (CIT-U)',
    'Cebu Normal University (CNU)',
    'Cebu Technological University (CTU)',
    'Southwestern University PHINMA (SWU)',
    'University of Cebu - Banilad Campus (UC-Banilad)',
    'University of Cebu - Lapu-Lapu and Mandaue (UCLM)',
    'University of Cebu - Main Campus (UC-Main)',
    'University of the Philippines Cebu (UP Cebu)',
    'University of San Carlos - Talamban (USC)',
    'University of San Jose - Recoletos (USJ-R)',
    'University of the Visayas (UV)'
];

try {
    $conn->set_charset('utf8mb4');
    
    // Check if schools already exist
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM schools");
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $existingCount = (int)$row['count'];
    $checkStmt->close();
    
    if ($existingCount > 0) {
        echo "Warning: Schools table already contains {$existingCount} schools.\n";
        echo "This script will add missing schools only.\n\n";
    }
    
    $added = 0;
    $skipped = 0;
    
    foreach ($schools as $schoolName) {
        // Check if school already exists
        $stmt = $conn->prepare("SELECT id FROM schools WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $schoolName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "Skipped (already exists): {$schoolName}\n";
            $skipped++;
        } else {
            // Insert new school
            $insertStmt = $conn->prepare("INSERT INTO schools (name) VALUES (?)");
            $insertStmt->bind_param("s", $schoolName);
            
            if ($insertStmt->execute()) {
                echo "Added: {$schoolName}\n";
                $added++;
            } else {
                echo "Error adding {$schoolName}: " . $insertStmt->error . "\n";
            }
            $insertStmt->close();
        }
        
        $stmt->close();
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "- Added: {$added} schools\n";
    echo "- Skipped: {$skipped} schools\n";
    echo "- Total in database: " . ($existingCount + $added) . " schools\n";
    
    // Verify alphabetical order
    $verifyStmt = $conn->query("SELECT name FROM schools ORDER BY name");
    echo "\nSchools in database (alphabetical order):\n";
    while ($row = $verifyStmt->fetch_assoc()) {
        echo "  - " . $row['name'] . "\n";
    }
    $verifyStmt->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    http_response_code(500);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

