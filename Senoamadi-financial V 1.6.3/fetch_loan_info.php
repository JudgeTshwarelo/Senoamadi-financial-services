<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) exit(json_encode([]));

$uid = $_SESSION['id'];

// Fetch loan summary
$loan_summary = $conn->query("SELECT status, COUNT(*) AS total FROM loan_applications WHERE user_id=$uid GROUP BY status");
$pending = $approved = $rejected = 0;
if ($loan_summary && $loan_summary->num_rows > 0) {
  while ($row = $loan_summary->fetch_assoc()) {
    $status = strtolower($row['status']);
    if ($status == 'pending') $pending = $row['total'];
    elseif ($status == 'approved') $approved = $row['total'];
    elseif ($status == 'rejected') $rejected = $row['total'];
  }
}

// Fetch notifications
$notifications = [];
$res = $conn->query("SELECT amount,status FROM loan_applications WHERE user_id=$uid AND status!='pending' ORDER BY created_at DESC");
if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) $notifications[] = $row;
}

echo json_encode([
  'pending' => $pending,
  'approved' => $approved,
  'rejected' => $rejected,
  'notifications' => $notifications
]);
