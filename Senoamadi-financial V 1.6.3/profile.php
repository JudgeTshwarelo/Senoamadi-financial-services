<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['id'])) {
  header("Location: index2.php");
  exit();
}

$user_id = $_SESSION['id'];

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
$message = '';
if (isset($_POST['update_profile'])) {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);
  $profile_pic = $user['profile_pic'];

  // Handle image upload
  if (!empty($_FILES['profile_pic']['name'])) {
    $upload_dir = "uploads/";
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_name = basename($_FILES["profile_pic"]["name"]);
    $target_file = $upload_dir . time() . "_" . $file_name;
    $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed)) {
      if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
        $profile_pic = $target_file;
      } else {
        $message = "Failed to upload image.";
      }
    } else {
      $message = "Invalid file type. Only JPG, JPEG, PNG, GIF allowed.";
    }
  }

  // Update query
  if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET name=?, password=?, profile_pic=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $hashed_password, $profile_pic, $user_id);
  } else {
    $stmt = $conn->prepare("UPDATE users SET name=?, profile_pic=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $profile_pic, $user_id);
  }

  if ($stmt->execute()) {
    $_SESSION['name'] = $name;
    $_SESSION['success'] = "Profile updated successfully!";
    header("Location: profile.php");
    exit();
  } else {
    $message = "Error updating profile.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Title that appears in search results -->
  <title>Profile | SENOAMADI_BANK</title>
  <!-- Short description that appears under the title in Google -->
  <meta name="description" content="Get access to reliable digital banking, financial partner, and technology-driven
            banking services." />
  <!-- Keywords (optional but still useful) -->
  <meta name="keywords" content="Senoamadi bank, loan, Investment, technology-driven
            banking services, secure future, Judge" />
  <!-- Author -->
  <meta name="author" content="Judge Tshwarelo" />
  <!-- Favicon (small icon that appears next to your site title) -->
  <link rel="icon" type="image/png" href="SENOAMADI_BANK.png" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

  <!-- Boxicons for menu icons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <style>
    .profile-container {
      max-width: 500px;
      margin: 40px auto;
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
      margin-top: 100px;
    }

    h2 {
      color: #002b5b;
      margin-bottom: 20px;
    }

    img.profile-pic {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px;
      border: 3px solid #7494ec;
    }

    input,
    button {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 15px;
    }

    button {
      background: #002b5b;
      color: #ffd700;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }

    button:hover {
      opacity: 0.9;
    }

    button.back-btn {
      background: #f33;
      margin-top: 10px;
    }

    .msg {
      font-weight: bold;
      margin: 10px 0;
    }

    .msg.success {
      color: green;
    }

    .msg.error {
      color: red;
    }



    :root {
      --gold: #D4AF37;
      --navy: #0A1A2F;
      --light-bg: #f4f7ff;
      --dark-bg: #1c1c1c;
    }

    body {
      background: var(--light-bg);
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      transition: 0.5s ease;
    }

    body.inverse {
      --gold: #0A1A2F;
      --navy: #D4AF37;
      background: var(--dark-bg);
      color: #eee;
    }

    /* Sidebar */
    nav {
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
      width: 220px;
      background: var(--navy);
      color: var(--gold);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding-top: 20px;
      z-index: 999;
      transition: 0.3s;
    }

    nav .logo img {
      width: 160px;
      border: 3px solid var(--gold);
      border-radius: 10px;
      margin-bottom: 20px;
    }

    nav .buttons {
      display: flex;
      flex-direction: column;
      width: 100%;
      align-items: center;
    }

    nav .buttons button {
      border: none;
      border-radius: 8px;
      background: var(--gold);
      color: var(--navy);
      font-weight: bold;
      font-size: 1em;
      cursor: pointer;
      margin: 8px 0;
      width: 80%;
      padding: 10px 0;
      transition: 0.3s;
    }

    nav .buttons button:hover {
      background: var(--navy);
      color: var(--gold);
    }

    .logout-btn {
      background: #f33;
      color: #fff;
    }

    .logout-btn:hover {
      background: #d22;
    }


    /* Main */
    main {
      margin-left: 220px;
      padding: 80px 20px;
    }

    .container {
      max-width: 900px;
      margin: 40px auto;
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    body.inverse .container {
      background: #2b2b2b;
      color: #eee;
    }

    h1,
    h2 {
      text-align: center;
      color: var(--navy);
    }

    /* Mobile menu toggle */
    .menu-toggle {
      display: none;
      /* keep hidden on desktop */
      position: fixed;
      top: 20px;
      left: 50%;
      /* <-- center horizontally */
      transform: translateX(-50%);
      /* <-- adjust to center properly */
      font-size: 28px;
      color: var(--gold);
      background: transparent;
      border: none;
      cursor: pointer;
      z-index: 2000;
    }



    /* Notifications */
    .notifications {
      background: #eef1ff;
      border-left: 5px solid var(--navy);
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      color: black;
    }

    /* Responsive Sidebar */
    @media (max-width: 768px) {
      nav {
        left: -220px;
      }

      nav.active {
        left: 0;
      }

      main {
        margin-left: 0;
      }

      .menu-toggle {
        display: block;
      }
    }

    /* Floating Chat Icon */
    #chat-icon {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: var(--gold);
      color: var(--navy);
      border-radius: 50%;
      width: 60px;
      height: 60px;
      display: none;
      justify-content: center;
      align-items: center;
      font-size: 30px;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
      z-index: 999;
    }

    /* Show only on phones */
    @media (max-width: 768px) {
      #chat-icon {
        display: flex;
      }
    }

    @media (max-width: 768px) {
      .menu-toggle {
        display: block;
      }
    }

    /* Hide mic button on desktop */
    @media (min-width: 769px) {
      #micBtn {
        display: none;
      }
    }
  </style>
</head>

<body>
  <button class="menu-toggle" onclick="toggleMenu()"><i class="bx bx-menu"></i></button>

  <nav id="sidebar">
    <div class="logo">
      <img src="SENOAMADI_BANK.png" alt="Logo">
    </div>
    <div class="buttons">
      <button onclick="window.location.href='user_page.php'">HOME</button>
      <button onclick="window.location.href='profile.php'">PROFILE</button>
      <button onclick="window.location.href='apply_loan.php'">LOANS</button>
      <button onclick="window.location.href='apply_investment.php'">INVEST</button>
      <button onclick="window.location.href='transfer.php'">TRANSFER</button>
      <button onclick="window.location.href='track_money.php'">TRACK MONEY</button>
      <button onclick="window.location.href='virtual_card.php'">CARD</button>
      <button class="logout-btn" onclick="window.location.href='logout.php'">LOG OUT</button>
      <button onclick="toggleInverse()">MODE</button>
    </div>
  </nav>

  <div class="profile-container">
    <h2>Profile Settings</h2>

    <?php if (!empty($message)): ?>
      <p class="msg error"><?= $message; ?></p>
    <?php elseif (isset($_SESSION['success'])): ?>
      <p class="msg success"><?= $_SESSION['success'];
                              unset($_SESSION['success']); ?></p>
    <?php endif; ?>

    <img src="<?= !empty($user['profile_pic']) ? $user['profile_pic'] : 'uploads/default.png'; ?>" class="profile-pic" alt="Profile Picture">

    <form method="POST" enctype="multipart/form-data">
      <label>Name:</label>
      <input type="text" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>

      <label>Email (cannot change):</label>
      <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" readonly>

      <label>Password (leave blank to keep current):</label>
      <input type="password" name="password" placeholder="New password">

      <label>Upload Profile Picture:</label>
      <input type="file" name="profile_pic" accept="image/*">

      <button type="submit" name="update_profile">Update Profile</button>
    </form>


  </div>
  <script>
    function toggleMenu() {
      document.getElementById("sidebar").classList.toggle("active");
    }

    function toggleInverse() {
      document.body.classList.toggle("inverse");
    }

    function toggleChat() {
      const chat = document.getElementById("ai-box");
      const icon = document.getElementById("chat-icon");
      chat.classList.toggle("active");
      icon.style.display = chat.classList.contains("active") ? "none" : "flex";
    }
  </script>
</body>

</html>