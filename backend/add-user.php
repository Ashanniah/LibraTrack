<?php
require_once "db.php"; // your db connection file

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first = trim($_POST['first_name']);
    $middle = trim($_POST['middle_name']);
    $last = trim($_POST['last_name']);
    $suffix = trim($_POST['suffix']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $status = $_POST['status'];

    // Force role as librarian (admin cannot create student accounts here)
    $role = "librarian";

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO users 
        (first_name, middle_name, last_name, suffix, email, password, role, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssss", $first, $middle, $last, $suffix, $email, $hashedPassword, $role, $status);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Librarian account created successfully!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
}
?>
