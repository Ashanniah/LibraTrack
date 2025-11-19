<?php
// backend/search-students-books.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can search
$user = require_role($conn, ['librarian', 'admin']);

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'both')); // 'students', 'books', or 'both'

if (!$q) {
  json_response(['ok'=>true, 'students'=>[], 'books'=>[]]);
}

$results = ['students' => [], 'books' => []];

// School filtering: Librarians can only search students from their school
$currentRole = strtolower($user['role'] ?? '');
$schoolFilter = '';
$schoolParams = [];
$schoolTypes = '';

if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  if ($librarianSchoolId > 0) {
    $schoolFilter = " AND school_id = ? ";
    $schoolParams[] = $librarianSchoolId;
    $schoolTypes .= 'i';
  } else {
    // Librarian without school - return empty
    json_response(['ok'=>true, 'students'=>[], 'books'=>[]]);
  }
}
// Admin sees all students (no school filter)

// Search students
if ($type === 'both' || $type === 'students') {
  $sql = "SELECT id, first_name, last_name, student_no, email 
          FROM users 
          WHERE role = 'student' 
          $schoolFilter
          AND (CONCAT(first_name, ' ', last_name) LIKE ? 
               OR student_no LIKE ? 
               OR email LIKE ?)
          LIMIT 20";
  $stmt = $conn->prepare($sql);
  $searchTerm = "%{$q}%";
  $allParams = array_merge($schoolParams, [$searchTerm, $searchTerm, $searchTerm]);
  $allTypes = $schoolTypes . 'sss';
  $stmt->bind_param($allTypes, ...$allParams);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $results['students'][] = [
      'id' => (int)$row['id'],
      'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
      'student_no' => $row['student_no'] ?? '',
      'email' => $row['email'] ?? '',
      'display' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . ' (' . ($row['student_no'] ?? '') . ')'
    ];
  }
  $stmt->close();
}

// Search books
if ($type === 'both' || $type === 'books') {
  $sql = "SELECT id, title, author, isbn, category 
          FROM books 
          WHERE (title LIKE ? 
                 OR author LIKE ? 
                 OR isbn LIKE ? 
                 OR category LIKE ?)
          LIMIT 20";
  $stmt = $conn->prepare($sql);
  $searchTerm = "%{$q}%";
  $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $results['books'][] = [
      'id' => (int)$row['id'],
      'title' => $row['title'] ?? '',
      'author' => $row['author'] ?? '',
      'isbn' => $row['isbn'] ?? '',
      'category' => $row['category'] ?? '',
      'display' => ($row['title'] ?? '') . ' — ' . ($row['isbn'] ?? '')
    ];
  }
  $stmt->close();
}

json_response(['ok' => true, 'students' => $results['students'], 'books' => $results['books']]);

