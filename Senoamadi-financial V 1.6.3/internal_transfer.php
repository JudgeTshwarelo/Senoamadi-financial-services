<?php
session_start();
require_once 'config.php'; // DB credentials

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['transfer'])) {
    $sender_id = $_SESSION['id'];
    $receiver_email = trim($_POST['receiver_email']);
    $amount = floatval($_POST['amount']);

    // Basic validation
    if ($amount <= 0) {
        echo "❌ Invalid amount.";
        exit();
    }

    // Get receiver ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $receiver_email);
    $stmt->execute();
    $receiver = $stmt->get_result()->fetch_assoc();

    if (!$receiver) {
        echo "❌ Receiver not found.";
        exit();
    }

    $receiver_id = $receiver['id'];

    if ($receiver_id == $sender_id) {
        echo "❌ You cannot send money to yourself.";
        exit();
    }

    // Get sender balance
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $sender = $stmt->get_result()->fetch_assoc();

    if ($sender['balance'] < $amount) {
        echo "❌ Insufficient funds.";
        exit();
    }

    // Safe transaction process
    $conn->begin_transaction();

    try {
        // Deduct from sender
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $sender_id);
        $stmt->execute();

        // Credit receiver
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $receiver_id);
        $stmt->execute();

        // Record transaction
        $reference = uniqid("tx_");
        $stmt = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, reference, status) VALUES (?, ?, ?, ?, 'completed')");
        $stmt->bind_param("iids", $sender_id, $receiver_id, $amount, $reference);
        $stmt->execute();

        $conn->commit();
        echo "✅ Transfer successful! Reference: $reference";
    } catch (Exception $e) {
        $conn->rollback();
        echo "❌ Transfer failed: " . $e->getMessage();
    }
}
