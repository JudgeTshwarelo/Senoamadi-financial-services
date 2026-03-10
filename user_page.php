<?php

session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['id'])) {
  header("Location: index2.php");
  exit();
}

$uid = $_SESSION['id'];

// Fetch user balance
$balance_query = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$balance_query->bind_param("i", $uid);
$balance_query->execute();
$balance_result = $balance_query->get_result()->fetch_assoc();
$current_balance = $balance_result ? $balance_result['balance'] : 0.00;


// Loan summary
$user_pending_loans = 0;
$user_approved_loans = 0;
$user_rejected_loans = 0;
$loan_summary = $conn->query("SELECT status, COUNT(*) AS total FROM loan_applications WHERE user_id=$uid GROUP BY status");
if ($loan_summary && $loan_summary->num_rows > 0) {
  while ($row = $loan_summary->fetch_assoc()) {
    $status = strtolower($row['status']);
    if ($status == 'pending') $user_pending_loans = $row['total'];
    elseif ($status == 'approved') $user_approved_loans = $row['total'];
    elseif ($status == 'rejected') $user_rejected_loans = $row['total'];
  }
}

$loan_info = "You currently have $user_pending_loans pending, $user_approved_loans approved, and $user_rejected_loans rejected loan applications.";

// Fetch notifications
$notifications = $conn->query("
    SELECT * FROM loan_applications 
    WHERE user_id=$uid AND status!='pending'
    ORDER BY created_at DESC
");






// Fetch loan summary
$loan_summary = $conn->query("SELECT status, SUM(amount) AS total FROM loan_applications WHERE user_id=$uid GROUP BY status");
$loans = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_approved' => 0];
while ($row = $loan_summary->fetch_assoc()) {
  $status = strtolower($row['status']);
  $loans[$status] = $row['total'];
  if ($status == 'approved') $loans['total_approved'] = $row['total'];
}

// Fetch investment summary
$inv_summary = $conn->query("SELECT status, SUM(amount) AS total FROM investments WHERE user_id=$uid GROUP BY status");
$investments = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_approved' => 0];
while ($row = $inv_summary->fetch_assoc()) {
  $status = strtolower($row['status']);
  $investments[$status] = $row['total'];
  if ($status == 'approved') $investments['total_approved'] = $row['total'];
}

// Notifications: loans + investments
$notifications = $conn->query("
    SELECT 'Loan' AS type, amount, status, created_at FROM loan_applications WHERE user_id=$uid AND status!='pending'
    UNION
    SELECT 'Investment' AS type, amount, status, created_at FROM investments WHERE user_id=$uid AND status!='pending'
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Title that appears in search results -->
  <title>User Dashboard | SENOAMADI_BANK</title>
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

    /* AI Chat Box */
    #ai-box {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 320px;
      height: 420px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      display: none;
      flex-direction: column;
      overflow: hidden;
      z-index: 1000;
    }

    #ai-box.active {
      display: flex;
    }

    #ai-header {
      background: var(--navy);
      color: var(--gold);
      padding: 0.5rem;
      text-align: center;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    #ai-chat {
      flex: 1;
      padding: 0.5rem;
      overflow-y: auto;
      background: #f9f9f9;
      font-size: 0.9rem;
    }

    .msg {
      margin: 0.3rem 0;
      padding: 0.5rem;
      border-radius: 8px;
      max-width: 80%;
      word-wrap: break-word;
    }

    .user {
      background: #d1ecf1;
      align-self: flex-end;
    }

    .ai {
      background: #e2e3e5;
      align-self: flex-start;
    }

    #ai-controls {
      padding: 0.5rem;
      display: flex;
      gap: 0.5rem;
    }

    #ai-controls input {
      flex: 2;
      border-radius: 6px;
      padding: 6px;
      border: 1px solid #ccc;
    }

    #ai-controls button {
      flex: 1;
      border: none;
      border-radius: 6px;
      background: var(--gold);
      color: var(--navy);
      cursor: pointer;
    }

    #ai-controls button:hover {
      background: var(--navy);
      color: var(--gold);
    }

    /* Hide mic button on desktop */
    @media (min-width: 769px) {
      #micBtn {
        display: none;
      }
    }

    /* Main content */
    main {
      margin-left: 220px;
      padding: 80px 20px;
    }

    .container {
      max-width: 900px;
      margin: 40px auto;
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    h1,
    h2 {
      text-align: center;
      color: var(--navy);
    }

    .card {
      background: #ffffff78;
      border-radius: 12px;
      padding: 20px;
      margin: 10px 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .card p {
      margin: 0;
    }

    .quick-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .quick-action {
      flex: 1 1 45%;
      background: #fff;
      border-radius: 10px;
      padding: 15px;
      text-align: center;
      cursor: pointer;
      box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
      transition: 0.3s;
    }

    .quick-action:hover {
      transform: scale(1.05);
    }

    .quick-action i {
      font-size: 24px;
      color: var(--navy);
    }

    .notifications {
      background: #eef1ff;
      border-left: 5px solid var(--navy);
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
    }

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

    @media (max-width:768px) {
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

      #chat-icon {
        display: flex;
      }
    }

    .card-title {
      font-weight: bold;
      color: var(--navy);
    }

    .card-value {
      font-size: 1.5em;
      color: var(--gold);
    }

    /* mobile */
    .B {
      display: block;
    }

    /* desktop */

    /* Responsive */
    @media(max-width:768px) {
      .A {
        display: block;
      }

      .B {
        display: none;
      }

      main {
        margin-left: 0;
        padding: 20px;
      }
    }

    .dashboard-card {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .card-title {
      font-weight: bold;
      color: var(--navy);
    }

    .card-value {
      font-size: 1.5em;
      color: var(--gold);
    }

    .A {
      display: none;
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

  <main>
    <div class="container">
      <h1>Welcome, <?= htmlspecialchars($_SESSION['name']); ?></h1>
      <p>This is your <strong>Dashboard</strong></p>

      <!-- MOBILE SUMMARY -->
      <div class="A dashboard-card">
        <p class="card-title">Hello, <?= htmlspecialchars($_SESSION['name']); ?></p>
        <p class="card-title">Total Money Owed:</p>
        <p class="card-value">R<?= number_format($loans['total_approved'], 2); ?></p>
        <p class="card-title">Total Invested:</p>
        <p class="card-value">R<?= number_format($investments['total_approved'], 2); ?></p>
      </div>

      <!-- Money Summary -->
      <div class="container">
        <div class="card">
          <p class="card-title">Your Current Balance:</p>
          <p class="card-value">R<?= number_format($current_balance, 2); ?></p>
        </div>





        <div class="card">
          <p class="card-title">Total Invested:</p>
          <p class="card-value">R<?= number_format($investments['total_approved'], 2); ?></p>
        </div>

        <div class="card">
          <p class="card-title">Total Money Owed:</p>
          <p class="card-value">R<?= number_format($loans['total_approved'], 2); ?></p>
        </div>



        <!-- Loan Summary -->
        <div class="card">
          <p class="card-title">Loans: Pending / Approved / Rejected</p>
          <p class="card-value"><?= "$user_pending_loans / $user_approved_loans / $user_rejected_loans" ?></p>
        </div>

      </div>


      <!-- Quick Actions --
      <h2>Quick Actions</h2>
      <div class="quick-actions">
        <a href="#" class="quick-action">
        <button class="quick-action"><i class="fas fa-mobile-screen"></i>
          <p>Buy Airtime</p>
        </button></a>
        <a href="#" class="quick-action">
        <button class="quick-action"><i class="fas fa-lightbulb"></i>
          <p>Buy Electricity</p>
        </button></a>
        <a href="#" class="quick-action">
        <button class="quick-action"><i class="fas fa-user"></i>
          <p>Pay Beneficiary</p>
        </button></a>
        <a href="track_money.php" class="quick-action">
        <button class="quick-action"><i class="fas fa-chart-line"></i>
            <p>Track Money</p>
          </button></a>
          <a href="#" class="quick-action">
        <button class="quick-action"><i class="fas fa-money-check"></i>
          <p>Buy Vouchers</p>
        </button></a>
        <a href="#" class="quick-action">
        <button class="quick-action"><i class="fas fa-car"></i>
          <p>Pay Car Insurance</p>
  </button></a>
      </div-->
  </main>

  <!-- Floating chat icon -->
  <div id="chat-icon" onclick="toggleChat()"><i class='bx bx-message-rounded-dots'></i></div>

  <!-- AI BOX -->
  <div id="ai-box">
    <div id="ai-header">
      SB AI Buddy
      <button onclick="toggleChat()">CLOSE</button>
    </div>
    <div id="ai-chat"></div>
    <div id="ai-controls">
      <input type="text" id="userInput" placeholder="Type your message...">
      <button onclick="sendText()">Send</button>
      <button id="micBtn" onclick="talkAI()">ACTIVATE</button>
    </div>
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

    // ==== AI LOGIC ====
    const chatBox = document.getElementById("ai-chat");
    let selectedVoice = null;
    const userLoanInfo = "<?= addslashes($loan_info) ?>";

    function loadVoices() {
      const voices = speechSynthesis.getVoices();
      selectedVoice = voices.find(v => v.lang === "en-US" && v.name.includes("Google")) || voices[0];
    }
    speechSynthesis.onvoiceschanged = loadVoices;

    function addMsg(text, sender = "ai") {
      const msg = document.createElement("div");
      msg.className = "msg " + sender;
      msg.textContent = text;
      chatBox.appendChild(msg);
      chatBox.scrollTop = chatBox.scrollHeight;
    }

    function speak(text) {
      const utter = new SpeechSynthesisUtterance(text);
      utter.voice = selectedVoice;
      utter.rate = 1.05;
      speechSynthesis.cancel();
      speechSynthesis.speak(utter);
    }

    // Greet with loan info
    window.addEventListener("load", () => {
      const welcome = "Hello <?= htmlspecialchars($_SESSION['name']); ?>! " + userLoanInfo;
      addMsg(welcome, "ai");
      speak(welcome);
    });

    function sendText() {
      const input = document.getElementById("userInput");
      const text = input.value.trim();
      if (!text) return;
      addMsg(text, "user");
      input.value = "";
      handleUserMessage(text);
    }

    function talkAI() {
      const intro = "Hello! I am your Senoamadi Bank AI Buddy. " + userLoanInfo;
      addMsg(intro, "ai");
      speak(intro);
    }

    function handleUserMessage(text) {
      let reply = "";
      const lower = text.toLowerCase();
      if (lower.includes("loan")) reply = userLoanInfo;
      else if (lower.includes("save")) reply = "Remember, savings help you prepare for future needs!";
      else if (lower.includes("hello") || lower.includes("hi")) reply = "Hello there! How can I help today?";
      else if (lower.includes("thanks")) reply = "You're welcome! Always happy to assist.";
      else reply = "That's interesting! Would you like to know more about our services?";
      addMsg(reply, "ai");
      speak(reply);
    }
  </script>
</body>

</html>