<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    echo "error: not logged in";
    exit();
}

$user_id = $_SESSION['id'];
$action = $_GET['action'] ?? '';

if (!in_array($action, ['freeze', 'unfreeze'])) {
    echo "error: invalid action";
    exit();
}

$status = $action === 'freeze' ? 'frozen' : 'active';

$stmt = $conn->prepare("UPDATE virtual_cards SET status = ? WHERE user_id = ?");
$stmt->bind_param("si", $status, $user_id);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error: " . $conn->error;
}
?>
