<?php
// backend/update-book.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // gives $conn + helpers
requireRole(['librarian','admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

function g(string $key): string { return trim((string)($_POST[$key] ?? '')); }
function date_or_null(string $s): ?string {
  $s = trim($s); if ($s==='') return null;
  $d = date_create_from_format('Y-m-d', $s);
  return $d ? $d->format('Y-m-d') : null;
}

$id         = (int)($_POST['id'] ?? 0);
$title      = g('title');
$isbn       = g('isbn');
$author     = g('author');
$category   = g('category');
$publisher  = g('publisher');
$quantity   = max(1, (int)($_POST['quantity'] ?? 0));
$datePub    = date_or_null((string)($_POST['date_published'] ?? ''));

if ($id<=0) json_response(['ok'=>false,'error'=>'Invalid id'],400);
$missing = [];
foreach (['title'=>$title,'isbn'=>$isbn,'author'=>$author,'category'=>$category,'publisher'=>$publisher] as $k=>$v) {
  if ($v==='') $missing[] = $k;
}
if ($missing) json_response(['ok'=>false,'error'=>'Missing: '.implode(', ',$missing)], 400);

// check unique ISBN (except self)
$stmt=$conn->prepare('SELECT id FROM books WHERE isbn=? AND id<>? LIMIT 1');
$stmt->bind_param('si',$isbn,$id);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows>0){ $stmt->close(); json_response(['ok'=>false,'error'=>'ISBN already exists'],409); }
$stmt->close();

// optional new cover
$coverFile=null;
if (!empty($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
  $file = $_FILES['cover'];
  if (($file['size'] ?? 0) > 5*1024*1024) json_response(['ok'=>false,'error'=>'Cover too large (max 5 MB)'], 400);
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
  if (!isset($allowed[$mime])) json_response(['ok'=>false,'error'=>'Invalid cover type'], 400);
  $dir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
  $covers = $dir . '/covers';
  if (!is_dir($covers) && !mkdir($covers,0775,true) && !is_dir($covers)) {
    json_response(['ok'=>false,'error'=>'Upload dir error'], 500);
  }
  $ext=$allowed[$mime];
  $coverFile='cover_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).".$ext";
  $dest=$covers.'/'.$coverFile;
  if (!move_uploaded_file($file['tmp_name'],$dest)) json_response(['ok'=>false,'error'=>'Failed to save cover'],500);
  @chmod($dest,0644);
}

try{
  if ($datePub===null){
    $sql = 'UPDATE books SET title=?, isbn=?, author=?, category=?, quantity=?, publisher=?, date_published=NULL'.($coverFile?', cover=?':'').' WHERE id=?';
    if ($coverFile){
      $stmt=$conn->prepare($sql);
      $stmt->bind_param('ssssissi',$title,$isbn,$author,$category,$quantity,$publisher,$coverFile,$id);
    }else{
      $stmt=$conn->prepare($sql);
      $stmt->bind_param('ssssis i',$title,$isbn,$author,$category,$quantity,$publisher,$id);
    }
  }else{
    $sql = 'UPDATE books SET title=?, isbn=?, author=?, category=?, quantity=?, publisher=?, date_published=?'.($coverFile?', cover=?':'').' WHERE id=?';
    if ($coverFile){
      $stmt=$conn->prepare($sql);
      $stmt->bind_param('ssssisssi',$title,$isbn,$author,$category,$quantity,$publisher,$datePub,$coverFile,$id);
    }else{
      $stmt=$conn->prepare($sql);
      $stmt->bind_param('ssssissi',$title,$isbn,$author,$category,$quantity,$publisher,$datePub,$id);
    }
  }
  if (!$stmt->execute()) throw new Exception($stmt->error);
  $stmt->close();
  json_response(['ok'=>true]);
}catch(Throwable $e){
  if ($coverFile) @unlink(__DIR__.'/../uploads/covers/'.$coverFile);
  json_response(['ok'=>false,'error'=>'Update failed'],500);
}
