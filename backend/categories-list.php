<?php
header('Content-Type: application/json');
$mysqli = new mysqli('127.0.0.1','root','','libratrack');
$mysqli->set_charset('utf8mb4');
$res = $mysqli->query("SELECT DISTINCT category FROM books WHERE category <> '' ORDER BY category");
$cats = [];
while($row = $res->fetch_row()){ $cats[] = $row[0]; }
echo json_encode(['ok'=>true,'categories'=>$cats]);
