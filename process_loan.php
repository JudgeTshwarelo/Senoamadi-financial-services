<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['email']) || $_SESSION['role'] != 'admin'){
    header("Location: index2.php");
    exit();
}

if(isset($_GET['id'], $_GET['action'])){
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    $loan = $conn->query("SELECT * FROM loan_applications WHERE id=$id")->fetch_assoc();
    if(!$loan) { die("Loan not found"); }

    if($action == 'approve'){
        $conn->query("UPDATE loan_applications SET status='approved' WHERE id=$id");
        $conn->query("INSERT INTO approved_loans (loan_id, user_id, amount) VALUES ({$loan['id']}, {$loan['user_id']}, {$loan['amount']})");
        $_SESSION['loan_message'] = "Loan approved successfully!";
    }
    if($action == 'reject'){
        $conn->query("UPDATE loan_applications SET status='rejected' WHERE id=$id");
        $_SESSION['loan_message'] = "Loan rejected!";
    }

    header("Location: admin_page.php");
    exit();
}
?>