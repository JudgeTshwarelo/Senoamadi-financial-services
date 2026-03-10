<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

$query = trim($_GET['query'] ?? '');
if(!$query){
    echo json_encode(['success'=>false]);
    exit;
}

// Search by email or account_number
$stmt = $conn->prepare("SELECT name FROM users WHERE email=? OR account_number=? LIMIT 1");
$stmt->bind_param("ss",$query,$query);
$stmt->execute();
$stmt->bind_result($name);
if($stmt->fetch()){
    echo json_encode(['success'=>true,'name'=>$name]);
}else{
    echo json_encode(['success'=>false]);
}
$stmt->close();
