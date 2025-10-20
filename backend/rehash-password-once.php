<?php
require_once __DIR__ . '/db.php';

$email = 'librarian123@libratrack.local';  // adjust
$newPlain = 'Oliver-123';                   // adjust

$hash = password_hash($newPlain, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
$stmt->bind_param('ss', $hash, $email);
$stmt->execute();
echo "Password updated for ".$email."\n";
