<?php
session_start();
require_once 'config.php';

// ======================================================
// 🔐 LOGIN GUARD — accepts either session key
// ======================================================
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (empty($user_id) || empty($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $user_id;

// ======================================================
// 🗄️ Use the same PDO connection as the rest of the app
// ======================================================
$db = get_db();

// Fetch user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Session pointed to a user that no longer exists — force logout
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// ======================================================
// ✏️ Handle profile update
// ======================================================
$message = '';
$messageType = '';

if (isset($_POST['update_profile'])) {
    $name        = trim($_POST['name'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $profile_pic = $user['profile_pic'];

    // ----- Handle image upload -----
    if (!empty($_FILES['profile_pic']['name'])) {
        $upload_dir = "uploads/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_name   = basename($_FILES["profile_pic"]["name"]);
        $target_file = $upload_dir . time() . "_" . $file_name;
        $ext         = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed     = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                $profile_pic = $target_file;
            } else {
                $message = "Failed to upload image.";
                $messageType = 'error';
            }
        } else {
            $message = "Invalid file type. Only JPG, JPEG, PNG, GIF allowed.";
            $messageType = 'error';
        }
    }

    // ----- Update DB (PDO) -----
    if (empty($message)) {
        try {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name = ?, password = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$name, $hashed_password, $profile_pic, $user_id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$name, $profile_pic, $user_id]);
            }

            $_SESSION['name']      = $name;
            $_SESSION['fullname']  = $name;
            $_SESSION['success']   = "Profile updated successfully!";
            header("Location: profile.php");
            exit();

        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PROFILE | SFS</title>
  <meta name="description" content="Get access to reliable digital banking, financial partner, and technology-driven banking services." />
  <meta name="keywords" content="Senoamadi bank, loan, Investment, technology-driven banking services, secure future, Judge" />
  <meta name="author" content="Judge Tshwarelo" />
  <link rel="icon" type="image/png" href="SFS-LOGO.png" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    :root {
      --gold: #D4AF37;
      --gold-light: #F7D76A;
      --dark: #040B16;
      --navy: #08182E;
      --blue: #3a7bd5;
      --light: #74b9ff;
      --white: #ffffff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    html { scroll-behavior: smooth; }

    body {
      color: white;
      overflow-x: hidden;
      min-height: 100vh;

      background:
        radial-gradient(circle at top right, rgba(212, 175, 55, .12), transparent 25%),
        radial-gradient(circle at bottom left, rgba(0, 140, 255, .12), transparent 35%),
        linear-gradient(135deg, #040B16, #071427, #020913);
    }

    a { text-decoration: none; }

    /* ======================================================
       SIDEBAR NAVIGATION
    ====================================================== */
    nav {
      position: fixed;
      top: 0;
      left: 0;
      width: 240px;
      height: 100vh;
      z-index: 1000;
      padding: 28px 18px;
      display: flex;
      flex-direction: column;
      align-items: center;
      overflow-y: auto;
      transition: .3s;

      background: rgba(4, 11, 22, .82);
      backdrop-filter: blur(25px);
      border-right: 1px solid rgba(255, 255, 255, .06);
    }

    nav::before {
      content: "";
      position: absolute;
      top: 0;
      right: 0;
      width: 1px;
      height: 100%;
      background: linear-gradient(180deg, transparent, rgba(212, 175, 55, .6), transparent);
    }

    nav::-webkit-scrollbar { width: 4px; }
    nav::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }

    .logo {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
      margin-bottom: 32px;
      cursor: pointer;
      text-align: center;
    }

    .logo-orbit {
      position: relative;
      width: 68px;
      height: 68px;
    }

    .logo-orbit img {
      width: 100%;
      height: 100%;
      border-radius: 18px;
      object-fit: cover;
      position: relative;
      z-index: 2;
    }

    .orbit-ring {
      position: absolute;
      inset: -8px;
      border-radius: 50%;
      border: 1.5px solid rgba(212, 175, 55, .4);
      animation: spin 10s linear infinite;
    }

    .orbit-ring::before {
      content: "";
      position: absolute;
      width: 10px;
      height: 10px;
      background: var(--gold);
      border-radius: 50%;
      top: -5px;
      left: 50%;
      transform: translateX(-50%);
      box-shadow: 0 0 12px var(--gold), 0 0 25px var(--gold);
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }

    .logo span {
      font-size: .85rem;
      font-weight: 700;
      letter-spacing: .5px;
      color: var(--gold-light);
    }

    .menu-links {
      display: flex;
      flex-direction: column;
      gap: 8px;
      width: 100%;
    }

    .menu-item {
      width: 100%;
      padding: 13px 15px;
      border-radius: 14px;
      color: rgba(255, 255, 255, .75);
      display: flex;
      align-items: center;
      gap: 14px;
      transition: .3s;
      font-weight: 500;
      font-size: .9rem;
      cursor: pointer;
      position: relative;
    }

    .menu-item i { font-size: 20px; }

    .menu-item:hover {
      background: rgba(255, 255, 255, .06);
      color: white;
      transform: translateX(4px);
    }

    .menu-item.active {
      background: linear-gradient(135deg, rgba(212, 175, 55, .25), rgba(212, 175, 55, .08));
      color: var(--gold-light);
      border: 1px solid rgba(212, 175, 55, .35);
    }

    .menu-item.active::before {
      content: "";
      position: absolute;
      left: -18px;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 60%;
      background: var(--gold);
      border-radius: 0 4px 4px 0;
      box-shadow: 0 0 12px var(--gold);
    }

    .logout-btn { margin-top: 10px; color: rgba(255, 120, 120, .85); }
    .logout-btn:hover { background: rgba(192, 57, 43, .2); color: #ff6b6b; }

    /* ======================================================
       MAIN CONTENT
    ====================================================== */
    main {
      margin-left: 240px;
      width: calc(100% - 240px);
      min-height: 100vh;
      padding: 40px 50px 80px;
      transition: .3s;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 36px;
      padding-bottom: 26px;
      border-bottom: 1px solid rgba(255, 255, 255, .07);
      position: relative;
    }

    .page-header::after {
      content: "";
      position: absolute;
      bottom: -1px;
      left: 0;
      width: 160px;
      height: 2px;
      background: linear-gradient(90deg, var(--gold), transparent);
    }

    .user-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(212, 175, 55, .12);
      color: var(--gold-light);
      padding: 6px 16px;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      margin-bottom: 14px;
      border: 1px solid rgba(212, 175, 55, .3);
    }

    .page-header h1 {
      font-size: clamp(1.9rem, 3.5vw, 2.6rem);
      font-weight: 800;
      background: linear-gradient(90deg, white, var(--gold-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      line-height: 1.2;
    }

    .page-header p {
      margin-top: 10px;
      color: rgba(255, 255, 255, .6);
      font-size: 1rem;
      max-width: 560px;
    }

    /* ======================================================
       PROFILE LAYOUT
    ====================================================== */
    .profile-layout {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 30px;
      align-items: start;
    }

    .avatar-card {
      position: relative;
      overflow: hidden;
      padding: 34px 26px;
      border-radius: 22px;
      background: rgba(8, 20, 40, .65);
      border: 1px solid rgba(212, 175, 55, .2);
      text-align: center;
      transition: .35s;
    }

    .avatar-card::before {
      content: "";
      position: absolute;
      top: -60px;
      right: -60px;
      width: 180px;
      height: 180px;
      background: radial-gradient(circle, rgba(212, 175, 55, .16), transparent 70%);
      pointer-events: none;
    }

    .avatar-card:hover {
      border-color: rgba(212, 175, 55, .5);
      box-shadow: 0 20px 45px rgba(212, 175, 55, .12);
    }

    .avatar-wrap {
      position: relative;
      width: 130px;
      height: 130px;
      margin: 0 auto 20px;
    }

    .avatar-wrap img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      position: relative;
      z-index: 2;
      border: 3px solid rgba(212, 175, 55, .5);
      background: #0A1A2F;
    }

    .avatar-ring {
      position: absolute;
      inset: -10px;
      border-radius: 50%;
      border: 1.5px dashed rgba(212, 175, 55, .5);
      animation: spin 14s linear infinite;
    }

    .avatar-card h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: white;
      margin-bottom: 4px;
      word-break: break-word;
    }

    .avatar-card .email {
      font-size: .85rem;
      color: rgba(255, 255, 255, .55);
      word-break: break-all;
    }

    .avatar-card .role-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 16px;
      padding: 6px 14px;
      border-radius: 999px;
      background: rgba(212, 175, 55, .12);
      color: var(--gold-light);
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      border: 1px solid rgba(212, 175, 55, .3);
    }

    .avatar-card .meta {
      margin-top: 22px;
      padding-top: 22px;
      border-top: 1px solid rgba(255, 255, 255, .07);
      display: flex;
      flex-direction: column;
      gap: 12px;
      text-align: left;
    }

    .avatar-card .meta-row {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: .85rem;
      color: rgba(255, 255, 255, .7);
    }

    .avatar-card .meta-row i {
      color: var(--gold);
      font-size: 1rem;
    }

    .form-card {
      position: relative;
      overflow: hidden;
      padding: 36px 34px;
      border-radius: 22px;
      background: rgba(8, 20, 40, .65);
      border: 1px solid rgba(212, 175, 55, .2);
    }

    .form-card::before {
      content: "";
      position: absolute;
      top: 0;
      right: 0;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(58, 123, 213, .14), transparent 70%);
      transform: translate(30%, -30%);
      pointer-events: none;
    }

    .section-head {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 26px;
      position: relative;
      z-index: 2;
    }

    .section-head .accent {
      width: 5px;
      height: 28px;
      border-radius: 4px;
      background: linear-gradient(180deg, var(--gold), rgba(212, 175, 55, .2));
      box-shadow: 0 0 16px rgba(212, 175, 55, .5);
    }

    .section-head h2 {
      font-size: 1.3rem;
      font-weight: 700;
      color: white;
    }

    .field {
      position: relative;
      margin-bottom: 20px;
      z-index: 2;
    }

    .field label {
      display: block;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      color: rgba(255, 255, 255, .55);
      margin-bottom: 8px;
    }

    .field .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .field .input-wrap i {
      position: absolute;
      left: 16px;
      font-size: 18px;
      color: var(--gold);
      pointer-events: none;
      transition: .3s;
    }

    .field input[type="text"],
    .field input[type="email"],
    .field input[type="password"] {
      width: 100%;
      padding: 14px 16px 14px 48px;
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, .1);
      background: rgba(4, 11, 22, .6);
      color: white;
      font-size: .95rem;
      font-family: 'Inter', sans-serif;
      transition: .3s;
      outline: none;
    }

    .field input::placeholder {
      color: rgba(255, 255, 255, .35);
    }

    .field input:focus {
      border-color: var(--gold);
      background: rgba(4, 11, 22, .85);
      box-shadow: 0 0 0 4px rgba(212, 175, 55, .12);
    }

    .field input:focus + i,
    .field .input-wrap:focus-within i {
      color: var(--gold-light);
    }

    .field input[readonly] {
      color: rgba(255, 255, 255, .5);
      cursor: not-allowed;
      background: rgba(4, 11, 22, .4);
    }

    .file-upload {
      position: relative;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px;
      border-radius: 14px;
      border: 1.5px dashed rgba(212, 175, 55, .35);
      background: rgba(4, 11, 22, .5);
      cursor: pointer;
      transition: .3s;
    }

    .file-upload:hover {
      border-color: var(--gold);
      background: rgba(212, 175, 55, .05);
    }

    .file-upload i {
      font-size: 24px;
      color: var(--gold);
      flex-shrink: 0;
    }

    .file-upload .file-text {
      display: flex;
      flex-direction: column;
      gap: 2px;
      overflow: hidden;
    }

    .file-upload .file-text strong {
      font-size: .88rem;
      color: white;
      font-weight: 600;
    }

    .file-upload .file-text span {
      font-size: .72rem;
      color: rgba(255, 255, 255, .5);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .file-upload input[type="file"] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
      height: 100%;
    }

    .btn-submit {
      width: 100%;
      padding: 16px 24px;
      border-radius: 14px;
      border: none;
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      color: var(--navy);
      font-size: .95rem;
      font-weight: 800;
      letter-spacing: .8px;
      text-transform: uppercase;
      cursor: pointer;
      transition: .3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 8px;
      position: relative;
      z-index: 2;
      box-shadow: 0 10px 25px rgba(212, 175, 55, .25);
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 15px 35px rgba(212, 175, 55, .35);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    .btn-submit i { font-size: 20px; }

    .msg {
      padding: 14px 18px;
      border-radius: 14px;
      font-size: .88rem;
      font-weight: 600;
      margin-bottom: 22px;
      display: flex;
      align-items: center;
      gap: 10px;
      position: relative;
      z-index: 2;
    }

    .msg i { font-size: 20px; flex-shrink: 0; }

    .msg.success {
      background: rgba(46, 204, 113, .12);
      border: 1px solid rgba(46, 204, 113, .35);
      color: #2ecc71;
    }

    .msg.error {
      background: rgba(231, 76, 60, .12);
      border: 1px solid rgba(231, 76, 60, .35);
      color: #ff6b6b;
    }

    #chat-icon {
      position: fixed;
      bottom: 100px;
      right: 20px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      cursor: pointer;
      z-index: 999;
      background: radial-gradient(circle at 30% 30%, #fff6d8, var(--gold));
      color: var(--navy);
      box-shadow: 0 0 25px rgba(212, 175, 55, .5), 0 0 60px rgba(212, 175, 55, .25);
      animation: pulse 3s infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50%      { transform: scale(1.08); }
    }

    @media(max-width: 1024px) {
      main { padding: 30px 28px 100px; }
      .profile-layout { grid-template-columns: 1fr; }
      .avatar-card { max-width: 100%; }
    }

    @media(max-width: 768px) {
      body { padding-bottom: 95px; }

      nav {
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: 78px;
        padding: 0 10px;
        flex-direction: row;
        border-right: none;
        border-top: 1px solid rgba(255, 255, 255, .08);
        border-radius: 25px 25px 0 0;
        overflow: visible;
        justify-content: center;
      }

      nav::before { display: none; }

      .logo { display: none; }

      .menu-links {
        flex-direction: row;
        justify-content: space-around;
        align-items: center;
        gap: 0;
        height: 100%;
        width: 100%;
      }

      .menu-item {
        width: auto;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 3px;
        padding: 8px 4px;
        font-size: 10px;
        background: none !important;
        border: none !important;
        color: rgba(255, 255, 255, .6);
      }

      .menu-item.active::before { display: none; }

      .menu-item i { font-size: 20px; }
      .menu-item span { font-size: 9px; white-space: nowrap; }

      .menu-item.active { color: var(--gold-light); }
      .menu-item.active i {
        filter: drop-shadow(0 0 8px rgba(212, 175, 55, .8));
      }

      main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 22px 16px 110px !important;
      }

      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .form-card { padding: 24px 20px; }
      .avatar-card { padding: 28px 20px; }

      #chat-icon { display: flex; }
    }
  </style>
</head>

<body>

<!-- ======================================================
     SIDEBAR NAVIGATION
====================================================== -->
<nav>
    <div class="logo">
        <div class="logo-orbit">
            <div class="orbit-ring"></div>
            <img src="/files/SFS-LOGO.png" alt="Senoamadi Financial Services Logo" />
        </div>
        <span>Senoamadi FS</span>
    </div>

    <div class="menu-links">
        <a class="menu-item" href="user_page.php">
            <i class="bx bx-home-alt-2"></i>
            <span>HOME</span>
        </a>
        <a class="menu-item" href="track_money.php">
            <i class="bx bx-pulse"></i>
            <span>TRACK MONEY</span>
        </a>
        <a class="menu-item" href="personal.php">
            <i class="bx bx-wallet"></i>
            <span>PERSONAL</span>
        </a>
        <a class="menu-item" href="suggestion.php">
            <i class="bx bx-message-square-dots"></i>
            <span>SUGGESTIONS</span>
        </a>
        <a class="menu-item active" href="profile.php">
            <i class="bx bx-user-circle"></i>
            <span>PROFILE</span>
        </a>
        <a class="menu-item logout-btn" href="logout.php">
            <i class="bx bx-power-off"></i>
            <span>LOGOUT</span>
        </a>
    </div>
</nav>


  <main>

    <div class="page-header">
      <div>
        <h1>Profile Settings</h1>
        <p>Update your personal information, password, and profile picture.</p>
      </div>
    </div>

    <?php if (!empty($message)): ?>
      <div class="msg error">
        <i class="bx bx-error-circle"></i>
        <?= htmlspecialchars($message); ?>
      </div>
    <?php elseif (isset($_SESSION['success'])): ?>
      <div class="msg success">
        <i class="bx bx-check-circle"></i>
        <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <div class="profile-layout">

      <div class="avatar-card">
        <div class="avatar-wrap">
          <div class="avatar-ring"></div>
          <img
            src="<?= !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'uploads/default.png'; ?>"
            alt="Profile Picture"
          >
        </div>

        <h3><?= htmlspecialchars($user['name'] ?? 'User'); ?></h3>
        <p class="email"><?= htmlspecialchars($user['email'] ?? ''); ?></p>

        <span class="role-pill">
          <i class="bx bx-shield-quarter"></i> Verified Member
        </span>

        <div class="meta">
          <div class="meta-row">
            <i class="bx bx-id-card"></i>
            <span>ID: #<?= str_pad((string) $user_id, 6, '0', STR_PAD_LEFT); ?></span>
          </div>
          <div class="meta-row">
            <i class="bx bx-envelope"></i>
            <span><?= htmlspecialchars($user['email'] ?? ''); ?></span>
          </div>
        </div>
      </div>

      <div class="form-card">

        <div class="section-head">
          <div class="accent"></div>
          <h2>Edit Information</h2>
        </div>

        <form method="POST" enctype="multipart/form-data">

          <div class="field">
            <label for="name">Full Name</label>
            <div class="input-wrap">
              <i class="bx bx-user"></i>
              <input
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars($user['name'] ?? ''); ?>"
                placeholder="Your full name"
                required
              >
            </div>
          </div>

          <div class="field">
            <label for="email">Email Address <span style="color:var(--gold);">(locked)</span></label>
            <div class="input-wrap">
              <i class="bx bx-envelope"></i>
              <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($user['email'] ?? ''); ?>"
                readonly
              >
            </div>
          </div>

          <div class="field">
            <label for="password">New Password <span style="color:rgba(255,255,255,.4);">(leave blank to keep current)</span></label>
            <div class="input-wrap">
              <i class="bx bx-lock-alt"></i>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
              >
            </div>
          </div>

          <div class="field">
            <label>Profile Picture</label>
            <label class="file-upload" for="profile_pic">
              <i class="bx bx-cloud-upload"></i>
              <div class="file-text">
                <strong>Choose an image</strong>
                <span>JPG, JPEG, PNG, or GIF · Max recommended 2MB</span>
              </div>
              <input
                type="file"
                id="profile_pic"
                name="profile_pic"
                accept="image/*"
                onchange="updateFileName(this)"
              >
            </label>
          </div>

          <button type="submit" name="update_profile" class="btn-submit">
            <i class="bx bx-save"></i> Save Changes
          </button>

        </form>
      </div>

    </div>

  </main>

  <script>
    function updateFileName(input) {
      const fileText = input.closest('.file-upload').querySelector('.file-text strong');
      if (input.files && input.files[0]) {
        fileText.textContent = input.files[0].name;
      }
    }
  </script>

</body>

</html>