<?php
// backend/add-book.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';  // gives $conn (mysqli), $pdo (PDO), helpers, and session
require_once __DIR__ . '/auth.php';

// ---- Only librarian/admin can add ----
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

function g(string $key): string { return trim((string)($_POST[$key] ?? '')); }
function date_or_null(string $s): ?string {
  $s = trim($s);
  if ($s==='') return null;
  $d = date_create_from_format('Y-m-d', $s);
  return $d ? $d->format('Y-m-d') : null;
}

// ---- inputs ----
$title      = g('title');
$isbn       = g('isbn');
$author     = g('author');
$category   = g('category');
$publisher  = g('publisher');
$quantity   = max(1, (int)($_POST['quantity'] ?? 0));
$datePub    = date_or_null((string)($_POST['date_published'] ?? ''));

$missing = [];
foreach (['title'=>$title,'isbn'=>$isbn,'author'=>$author,'category'=>$category,'publisher'=>$publisher] as $k=>$v) {
  if ($v==='') $missing[] = $k;
}
if ($missing) json_response(['ok'=>false,'error'=>'Missing: '.implode(', ',$missing)], 400);

// ---- unique ISBN ----
$stmt = $conn->prepare('SELECT id FROM books WHERE isbn=? LIMIT 1');
$stmt->bind_param('s',$isbn);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) { $stmt->close(); json_response(['ok'=>false,'error'=>'ISBN already exists'], 409); }
$stmt->close();

// ---- optional cover upload ----
$coverFile = null;
if (!empty($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
  $file = $_FILES['cover'];
  if (($file['size'] ?? 0) > 5*1024*1024) json_response(['ok'=>false,'error'=>'Cover too large (max 5 MB)'], 400);

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
  if (!isset($allowed[$mime])) json_response(['ok'=>false,'error'=>'Invalid cover type'], 400);

  // Save to public/uploads/covers for web accessibility
  $dir = realpath(__DIR__ . '/../public/uploads') ?: (__DIR__ . '/../public/uploads');
  $covers = $dir . '/covers';
  if (!is_dir($covers) && !mkdir($covers,0775,true) && !is_dir($covers)) {
    json_response(['ok'=>false,'error'=>'Upload dir error'], 500);
  }

  $ext = $allowed[$mime];
  $coverFile = 'cover_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).".$ext";
  $dest = $covers.'/'.$coverFile;
  if (!move_uploaded_file($file['tmp_name'],$dest)) json_response(['ok'=>false,'error'=>'Failed to save cover'], 500);
  @chmod($dest,0644);
}

// ---- insert ----
try {
  if ($datePub === null) {
    $stmt = $conn->prepare(
      'INSERT INTO books (title,isbn,author,category,quantity,publisher,date_published,cover)
       VALUES (?,?,?,?,?,?,NULL,?)'
    );
    $stmt->bind_param('ssssiss',$title,$isbn,$author,$category,$quantity,$publisher,$coverFile);
  } else {
    $stmt = $conn->prepare(
      'INSERT INTO books (title,isbn,author,category,quantity,publisher,date_published,cover)
       VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->bind_param('ssssisss',$title,$isbn,$author,$category,$quantity,$publisher,$datePub,$coverFile);
  }

  if (!$stmt->execute()) throw new Exception($stmt->error);
  $newId = $stmt->insert_id;
  $stmt->close();

  json_response([
    'ok'        => true,
    'id'        => $newId,
    'cover'     => $coverFile,
    'cover_url' => $coverFile ? ("/uploads/covers/".$coverFile) : null
  ]);
} catch (Throwable $e) {
  if ($coverFile) @unlink(__DIR__.'/../public/uploads/covers/'.$coverFile);
  json_response(['ok'=>false,'error'=>'Insert failed'], 500);
}
