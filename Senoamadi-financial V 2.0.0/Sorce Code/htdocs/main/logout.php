<?php
// ======================================================
// 🔐 HARDENED LOGOUT
// ======================================================
session_start();

// 1. Clear all session variables
$_SESSION = [];

// 2. Delete the session cookie from the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_unset();
session_destroy();

// 4. Kill any leftover session cookie that may exist under a different name
//    (defensive — in case the session name was changed elsewhere)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// 5. Prevent the browser from caching this page or the previous page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// 6. Redirect to login
header("Location: login.php");
exit();
?>