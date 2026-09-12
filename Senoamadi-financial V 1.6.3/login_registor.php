<?php

session_start();
require_once 'config.php';

if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $country_code = $_POST['country_code'];
    $phone_number = $_POST['phone'];
    $full_phone = $country_code . $phone_number;
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $_SESSION['register_error'] = "Email already registered!";
        $_SESSION['active_form'] = 'register';
        header("Location: login.php");
        exit();
    } else {
        // Insert new user with phone
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $full_phone, $password, $role);

        if ($stmt->execute()) {
            $_SESSION['active_form'] = 'login';
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['register_error'] = "Registration failed. Try again!";
            $_SESSION['active_form'] = 'register';
            header("Location: login.php");
            exit();
        }
    }
}


if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];


    $email = $_POST['email']; // or however you get the email

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

   

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin_page.php");
            } else {
                header("Location: user_page.php");
            }
            exit();
        }
    }

    $_SESSION['login_error'] = 'Incorrect email / password';
    $_SESSION['active_form'] = 'login';
    header("Location: index2.php");
    exit();
}
