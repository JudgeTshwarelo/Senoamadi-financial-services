<?php
require_once 'config.php';

// Fetch approved investments
$investments = $conn->query("SELECT id, amount, interest_rate, last_interest FROM investments WHERE status='approved'");

while ($inv = $investments->fetch_assoc()) {
    $last = strtotime($inv['last_interest'] ?? $inv['created_at']);
    $now = time();
    // if 30 days passed
    if (($now - $last) >= 30 * 24 * 60 * 60) {
        $interest = $inv['amount'] * ($inv['interest_rate'] / 100);
        $new_amount = $inv['amount'] + $interest;

        $stmt = $conn->prepare("UPDATE investments SET amount=?, last_interest=NOW() WHERE id=?");
        $stmt->bind_param("di", $new_amount, $inv['id']);
        $stmt->execute();
    }
}
