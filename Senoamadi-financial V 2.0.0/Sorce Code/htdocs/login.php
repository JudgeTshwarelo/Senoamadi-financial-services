<?php

session_start();

require_once "config.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// If already logged in, push them to their dashboard
if (isset($_SESSION['user_id'])) {
    // adjust based on role if needed
    header("Location: user_page.php");
    exit();
}
// ... rest of login logic
/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function redirect_auth($form = "login")
{
    header("Location: login.php?form=" . urlencode($form));
    exit;
}

function set_auth_error($form, $message)
{
    $_SESSION[$form . "_error"] = $message;
    $_SESSION["active_form"] = $form;
    redirect_auth($form);
}

function set_auth_success($form, $message)
{
    $_SESSION[$form . "_success"] = $message;
    $_SESSION["active_form"] = $form;
    redirect_auth($form);
}

function firebase_error_message($firebase)
{
    if (isset($firebase["data"]["error"]["message"])) {
        $error = $firebase["data"]["error"]["message"];

        switch ($error) {
            case "EMAIL_EXISTS":
                return "This email is already registered.";
            case "INVALID_EMAIL":
                return "The email address is invalid.";
            case "WEAK_PASSWORD : Password should be at least 6 characters":
                return "Password must be at least 6 characters.";
            case "INVALID_PASSWORD":
                return "Incorrect password.";
            case "EMAIL_NOT_FOUND":
                return "No account was found with this email.";
            case "USER_DISABLED":
                return "This account has been disabled.";
            case "INVALID_LOGIN_CREDENTIALS":
                return "Incorrect email or password.";
            default:
                return $error;
        }
    }

    return $firebase["message"] ?? "Firebase request failed.";
}

/*
|--------------------------------------------------------------------------
| MAKE SURE REQUEST IS POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | DATABASE
    |--------------------------------------------------------------------------
    */

    try {
        $db = get_db();
    } catch (PDOException $e) {
        set_auth_error("login", "Database connection failed. Please try again.");
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */

    if (isset($_POST["register"])) {

        $fullname = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $countryCode = trim($_POST["country_code"] ?? "+27");
        $password = trim($_POST["password"] ?? "");
        $role = $_POST["role"] ?? "user";

        // VALIDATION
        if (empty($fullname) || empty($email) || empty($phone) || empty($password)) {
            set_auth_error("register", "Please fill in all fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_auth_error("register", "Please enter a valid email address.");
        }

        if (strlen($password) < 6) {
            set_auth_error("register", "Password must be at least 6 characters.");
        }

        if (!preg_match("/^[0-9]{9}$/", $phone)) {
            set_auth_error("register", "Please enter a valid 9-digit South African cellphone number.");
        }

        $fullPhone = $countryCode . $phone;

        // CHECK MYSQL
        try {
            $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);

            if ($check->fetch()) {
                set_auth_error("register", "Email already registered. Please login.");
            }
        } catch (PDOException $e) {
            set_auth_error("register", "Unable to check your account. Please try again.");
        }

        // CREATE FIREBASE ACCOUNT
        try {
            $firebase = firebase_request(
                FIREBASE_SIGNUP_URL,
                [
                    "email" => $email,
                    "password" => $password,
                    "returnSecureToken" => true
                ]
            );
        } catch (Exception $e) {
            set_auth_error("register", "Unable to connect to the authentication service.");
        }

        if (!$firebase["success"]) {
            set_auth_error("register", firebase_error_message($firebase));
        }

        $firebaseData = $firebase["data"] ?? [];

        if (isset($firebaseData["error"])) {
            set_auth_error("register", firebase_error_message($firebase));
        }

        $firebase_uid = $firebaseData["localId"] ?? "";
        $idToken = $firebaseData["idToken"] ?? "";

        if (empty($firebase_uid) || empty($idToken)) {
            set_auth_error("register", "Firebase account creation failed.");
        }

        // SEND FIREBASE VERIFICATION EMAIL
        try {
            $verifyEmail = firebase_request(
                FIREBASE_VERIFY_EMAIL_URL,
                [
                    "requestType" => "VERIFY_EMAIL",
                    "idToken" => $idToken
                ]
            );
        } catch (Exception $e) {
            $verifyEmail = ["success" => false];
        }

        if (!$verifyEmail["success"] || isset($verifyEmail["data"]["error"])) {
            set_auth_error(
                "register",
                "Your account was created, but we could not send the verification email. Please contact support."
            );
        }

        $hashedPassword = hash_password($password);

        // INSERT USER INTO MYSQL
        try {
            $stmt = $db->prepare("
                INSERT INTO users
                (
                    name,
                    email,
                    phone,
                    password,
                    role,
                    firebase_uid,
                    email_verified
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, 0
                )
            ");

            $inserted = $stmt->execute([
                $fullname,
                $email,
                $fullPhone,
                $hashedPassword,
                $role,
                $firebase_uid
            ]);

            if (!$inserted) {
                set_auth_error("register", "Your account could not be saved. Please try again.");
            }
        } catch (PDOException $e) {
            set_auth_error(
                "register",
                "Database registration failed: " . $e->getMessage()
            );
        }

        set_auth_success(
            "login",
            "Registration successful! A verification email has been sent. Please verify your email before logging in."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    if (isset($_POST["login"])) {

        $email = trim($_POST["email"] ?? "");
        $password = trim($_POST["password"] ?? "");

        if (empty($email) || empty($password)) {
            set_auth_error("login", "Please enter your email and password.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_auth_error("login", "Please enter a valid email address.");
        }

        // FIND MYSQL USER
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            set_auth_error("login", "Login failed. Please try again.");
        }

        if (!$user) {
            set_auth_error("login", "No account found with that email.");
        }

        // VERIFY MYSQL PASSWORD
        if (!verify_password($password, $user["password"])) {
            set_auth_error("login", "Incorrect password.");
        }

        // FIREBASE LOGIN
        try {
            $firebaseLogin = firebase_request(
                FIREBASE_SIGNIN_URL,
                [
                    "email" => $email,
                    "password" => $password,
                    "returnSecureToken" => true
                ]
            );
        } catch (Exception $e) {
            set_auth_error("login", "Authentication service unavailable. Please try again.");
        }

        if (!$firebaseLogin["success"] || isset($firebaseLogin["data"]["error"])) {
            set_auth_error("login", firebase_error_message($firebaseLogin));
        }

        $firebaseLoginData = $firebaseLogin["data"] ?? [];
        $idToken = $firebaseLoginData["idToken"] ?? "";
        $firebase_uid = $firebaseLoginData["localId"] ?? "";

        if (empty($idToken)) {
            set_auth_error("login", "Unable to authenticate your account.");
        }

        // CHECK FIREBASE EMAIL VERIFICATION
        try {
            $accountLookup = firebase_request(
                FIREBASE_LOOKUP_URL,
                ["idToken" => $idToken]
            );
        } catch (Exception $e) {
            set_auth_error("login", "Unable to verify your email status.");
        }

        if (!$accountLookup["success"] || isset($accountLookup["data"]["error"])) {
            set_auth_error("login", "Unable to verify your account. Please try again.");
        }

        $firebaseUser = $accountLookup["data"]["users"][0] ?? null;
        $emailVerified = false;

        if ($firebaseUser) {
            $emailVerified = isset($firebaseUser["emailVerified"]) && $firebaseUser["emailVerified"] === true;
        }

        if (!$emailVerified) {
            set_auth_error(
                "login",
                "Your email address has not been verified. Please check your email and click the verification link before logging in."
            );
        }

        // UPDATE MYSQL EMAIL VERIFICATION
        try {
            $updateVerification = $db->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
            $updateVerification->execute([$user["id"]]);
        } catch (PDOException $e) {
            // ignore
        }

        // UPDATE FIREBASE UID IF MISSING
        if (!empty($firebase_uid) && empty($user["firebase_uid"])) {
            try {
                $updateFirebaseUid = $db->prepare("UPDATE users SET firebase_uid = ? WHERE id = ?");
                $updateFirebaseUid->execute([$firebase_uid, $user["id"]]);
            } catch (PDOException $e) {
                // Do not block login.
            }
        }

        // SECURE SESSION
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["firebase_uid"] = $firebase_uid ?: ($user["firebase_uid"] ?? "");
        $_SESSION["email_verified"] = true;

        redirect_by_role($user["role"]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| READ FLASH MESSAGES FROM SESSION
|--------------------------------------------------------------------------
*/

$activeForm = $_SESSION["active_form"] ?? "login";

$error = [
    "login" => $_SESSION["login_error"] ?? "",
    "register" => $_SESSION["register_error"] ?? ""
];

$success = $_SESSION["login_success"] ?? "";

// clear them after reading
unset(
    $_SESSION["login_error"],
    $_SESSION["register_error"],
    $_SESSION["login_success"]
);

/*
|--------------------------------------------------------------------------
| HTML HELPERS
|--------------------------------------------------------------------------
*/

function isActiveForm($form, $activeForm)
{
    return $form === $activeForm;
}

function showError($message)
{
    if (empty($message)) {
        return "";
    }
    return "<p class='error-message'><i class=\"fa-solid fa-circle-exclamation\"></i> " . htmlspecialchars($message) . "</p>";
}

function showSuccess($message)
{
    if (empty($message)) {
        return "";
    }
    return "<p class='success-message'><i class=\"fa-solid fa-circle-check\"></i> " . htmlspecialchars($message) . "</p>";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login & Register | SFS</title>
    <link rel="icon" type="image/png" href="/files/SFS-LOGO.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <meta name="description" content="Get access to reliable digital banking, financial partner, and technology-driven banking services." />
    <meta name="keywords" content="Senoamadi bank, loan, Investment, technology-driven banking services, secure future, Judge" />
    <meta name="author" content="Judge Tshwarelo" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>

    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    :root{
        --gold:#D4AF37;
        --gold-light:#F7D76A;
        --dark:#040B16;
        --navy:#08182E;
        --navy-2:#0A1A2F;
        --white:#ffffff;
        --danger:#ff6b6b;
        --success:#5be08a;
    }

    *{
        margin:0;
        padding:0;
        box-sizing:border-box;
        font-family:'Inter',sans-serif;
    }

    html{
        scroll-behavior:smooth;
    }

    body{
        min-height:100vh;
        color:white;
        overflow-x:hidden;

        display:flex;
        align-items:center;
        justify-content:center;

        padding:40px 20px;
        position:relative;

        background:
        radial-gradient(circle at top right, rgba(212,175,55,.14), transparent 30%),
        radial-gradient(circle at bottom left, rgba(0,140,255,.14), transparent 38%),
        linear-gradient(135deg, #040B16, #071427, #020913);
    }

    /* ===== floating ambient orbs (desktop "alive" background) ===== */

    .bg-orb{
        position:fixed;
        border-radius:50%;
        filter:blur(70px);
        opacity:.55;
        z-index:0;
        pointer-events:none;
    }

    .bg-orb.o1{
        width:420px;
        height:420px;
        top:-120px;
        left:-100px;
        background:radial-gradient(circle, rgba(212,175,55,.35), transparent 70%);
        animation:floatSlow 14s ease-in-out infinite;
    }

    .bg-orb.o2{
        width:480px;
        height:480px;
        bottom:-160px;
        right:-140px;
        background:radial-gradient(circle, rgba(0,140,255,.28), transparent 70%);
        animation:floatSlow 18s ease-in-out infinite reverse;
    }

    .bg-orb.o3{
        width:260px;
        height:260px;
        top:40%;
        right:6%;
        background:radial-gradient(circle, rgba(247,215,106,.25), transparent 70%);
        animation:floatSlow 11s ease-in-out infinite;
        animation-delay:-4s;
    }

    @keyframes floatSlow{
        0%,100%{ transform:translate(0,0) scale(1); }
        50%{ transform:translate(30px,-25px) scale(1.08); }
    }

    /* ===== container ===== */

    .container{
        position:relative;
        z-index:1;

        background:rgba(8,20,40,.65);
        border:1px solid rgba(212,175,55,.18);
        border-radius:30px;

        backdrop-filter:blur(22px);
        -webkit-backdrop-filter:blur(22px);

        box-shadow:
        0 20px 60px rgba(0,0,0,.45),
        0 0 0 1px rgba(212,175,55,.05) inset;

        position:relative;
        overflow:hidden;

        width:920px;
        max-width:100%;
        min-height:560px;

        display:flex;
    }

    .container::before{
        content:"";
        position:absolute;
        inset:0;
        border-radius:30px;
        padding:1px;
        background:linear-gradient(135deg, rgba(212,175,55,.55), transparent 40%, transparent 60%, rgba(0,140,255,.35));
        -webkit-mask:linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite:xor;
        mask-composite:exclude;
        pointer-events:none;
        animation:borderGlow 6s linear infinite;
    }

    @keyframes borderGlow{
        0%,100%{ opacity:.55; }
        50%{ opacity:1; }
    }

    .forms-wrap{
        position:relative;
        width:60%;
        min-height:100%;
    }

    .container form{
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        padding:50px 55px;
        height:100%;
        min-height:560px;
        text-align:center;
    }

    .eyebrow{
        display:inline-flex;
        align-items:center;
        gap:8px;

        padding:7px 16px;
        border-radius:50px;

        background:rgba(212,175,55,.08);
        border:1px solid rgba(212,175,55,.25);

        color:var(--gold-light);
        font-size:.72rem;
        font-weight:700;
        letter-spacing:1.2px;
        text-transform:uppercase;

        margin-bottom:18px;
    }

    .eyebrow i{
        color:var(--gold);
    }

    h1{
        font-size:2.1rem;
        font-weight:800;
        margin-bottom:6px;

        background:linear-gradient(90deg, white, var(--gold-light));
        -webkit-background-clip:text;
        -webkit-text-fill-color:transparent;
    }

    .subtext{
        color:rgba(255,255,255,.55);
        font-size:.88rem;
        margin-bottom:20px;
    }

    .social-icons{
        display:flex;
        gap:12px;
        margin:6px 0 18px;
    }

    .social-icons a{
        color:var(--gold);
        border:1px solid rgba(212,175,55,.35);
        background:rgba(212,175,55,.06);
        border-radius:14px;

        display:inline-flex;
        justify-content:center;
        align-items:center;

        width:44px;
        height:44px;

        font-size:1rem;

        transition:.3s;
    }

    .social-icons a:hover{
        background:var(--gold);
        color:var(--navy);
        transform:translateY(-3px);
        box-shadow:0 10px 20px rgba(212,175,55,.25);
    }

    .field{
        position:relative;
        width:100%;
        margin:8px 0;
    }

    .field i.field-icon{
        position:absolute;
        left:16px;
        top:50%;
        transform:translateY(-50%);
        color:rgba(212,175,55,.7);
        font-size:.9rem;
        pointer-events:none;
    }

    .container input,
    .container select{
        background:rgba(4,11,22,.55);
        border:1px solid rgba(255,255,255,.12);
        color:white;

        padding:13px 16px 13px 40px;
        font-size:13.5px;
        border-radius:12px;
        width:100%;
        outline:none;

        transition:.3s;
    }

    .container input::placeholder{
        color:rgba(255,255,255,.35);
    }

    .container input:focus,
    .container select:focus{
        border-color:var(--gold);
        background:rgba(4,11,22,.8);
        box-shadow:0 0 0 3px rgba(212,175,55,.14);
    }

    .container select{
        appearance:none;
        -webkit-appearance:none;
        cursor:pointer;

        background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D4AF37'><path d='M7 10l5 5 5-5z'/></svg>");
        background-repeat:no-repeat;
        background-position:right 14px center;
        background-size:18px;
        padding-left:40px;
    }

    .container select option{
        background:var(--navy);
        color:white;
    }

    .phone-row{
        display:flex;
        gap:10px;
        width:100%;
        margin:8px 0;
    }

    .phone-row .field{
        margin:0;
    }

    .phone-row input[readonly]{
        text-align:center;
        color:var(--gold-light);
        font-weight:700;
        background:rgba(212,175,55,.08);
        border-color:rgba(212,175,55,.25);
        padding-left:16px;
    }

    button[type="submit"],
    button[name="login"],
    button[name="register"]{
        position:relative;
        overflow:hidden;

        width:100%;
        height:46px;

        margin-top:14px;

        border:none;
        border-radius:12px;

        background:linear-gradient(90deg, var(--gold), var(--gold-light));
        color:var(--navy);

        font-size:14.5px;
        font-weight:800;
        letter-spacing:.4px;

        cursor:pointer;
        transition:.3s;
    }

    button[type="submit"]::after,
    button[name="login"]::after,
    button[name="register"]::after{
        content:"";
        position:absolute;
        top:0;
        left:-60%;
        width:40%;
        height:100%;
        background:linear-gradient(120deg, transparent, rgba(255,255,255,.55), transparent);
        transform:skewX(-20deg);
        transition:.6s;
    }

    button[type="submit"]:hover::after,
    button[name="login"]:hover::after,
    button[name="register"]:hover::after{
        left:130%;
    }

    button[type="submit"]:hover,
    button[name="login"]:hover,
    button[name="register"]:hover{
        transform:translateY(-3px);
        box-shadow:0 14px 30px rgba(212,175,55,.3);
    }

    .btn-row{
        display:flex;
        gap:14px;
        width:100%;
        margin-top:14px;
    }

    .btn-row button[type="button"]{
        width:100%;
        height:46px;

        border-radius:12px;
        border:1.5px solid rgba(255,255,255,.2);

        background:transparent;
        color:rgba(255,255,255,.85);

        font-size:13.5px;
        font-weight:700;

        cursor:pointer;
        transition:.3s;
    }

    .btn-row button[type="button"]:hover{
        border-color:var(--gold);
        color:var(--gold-light);
        background:rgba(212,175,55,.06);
    }

    .switch-line{
        margin-top:16px;
        font-size:.85rem;
        color:rgba(255,255,255,.55);
    }

    .switch-line a{
        color:var(--gold-light);
        font-weight:700;
        text-decoration:none;
        border-bottom:1px solid transparent;
        transition:.3s;
    }

    .switch-line a:hover{
        border-color:var(--gold-light);
    }

    .error-message,
    .success-message{
        display:flex;
        align-items:center;
        gap:8px;
        justify-content:center;

        border-radius:10px;
        padding:11px 14px;
        margin-bottom:14px;

        text-align:center;
        width:100%;
        font-size:12.5px;
        font-weight:600;

        animation:shake .45s ease;
    }

    .error-message{
        background:rgba(255,107,107,.1);
        border:1px solid rgba(255,107,107,.35);
        color:var(--danger);
    }

    .success-message{
        background:rgba(91,224,138,.1);
        border:1px solid rgba(91,224,138,.35);
        color:var(--success);
    }

    @keyframes shake{
        0%,100%{ transform:translateX(0); }
        20%{ transform:translateX(-6px); }
        40%{ transform:translateX(6px); }
        60%{ transform:translateX(-4px); }
        80%{ transform:translateX(4px); }
    }

    /* ===== form panels (desktop split) ===== */

    .form-container{
        position:absolute;
        top:0;
        left:0;
        width:100%;
        height:100%;
    }

    .active{
        display:block;
        animation:fadeIn .5s ease;
    }

    .inactive{
        display:none;
    }

    @keyframes fadeIn{
        from{ opacity:0; transform:translateY(14px); }
        to{ opacity:1; transform:translateY(0); }
    }

    /* ===== gold toggle side panel (desktop, "alive") ===== */

    .toggle-container{
        position:relative;
        width:40%;

        display:flex;
        align-items:center;
        justify-content:center;

        background:
        radial-gradient(circle at 30% 20%, rgba(212,175,55,.22), transparent 55%),
        linear-gradient(160deg, var(--navy-2), #050e1e 80%);

        border-left:1px solid rgba(212,175,55,.15);

        overflow:hidden;
    }

    .toggle-particle{
        position:absolute;
        border-radius:50%;
        background:var(--gold);
        opacity:.55;
        box-shadow:0 0 12px var(--gold);
        animation:drift 7s ease-in-out infinite;
    }

    .toggle-particle:nth-child(1){ width:8px; height:8px; left:20%; top:70%; animation-delay:0s; }
    .toggle-particle:nth-child(2){ width:5px; height:5px; left:65%; top:25%; animation-delay:1.5s; }
    .toggle-particle:nth-child(3){ width:6px; height:6px; left:80%; top:65%; animation-delay:3s; }
    .toggle-particle:nth-child(4){ width:4px; height:4px; left:35%; top:15%; animation-delay:4.5s; }

    @keyframes drift{
        0%,100%{ transform:translateY(0) scale(1); opacity:.3; }
        50%{ transform:translateY(-22px) scale(1.3); opacity:.9; }
    }

    .toggle{
        position:relative;
        z-index:2;

        display:flex;
        flex-direction:column;
        align-items:center;
        text-align:center;

        padding:40px 30px;
    }

    /* ===== LOGO PLACEHOLDER (replaces toggle-icon) ===== */
    .toggle-logo{
        width:110px;
        height:110px;

        display:flex;
        align-items:center;
        justify-content:center;

        border-radius:50%;           /* circular */
        overflow:hidden;             /* crop image to circle */

        margin-bottom:22px;

        background:linear-gradient(135deg, rgba(212,175,55,.25), rgba(212,175,55,.05));
        border:2px solid rgba(212,175,55,.4);

        box-shadow:0 0 0 6px rgba(212,175,55,.08), 0 12px 30px rgba(0,0,0,.35);

        animation:iconFloat 4s ease-in-out infinite;
    }

    .toggle-logo img{
        width:100%;
        height:100%;
        object-fit:cover;            /* fills the circle neatly */
        display:block;
    }

    @keyframes iconFloat{
        0%,100%{ transform:translateY(0) rotate(0deg); }
        50%{ transform:translateY(-8px) rotate(4deg); }
    }

    .toggle h1{
        font-size:1.7rem;
        margin-bottom:10px;
    }

    .toggle p{
        color:rgba(255,255,255,.68);
        font-size:.9rem;
        line-height:1.7;
        max-width:260px;
    }

    /* ===== mobile tab switcher (hidden on desktop) ===== */

    .tab-switcher{
        display:none;
    }

    /* ===================================================
       RESPONSIVE
    =================================================== */

    @media (max-width:900px){

        .container{
            width:520px;
        }

        .toggle p{
            max-width:220px;
        }
    }

    @media (max-width:768px){

        body{
            padding:0;
            align-items:flex-start;
            min-height:100vh;
        }

        .bg-orb{
            filter:blur(50px);
            opacity:.4;
        }

        .container{
            width:100%;
            max-width:100%;
            min-height:100vh;
            border-radius:0;
            flex-direction:column;
            box-shadow:none;
            border:none;
        }

        .container::before{
            display:none;
        }

        /* toggle side panel becomes a compact top banner on mobile */
        .toggle-container{
            width:100%;
            order:-1;
            padding:28px 20px 20px;
            border-left:none;
            border-bottom:1px solid rgba(212,175,55,.15);
        }

        .toggle{
            padding:0;
            flex-direction:row;
            gap:16px;
            text-align:left;
        }

        .toggle-logo{
            width:64px;
            height:64px;
            margin-bottom:0;
            flex-shrink:0;
            border-width:2px;
        }

        .toggle h1{
            font-size:1.15rem;
            margin-bottom:2px;
        }

        .toggle p{
            font-size:.78rem;
            max-width:none;
        }

        /* tab switcher replaces the invisible split-panel interaction on mobile */
        .tab-switcher{
            display:flex;
            gap:8px;

            margin:18px 20px 0;
            padding:6px;

            background:rgba(4,11,22,.6);
            border:1px solid rgba(255,255,255,.1);
            border-radius:14px;
        }

        .tab-switcher button{
            flex:1;

            padding:11px 0;
            border:none;
            border-radius:10px;

            background:transparent;
            color:rgba(255,255,255,.6);

            font-size:13px;
            font-weight:700;
            letter-spacing:.3px;

            cursor:pointer;
            transition:.3s;
        }

        .tab-switcher button.tab-active{
            background:linear-gradient(90deg, var(--gold), var(--gold-light));
            color:var(--navy);
            box-shadow:0 8px 20px rgba(212,175,55,.25);
        }

        .forms-wrap{
            width:100%;
            flex:1;
        }

        .form-container{
            position:relative;
        }

        .container form{
            padding:26px 22px 40px;
            min-height:auto;
            height:auto;
        }

        h1{
            font-size:1.6rem;
        }

        .eyebrow{
            font-size:.66rem;
            padding:6px 13px;
        }

        .social-icons a{
            width:40px;
            height:40px;
        }

        .btn-row{
            flex-direction:column;
        }
    }

    @media (max-width:420px){

        .container form{
            padding:22px 16px 34px;
        }

        h1{
            font-size:1.4rem;
        }

        .container input,
        .container select{
            font-size:13px;
            padding:12px 14px 12px 38px;
        }

        button[type="submit"],
        button[name="login"],
        button[name="register"],
        .btn-row button[type="button"]{
            font-size:13px;
            height:44px;
        }

        .phone-row{
            flex-wrap:nowrap;
        }

        .toggle-logo{
            width:54px;
            height:54px;
        }
    }

    </style>
</head>

<body>

    <div class="bg-orb o1"></div>
    <div class="bg-orb o2"></div>
    <div class="bg-orb o3"></div>

    <div class="container" id="content">

        <div class="forms-wrap">

            <!-- MOBILE TAB SWITCHER -->
            <div class="tab-switcher">
                <button type="button" id="tab-login" class="<?= isActiveForm('login', $activeForm) ? 'tab-active' : ''; ?>" onclick="showForm('login-form')">Login</button>
                <button type="button" id="tab-register" class="<?= isActiveForm('register', $activeForm) ? 'tab-active' : ''; ?>" onclick="showForm('register-form')">Register</button>
            </div>

            <!-- LOGIN FORM -->
            <div class="form-container <?= isActiveForm('login', $activeForm) ? 'active' : 'inactive'; ?>" id="login-form">
                <form method="post" action="login.php">

                    <h1>Welcome Back</h1>
                    <p class="subtext">Login to continue</p>

                    <?= showError($error['login']); ?>
                    <?= showSuccess($success); ?>

                    <div class="social-icons">
                        <a href="#" aria-label="Google"><i class="fab fa-google"></i></a>
                        <a href="#" aria-label="Facebook"><i class="fab fa-apple"></i></a>
                        <a href="#" aria-label="Microsoft"><i class="fab fa-microsoft"></i></a>
                    </div>

                    <div class="field">
                        <i class="fa-solid fa-envelope field-icon"></i>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>

                    <div class="field">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="login">Login</button>
                        <button type="button" onclick="window.location.href='index.html'">Home</button>
                    </div>

                    <p class="switch-line">Don't have an account?
                        <a href="#" onclick="showForm('register-form'); return false;">Register</a>
                    </p>
                </form>
            </div>

            <!-- REGISTER FORM -->
            <div class="form-container <?= isActiveForm('register', $activeForm) ? 'active' : 'inactive'; ?>" id="register-form">
                <form method="post" action="login.php">

                    <h1>Create Account</h1>
                    <p class="subtext">Register to access loans, investments & more</p>

                    <?= showError($error['register']); ?>

                    <div class="field">
                        <i class="fa-solid fa-user field-icon"></i>
                        <input type="text" name="name" placeholder="Name" required>
                    </div>

                    <div class="field">
                        <i class="fa-solid fa-envelope field-icon"></i>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>

                    <div class="phone-row">
                        <div class="field" style="width:28%;">
                            <input type="text" name="country_code" value="+27" readonly>
                        </div>
                        <div class="field" style="width:72%;">
                            <i class="fa-solid fa-mobile-screen field-icon"></i>
                            <input type="text" name="phone" placeholder="Cellphone Number" pattern="[0-9]{9}" maxlength="9" required>
                        </div>
                    </div>

                    <div class="field">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="field">
                        <i class="fa-solid fa-id-badge field-icon"></i>
                        <select name="role" required>
                            <option value="">--Select Role--</option>
                            <option value="user">User</option>
                        </select>
                    </div>

                    <button type="submit" name="register">Register</button>

                    <p class="switch-line">Already have an account?
                        <a href="#" onclick="showForm('login-form'); return false;">Login</a>
                    </p>
                </form>
            </div>

        </div>

        <!-- GOLD TOGGLE SIDE -->
        <div class="toggle-container">
            <div class="toggle-particle"></div>
            <div class="toggle-particle"></div>
            <div class="toggle-particle"></div>
            <div class="toggle-particle"></div>

            <div class="toggle">

                <!-- REPLACED ICON WITH YOUR CIRCULAR LOGO -->
                <div class="toggle-logo">
                    <img src="/files/SFS-LOGO.png" alt="Senoamadi Bank Logo">
                </div>

                <h1>Senoamadi Financial Services</h1>
            </div>
        </div>

    </div>

    <script>
        function showForm(formId) {
            document.getElementById('login-form').classList.add('inactive');
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.add('inactive');
            document.getElementById('register-form').classList.remove('active');

            document.getElementById(formId).classList.remove('inactive');
            document.getElementById(formId).classList.add('active');

            document.getElementById('tab-login').classList.remove('tab-active');
            document.getElementById('tab-register').classList.remove('tab-active');

            if (formId === 'login-form') {
                document.getElementById('tab-login').classList.add('tab-active');
            } else {
                document.getElementById('tab-register').classList.add('tab-active');
            }
        }
    </script>
</body>

</html>