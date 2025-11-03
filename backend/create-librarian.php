<?php
// backend/create-librarian.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

// Optional but useful while developing:
ini_set('display_errors', '0');          // keep output clean for JSON
ini_set('log_errors', '1');              // log to PHP error log
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---- Safe JSON responder (works even if db.php doesn't define json_response()) ----
function respond(array $payload, int $code = 200): void {
  if (function_exists('json_response')) {
    json_response($payload, $code);
  } else {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

try {
  if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new RuntimeException('Database connection not found.');
  }
  $conn->set_charset('utf8mb4');

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(["success"=>false, "message"=>"Method not allowed"], 405);
  }

  // Accept JSON or form body
  $raw  = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  if (!is_array($data) || empty($data)) $data = $_POST;

  // ---- Inputs from your modal ----
  $first    = trim((string)($data['first_name']  ?? ''));
  $middle   = trim((string)($data['middle_name'] ?? ''));
  $last     = trim((string)($data['last_name']   ?? ''));
  $suffix   = trim((string)($data['suffix']      ?? ''));
  $email    = trim((string)($data['email']       ?? ''));
  $password = (string)($data['password']         ?? '');
  $status   = strtolower(trim((string)($data['status'] ?? 'active')));
  $role     = 'librarian';

  // school_id (required in your UI)
  $schoolId = $data['school_id'] ?? null;
  $schoolId = ($schoolId === '' ? null : $schoolId);
  $schoolId = is_null($schoolId) ? null : (int)$schoolId;

  // ---- Validation ----
  if ($first === '' || $last === '') {
    respond(["success"=>false, "message"=>"First and last name are required."], 422);
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(["success"=>false, "message"=>"Enter a valid email."], 422);
  }
  $pwRule = '/^(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{}|\\:;\'"<>,.?\/~`]).{8,}$/';
  if ($password === '' || !preg_match($pwRule, $password)) {
    respond(["success"=>false, "message"=>"Password must have ≥8 chars, 1 uppercase, 1 special character."], 422);
  }
  if (is_null($schoolId)) {
    respond(["success"=>false, "message"=>"Please select a school."], 422);
  }

  // Verify school exists
  $chkSchool = $conn->prepare("SELECT 1 FROM schools WHERE id=? LIMIT 1");
  $chkSchool->bind_param("i", $schoolId);
  $chkSchool->execute();
  $chkSchool->store_result();
  if ($chkSchool->num_rows === 0) {
    $chkSchool->close();
    respond(["success"=>false, "message"=>"Selected school not found."], 422);
  }
  $chkSchool->close();

  // Unique email
  $chk = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $chk->bind_param("s", $email);
  $chk->execute();
  $chk->store_result();
  if ($chk->num_rows > 0) {
    $chk->close();
    respond(["success"=>false, "message"=>"Email already exists."], 409);
  }
  $chk->close();

  // Hash password
  $hashed = password_hash($password, PASSWORD_BCRYPT);

  // Insert
  $stmt = $conn->prepare("
    INSERT INTO users
      (first_name, middle_name, last_name, suffix, email, password, role, school_id, status, created_at)
    VALUES
      (?,?,?,?,?,?,?,?,?, NOW())
  ");
  $dbStatus = ($status === 'disabled') ? 'disabled' : 'active';
  // types: s s s s s s s i s
  $stmt->bind_param(
    "sssssssis",
    $first,
    $middle,
    $last,
    $suffix,
    $email,
    $hashed,
    $role,
    $schoolId,
    $dbStatus
  );
  $stmt->execute();
  $newId = $conn->insert_id;
  $stmt->close();

  respond([
    "success" => true,
    "id"      => $newId,
    "message" => "Librarian account created successfully!"
  ]);

} catch (mysqli_sql_exception $e) {
  respond(["success"=>false, "message"=>"Server error: ".$e->getMessage()], 500);
} catch (Throwable $e) {
  respond(["success"=>false, "message"=>"Server error: ".$e->getMessage()], 500);
}
