<?php
/**
 * Create Admin Account Script
 * Run this file once via browser or command line to create an admin account
 * 
 * Usage via browser: http://localhost/LibraTrack/backend/create-admin.php
 * Usage via command line: php backend/create-admin.php
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

// Configuration - Change these values
$adminEmail = 'admin2@libratrack.com';
$adminPassword = 'Admin123!'; // Must be ≥8 chars, 1 uppercase, 1 special
$adminFirstName = 'Libra';
$adminMiddleName = '';
$adminLastName = 'Admin2';
$adminSuffix = '';

// Check if running via command line or browser
$isCLI = php_sapi_name() === 'cli';

if ($isCLI) {
    echo "Creating admin account...\n";
    echo "Email: {$adminEmail}\n";
    echo "Password: {$adminPassword}\n\n";
} else {
    echo "<!DOCTYPE html><html><head><title>Create Admin Account</title></head><body>";
    echo "<h2>Creating Admin Account</h2>";
    echo "<p>Email: <strong>{$adminEmail}</strong></p>";
    echo "<p>Password: <strong>{$adminPassword}</strong></p>";
    echo "<hr>";
}

try {
    $conn->set_charset('utf8mb4');
    
    // Validate email
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address: {$adminEmail}");
    }
    
    // Validate password
    if (strlen($adminPassword) < 8) {
        throw new Exception("Password must be at least 8 characters long");
    }
    if (!preg_match('/[A-Z]/', $adminPassword)) {
        throw new Exception("Password must contain at least one uppercase letter");
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|\\:;\'"<>,.?\/~`]/', $adminPassword)) {
        throw new Exception("Password must contain at least one special character");
    }
    
    // Check if this specific email already exists
    $checkStmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
    $checkStmt->bind_param("s", $adminEmail);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $checkStmt->close();
        throw new Exception("Account with this email already exists! Email: " . $existing['email']);
    }
    $checkStmt->close();
    
    // Hash password
    $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
    
    // Insert admin account
    $stmt = $conn->prepare("
        INSERT INTO users 
        (first_name, middle_name, last_name, suffix, email, password, role, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active', NOW())
    ");
    
    $middleParam = ($adminMiddleName === '') ? null : $adminMiddleName;
    $suffixParam = ($adminSuffix === '') ? null : $adminSuffix;
    
    $stmt->bind_param("ssssss", 
        $adminFirstName, 
        $middleParam, 
        $adminLastName, 
        $suffixParam, 
        $adminEmail, 
        $hashedPassword
    );
    
    if ($stmt->execute()) {
        $adminId = $stmt->insert_id;
        $stmt->close();
        
        if ($isCLI) {
            echo "✓ Admin account created successfully!\n";
            echo "  ID: {$adminId}\n";
            echo "  Email: {$adminEmail}\n";
            echo "  Name: {$adminFirstName} {$adminLastName}\n";
            echo "  Role: admin\n";
            echo "  Status: active\n\n";
            echo "You can now login with these credentials.\n";
        } else {
            echo "<div style='color: green; padding: 20px; background: #d1fae5; border-radius: 8px; margin: 20px 0;'>";
            echo "<h3>✓ Admin account created successfully!</h3>";
            echo "<ul>";
            echo "<li><strong>ID:</strong> {$adminId}</li>";
            echo "<li><strong>Email:</strong> {$adminEmail}</li>";
            echo "<li><strong>Name:</strong> {$adminFirstName} {$adminLastName}</li>";
            echo "<li><strong>Role:</strong> admin</li>";
            echo "<li><strong>Status:</strong> active</li>";
            echo "</ul>";
            echo "<p><strong>You can now login with these credentials.</strong></p>";
            echo "</div>";
            echo "<p><a href='../login.html'>Go to Login Page</a></p>";
        }
    } else {
        throw new Exception("Failed to create admin: " . $stmt->error);
    }
    
} catch (Exception $e) {
    if ($isCLI) {
        echo "✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        echo "<div style='color: red; padding: 20px; background: #fee2e2; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3>✗ Error</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

if (!$isCLI) {
    echo "</body></html>";
}

