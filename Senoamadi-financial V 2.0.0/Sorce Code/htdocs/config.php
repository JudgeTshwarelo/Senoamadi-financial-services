<?php
// ======================================================
// 🔐 START SESSION
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ======================================================
// ⚠️ ERROR REPORTING (disable on production)
// ======================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ======================================================
// 🗄️ BANK DATABASE CONFIGURATION
// ======================================================

define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// ======================================================
// 🔌 PDO DATABASE CONNECTION
// ======================================================

function get_db()
{
    static $db = null;

    if ($db !== null) {
        return $db;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $db = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        die("DATABASE CONNECTION FAILED: " . $e->getMessage());
    }

    return $db;
}

// ======================================================
// 🔥 FIREBASE CONFIGURATION
// ======================================================

define('FIREBASE_API_KEY', '');

define(
    'FIREBASE_SIGNUP_URL',
    'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . FIREBASE_API_KEY
);

define(
    'FIREBASE_SIGNIN_URL',
    'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . FIREBASE_API_KEY
);

define(
    'FIREBASE_VERIFY_EMAIL_URL',
    'https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=' . FIREBASE_API_KEY
);

define(
    'FIREBASE_LOOKUP_URL',
    'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . FIREBASE_API_KEY
);

// ======================================================
// 🌐 FIREBASE REQUEST HELPER
// ======================================================

function firebase_request($url, $payload)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => $error];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'message' => $result['error']['message'] ?? 'Firebase request failed',
            'data' => $result
        ];
    }

    return ['success' => true, 'data' => $result];
}

// ======================================================
// 🔐 AUTHENTICATION HELPERS
// ======================================================

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function current_role()
{
    return $_SESSION['role'] ?? 'user';
}

function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function logout_user()
{
    $_SESSION = [];

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

    session_destroy();
    header("Location: login.php");
    exit;
}

function redirect_by_role($role)
{
    switch ($role) {
        case 'admin':
            header("Location: admin_page.php");
            exit;
        case 'user':
        default:
            header("Location: user_page.php");
            exit;
    }
}

function current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    $db = get_db();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch();
}

function hash_password($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}