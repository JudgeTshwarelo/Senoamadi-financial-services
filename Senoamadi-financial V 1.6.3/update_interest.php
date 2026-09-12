<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    exit("Access denied");
}

if(isset($_POST['id'], $_POST['rate'])){
    $id = intval($_POST['id']);
    $rate = floatval($_POST['rate']);

    $stmt = $conn->prepare("UPDATE investments SET interest_rate=? WHERE id=?");
    $stmt->bind_param("di", $rate, $id);

    if($stmt->execute()){
        echo "Interest rate updated successfully!";
    } else {
        echo "Error updating interest: " . $stmt->error;
    }
}
?>

