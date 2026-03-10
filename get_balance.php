<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    echo json_encode(['balance' => 0.00]);
    exit();
}

$id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['balance' => $result['balance']]);

?>