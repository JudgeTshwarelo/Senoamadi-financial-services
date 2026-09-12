<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['email'], $_SESSION['id'])) {
  header("Location: index2.php");
  exit();
}

$uid = $_SESSION['id'];
$user_name = htmlspecialchars($_SESSION['name']);

// Fetch loan summary
$loan_summary = $conn->query("SELECT status, COUNT(*) AS count, SUM(amount) AS total FROM loan_applications WHERE user_id=$uid GROUP BY status");
$loans = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_approved' => 0];

if ($loan_summary && $loan_summary->num_rows > 0) {
  while ($row = $loan_summary->fetch_assoc()) {
    $status = strtolower($row['status']);
    $loans[$status] = $row['count'];
    if ($status === 'approved') $loans['total_approved'] = $row['total'];
  }
}

// Fetch investment summary
$inv_summary = $conn->query("SELECT status, COUNT(*) AS count, SUM(amount) AS total FROM investments WHERE user_id=$uid GROUP BY status");
$investments = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_approved' => 0];

if ($inv_summary && $inv_summary->num_rows > 0) {
  while ($row = $inv_summary->fetch_assoc()) {
    $status = strtolower($row['status']);
    $investments[$status] = $row['count'];
    if ($status === 'approved') $investments['total_approved'] = $row['total'];
  }
}

// Fetch notifications (loans + investments, excluding pending)
$notifications_sql = "
    SELECT 'Loan' AS type, amount, status, created_at 
    FROM loan_applications 
    WHERE user_id=$uid AND status!='pending'
    UNION
    SELECT 'Investment' AS type, amount, status, created_at 
    FROM investments 
    WHERE user_id=$uid AND status!='pending'
    ORDER BY created_at DESC
";
$notifications = $conn->query($notifications_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Money | SENOAMADI_BANK</title>
  <link rel="icon" type="image/png" href="SENOAMADI_BANK.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
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
      transition: 0.5s;
    }

    body.inverse {
      --gold: #0A1A2F;
      --navy: #D4AF37;
      background: var(--dark-bg);
      color: #eee;
    }

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

    body.inverse .container {
      background: #2b2b2b;
      color: #eee;
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

    .notifications {
      background: #eef1ff;
      border-left: 5px solid var(--navy);
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
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

    @media(max-width:768px) {
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
  </style>
</head>

<body>
  <button class="menu-toggle" onclick="toggleMenu()"><i class="bx bx-menu"></i></button>
  <nav id="sidebar">
    <div class="logo"><img src="SENOAMADI_BANK.png" alt="Logo"></div>
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
      <h1>Welcome, <?= $user_name; ?></h1>

      <div class="dashboard-card">
        <h2>Loan Summary</h2>
        <p>Pending: <?= $loans['pending']; ?> | Approved: <?= $loans['approved']; ?> | Rejected: <?= $loans['rejected']; ?></p>
        <p>Total Approved Amount: R<?= number_format($loans['total_approved'], 2); ?></p>
      </div>

      <div class="dashboard-card">
        <h2>Investment Summary</h2>
        <p>Pending: <?= $investments['pending']; ?> | Approved: <?= $investments['approved']; ?> | Rejected: <?= $investments['rejected']; ?></p>
        <p>Total Approved Amount: R<?= number_format($investments['total_approved'], 2); ?></p>
      </div>

      <?php if ($notifications && $notifications->num_rows > 0): ?>
        <div class="notifications">
          <h2>Notifications</h2>
          <ul>
            <?php while ($n = $notifications->fetch_assoc()): ?>
              <li><?= $n['type']; ?> of <strong>R<?= number_format($n['amount'], 2); ?></strong> has been <strong><?= strtoupper($n['status']); ?></strong> (<?= date('d M Y', strtotime($n['created_at'])); ?>)</li>
            <?php endwhile; ?>
          </ul>
        </div>
      <?php else: ?>
        <p>No notifications yet.</p>
      <?php endif; ?>

    </div>
  </main>

  <script>
    function toggleMenu() {
      document.getElementById("sidebar").classList.toggle("active");
    }

    function toggleInverse() {
      document.body.classList.toggle("inverse");
    }
  </script>
</body>

</html>