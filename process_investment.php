<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['email']) || $_SESSION['role']!=='admin'){
    header("Location: index2.php");
    exit();
}

if(isset($_GET['id'], $_GET['action'])){
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if($action === 'approve'){
        $stmt = $conn->prepare("UPDATE investments SET status='approved' WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
    } elseif($action === 'reject'){
        $stmt = $conn->prepare("UPDATE investments SET status='rejected' WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
    }

    header("Location: admin_page.php");
}
?>


