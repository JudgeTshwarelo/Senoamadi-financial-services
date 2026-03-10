<?php
session_start();
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
  header("Location: login.php");
  exit();
}

$email = $_SESSION['email'];

// Fetch user info
$query = $conn->prepare("SELECT id, name, created_at FROM users WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

if (!$user) {
  die("User not found.");
}

$user_id = $user['id'];
$name = strtoupper($user['name']);
$account_number = 'SB' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

// Calculate card valid date (4 years after registration)
$created_at = new DateTime($user['created_at']);
$valid_until = clone $created_at;
$valid_until->modify('+4 years');
$valid_until_sql = $valid_until->format('Y-m-d');

// Check if card exists
$checkCard = $conn->prepare("SELECT * FROM virtual_cards WHERE user_id = ?");
$checkCard->bind_param("i", $user_id);
$checkCard->execute();
$existingCard = $checkCard->get_result()->fetch_assoc();

if (!$existingCard) {
  $insert = $conn->prepare("INSERT INTO virtual_cards (user_id, account_number, valid_until, status) VALUES (?, ?, ?, 'active')");
  $insert->bind_param("iss", $user_id, $account_number, $valid_until_sql);
  $insert->execute();
  $account_number = $account_number;
  $valid_until_sql = $valid_until_sql;
} else {
  $account_number = $existingCard['account_number'];
  $valid_until_sql = $existingCard['valid_until'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Virtual Card | SENOAMADI BANK</title>
  <!-- Boxicons for menu icons -->
  <link rel="icon" type="image/png" href="SENOAMADI_BANK.png" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
  <style>
    body {
      background: #071022;
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 80vh;
      font-family: 'Poppins', sans-serif;
      padding: 20px;
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

    .menu-toggle {
      display: none;
      position: fixed;
      top: 20px;
      left: 20px;
      font-size: 28px;
      color: var(--gold);
      background: transparent;
      border: none;
      cursor: pointer;
      z-index: 2000;
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

    /* ===== CARD STYLES ===== */
    .card {
      width: 400px;
      height: 240px;
      background: linear-gradient(145deg, #0b1530, #11204a);
      border-radius: 20px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
      padding: 25px 20px;
      position: relative;
      overflow: hidden;
      color: #D4AF37;
      transition: all 0.3s ease;
    }

    .card.frozen {
      filter: grayscale(100%) brightness(0.6);
      opacity: 0.7;
    }

    /* Gold pattern overlay */
    .card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: repeating-linear-gradient(45deg,
          rgba(255, 215, 0, 0.08) 0px,
          rgba(255, 215, 0, 0.08) 2px,
          transparent 2px,
          transparent 8px);
      z-index: 0;
    }

    .logo {
      display: flex;
      justify-content: space-between;
      align-items: center;
      z-index: 1;
      position: relative;
    }

    .logo span {
      font-size: 1.3rem;
      font-weight: 600;
      color: #D4AF37;
    }

    .logo .circle {
      width: 50px;
      height: 50px;
      border: 2px solid #D4AF37;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1.4rem;
      box-shadow: 0 0 8px #D4AF37;
    }

    .chip {
      width: 55px;
      height: 40px;
      background: linear-gradient(135deg, #D4AF37);
      border-radius: 8px;
      margin-top: 25px;
      box-shadow: inset 0 0 8px rgba(255, 255, 255, 0.3);
    }

    .number {
      font-size: 1.4rem;
      letter-spacing: 3px;
      margin: 25px 0 15px;
      color: #D4AF37;
      font-weight: 600;
      z-index: 1;
      position: relative;
      text-shadow: 0 0 6px #D4AF37;
    }

    .name {
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 1px;
      color: #D4AF37;
      position: relative;
      z-index: 1;
    }

    .valid {
      position: absolute;
      bottom: 25px;
      right: 30px;
      color: #D4AF37;
      font-size: 0.9rem;
      z-index: 1;
    }

    /* ===== Buttons ===== */
    button,
    a.action-btn {
      margin-top: 20px;
      background: #D4AF37;
      border: none;
      padding: 10px 25px;
      border-radius: 8px;
      color: #0b1530;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }

    button:hover,
    a.action-btn:hover {
      background: #0b1530;
      color: #D4AF37;
    }

    .freeze-btn {
      background: crimson !important;
      color: white !important;
    }

    .unfreeze-btn {
      background: limegreen !important;
      color: white !important;
    }

    /* Toast notification */
    .toast {
      position: fixed;
      bottom: 25px;
      right: 25px;
      background: #0A1A2F;
      color: #D4AF37;
      padding: 15px 25px;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(212, 175, 55, 0.3);
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.4s ease;
      z-index: 9999;
    }

    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }

    /* ===== Responsive ===== */
    @media (max-width: 768px) {
      .card {
        width: 90%;
        height: 200px;
        padding: 20px;
      }

      .number {
        font-size: 1.1rem;
      }
    }

    h2 {
      font-size: 30px;
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

  <?php
  // Fetch user's virtual card
  $cardQuery = $conn->prepare("SELECT * FROM virtual_cards WHERE user_id = ?");
  $cardQuery->bind_param("i", $user_id);
  $cardQuery->execute();
  $card = $cardQuery->get_result()->fetch_assoc();
  ?>

  <h2 style="color:#D4AF37; margin-bottom:20px; font: size 500px;">Virtual Card</h2>

  <?php if ($card): ?>
    <div id="user-card" class="card <?= $card['status'] === 'frozen' ? 'frozen' : ''; ?>">
      <div class="logo">
        <div class="circle">S</div>
        <span>SENOAMADI<br><strong>BANK</strong></span>
      </div>
      <div class="chip"></div>
      <div class="number"><?= chunk_split($card['account_number'], 4, ' ') ?></div>
      <div class="name"><?= strtoupper($name); ?></div>
      <div class="valid">VALID THRU <?= (new DateTime($card['valid_until']))->format('m/y'); ?></div>
    </div>

    <div style="margin-top: 25px;">
      <button onclick="downloadCard()">Download Card</button>
      <?php if ($card['status'] === 'active'): ?>
        <button class="freeze-btn" onclick="toggleCard('freeze')">Freeze Card</button>
      <?php else: ?>
        <button class="unfreeze-btn" onclick="toggleCard('unfreeze')">Unfreeze Card</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p>You don’t have a virtual card yet.</p>
  <?php endif; ?>

  <div class="toast" id="toast"></div>

  <script>
    function showToast(message) {
      const toast = document.getElementById('toast');
      toast.textContent = message;
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function toggleCard(action) {
      fetch('toggle_card.php?action=' + action, {
          method: 'GET'
        })
        .then(response => response.text())
        .then(data => {
          if (data.includes('success')) {
            showToast('Card ' + (action === 'freeze' ? 'Frozen ' : 'Unfrozen '));
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast('Error updating card');
          }
        })
        .catch(() => showToast('Network error'));
    }

    function downloadCard() {
      const card = document.getElementById("user-card");
      html2canvas(card).then(canvas => {
        const link = document.createElement('a');
        link.download = 'Senoamadi_Virtual_Card.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      });
    }

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