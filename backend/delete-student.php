<?php
// backend/delete-student.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Internally delegate to delete-user.php
require __DIR__ . '/delete-user.php';
