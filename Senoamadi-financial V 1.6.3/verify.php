<?php

if(isset($_GET['token'])){
    $token = $_GET['token'];
    $stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    if($stmt->affected_rows > 0){
        echo "Email verified! You can now <a href='login.php'>login</a>.";
    } else {
        echo "Invalid or expired token.";
    }
}