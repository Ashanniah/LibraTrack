<?php
// returns the logged-in user from session
declare(strict_types=1);
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'message'=>'Not authenticated']);
  exit;
}

// You stored this in login.php already:
// $_SESSION['user'] = ['id'=>..,'first_name'=>..,'last_name'=>..,'email'=>..,'role'=>..,'status'=>..]
echo json_encode(['success'=>true, 'user'=>$_SESSION['user']]);
