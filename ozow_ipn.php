<?php
session_start();
require_once 'config.php';

// Get POST from Ozow
$raw_post = file_get_contents('php://input');
$data = json_decode($raw_post, true);

// Example fields: reference, status, amount, customerId
$reference = $data['reference'] ?? null;
$status    = $data['status'] ?? null;
$amount    = floatval($data['amount'] ?? 0);
$uid       = intval($data['customerId'] ?? 0);

if ($status === 'success' && $uid > 0) {
    // Update user balance
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->bind_param("di", $amount, $uid);
    $stmt->execute();

    // Record transaction
    $stmt2 = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, type, status, reference) VALUES (?, ?, ?, 'bank', 'completed', ?)");
    $sender_id = 0; // system
    $receiver_id = $uid;
    $stmt2->bind_param("iids", $sender_id, $receiver_id, $amount, $reference);
    $stmt2->execute();
}

// Return 200 OK to provider
http_response_code(200);
