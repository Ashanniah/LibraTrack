<?php
// backend/list-students.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Preserve session + return JSON by delegating to list-users.php with role=student
$_GET['role'] = 'student';
require __DIR__ . '/list-users.php';
