

<?php
require_once __DIR__ . '/db.php';   // has json_response()

requireRole(['librarian','admin']);

// requireRole(['admin','librarian']); // enable when auth is ready

// <?php
// require __DIR__ . "/db.php";
// header('Content-Type: application/json; charset=utf-8');


function json_response($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['ok'=>false,'error'=>'Use POST'], 405);
}

$title     = trim($_POST["title"] ?? "");
$isbn      = trim($_POST["isbn"] ?? "");
$author    = trim($_POST["author"] ?? "");
$category  = trim($_POST["category"] ?? "");
$quantity  = (int)($_POST["quantity"] ?? 0);
$publisher = trim($_POST["publisher"] ?? "");
$date_pub  = trim($_POST["date_published"] ?? "");

if ($title===""||$isbn===""||$author===""||$category===""||$publisher===""||$quantity<=0) {
  json_response(['ok'=>false,'error'=>'Please fill all required fields.'],400);
}

// cover (optional)
$coverName = null;
if (!empty($_FILES['cover']['name'])) {
  $err = $_FILES['cover']['error'];
  if ($err !== UPLOAD_ERR_OK) {
    $msgs = [
      UPLOAD_ERR_INI_SIZE=>'File exceeds upload_max_filesize.',
      UPLOAD_ERR_FORM_SIZE=>'File exceeds MAX_FILE_SIZE.',
      UPLOAD_ERR_PARTIAL=>'Partial upload.',
      UPLOAD_ERR_NO_FILE=>'No file uploaded.',
      UPLOAD_ERR_NO_TMP_DIR=>'Missing temp folder.',
      UPLOAD_ERR_CANT_WRITE=>'Failed to write file.',
      UPLOAD_ERR_EXTENSION=>'PHP extension blocked upload.'
    ];
    json_response(['ok'=>false,'error'=>$msgs[$err] ?? ('Upload error: '.$err)],400);
  }

  $dir = __DIR__.'/../uploads/covers';
  if (!is_dir($dir) && !mkdir($dir,0777,true)) {
    json_response(['ok'=>false,'error'=>'Cannot create uploads/covers'],500);
  }

  $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
    json_response(['ok'=>false,'error'=>'Invalid image type (jpg, jpeg, png, gif, webp)'],400);
  }
  if ($_FILES['cover']['size'] > 5*1024*1024) {
    json_response(['ok'=>false,'error'=>'Max image size is 5 MB'],400);
  }

  $coverName = uniqid('cover_', true).'.'.$ext;
  if (!move_uploaded_file($_FILES['cover']['tmp_name'], $dir.'/'.$coverName)) {
    json_response(['ok'=>false,'error'=>'Failed to save image'],500);
  }
}

// insert
$dp = $date_pub !== '' ? $date_pub : null;
$stmt = $conn->prepare("
  INSERT INTO books (title, isbn, author, category, quantity, publisher, date_published, cover)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) json_response(['ok'=>false,'error'=>'Prepare failed: '.$conn->error],500);

$stmt->bind_param("ssssisss", $title,$isbn,$author,$category,$quantity,$publisher,$dp,$coverName);

if (!$stmt->execute()) {
  $err = ($conn->errno === 1062) ? 'ISBN already exists.' : ('Insert failed: '.$conn->error);
  json_response(['ok'=>false,'error'=>$err],400);
}

json_response(['ok'=>true,'id'=>$stmt->insert_id]);
