<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
  header("Location: index2.php");
  exit();
}

// Fetch all users
$users = $conn->query("SELECT id, name, email, role, profile_pic FROM users ORDER BY id ASC");

// Fetch pending loans
$loans = $conn->query("
    SELECT l.id, u.name, u.email, l.amount, l.purpose, l.status
    FROM loan_applications l
    JOIN users u ON l.user_id = u.id
    WHERE l.status='pending'
    ORDER BY l.created_at ASC
");

// Fetch pending investments
$investments = $conn->query("
    SELECT i.id, u.name, u.email, i.amount, i.plan, i.status, i.interest_rate
    FROM investments i
    JOIN users u ON i.user_id = u.id
    WHERE i.status='pending'
    ORDER BY i.created_at ASC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="SENOAMADI_BANK.png">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Title that appears in search results -->
  <title>Admin-Dashboard | SENOAMADI_BANK</title>
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
  <!-- Boxicons for menu icons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    body {
      background: gray;
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      padding: 0;
    }

    img.profile-pic {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
    }

    .top-buttons {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      gap: 10px;
    }

    .top-buttons button {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      color: white;
      background: #002b5b;
      transition: 0.3s;
    }

    .top-buttons button.logout-btn {
      background: #bfa43a;
      color: #002b5b;
    }

    .top-buttons button:hover {
      opacity: 0.85;
    }

    /* ===== HEADER ===== */
    header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background: #0A1A2F;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.5em;
      z-index: 1000;
      margin-bottom: 50px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .logo {
      font-size: 2rem;
      font-weight: 700;
      color: #fff;
      text-transform: uppercase;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo img {
      width: 45px;
      border-radius: 10px;
    }

    /* ===== CONTACT ===== */
    #contact {
      background: linear-gradient(135deg, #0A1A2F, #1C1F26, #D4AF37, #1C1F26, #0A1A2F);
      color: #000;
      width: 100%;
    }

    .contact {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 12px;
      background: #0A1A2F;
      color: #D4AF37;
      font-weight: 700;
      border: none;
      cursor: pointer;
      text-decoration: none;
    }

    .contact form input,
    .contact form textarea {
      background: #f0f0f0;
      border: 1px solid #ccc;
      color: #000;
      border-radius: 8px;
      padding: 12px;
    }

    .contact form textarea {
      min-height: 120px;
    }

    .contact img {
      flex: 1;
      max-width: 100px;
      border-radius: 12px;
      justify-content: center;
      align-items: center;
      text-align: center;
    }

    .social-links {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      justify-content: center;
      margin-top: 10px;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
      .container {
        margin: 100px 15px 50px;
        padding: 20px;
      }
    }

    @media (max-width: 768px) {
      .top-buttons {
        flex-direction: column;
        align-items: stretch;
      }

      .top-buttons button {
        width: 100%;
      }

      table th,
      table td {
        font-size: 14px;
        padding: 8px;
      }

    }

    @media (max-width: 480px) {

      table th,
      table td {
        font-size: 12px;
        padding: 6px;
      }
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

    /* Main content */
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
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }

    table th,
    table td {
      padding: 12px;
      border-bottom: 1px solid #ccc;
      text-align: center;
    }

    table th {
      background: var(--navy);
      color: var(--gold);
    }

    table tr:hover {
      background: #f1f1f1;
    }

    img.profile-pic {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
    }

    @media (max-width:768px) {
      main {
        margin-left: 0;
        padding: 20px;
      }

      table th,
      table td {
        font-size: 14px;
        padding: 8px;
      }
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
      <button onclick="window.location.href='admin_page.php'">REGISTERED</button>
      <button onclick="window.location.href='loan-table.php'">LOANS</button>
      <button onclick="window.location.href='investment-table.php'">INVESTMENT</button>
      <button class="logout-btn" onclick="window.location.href='logout.php'">LOG OUT</button>
      <button onclick="toggleInverse()">MODE</button>
    </div>
  </nav>

  <div class="container">
    <div class="top-buttons">
      <h1>Welcome, <span><?= htmlspecialchars($_SESSION['name']); ?></span> (Admin)</h1>
    </div>

    <h2>All Registered Users</h2>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Profile</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users->num_rows > 0): ?>
            <?php while ($row = $users->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id']; ?></td>
                <td><img src="<?= !empty($row['profile_pic']) ? $row['profile_pic'] : 'uploads/default.png'; ?>" class="profile-pic"></td>
                <td><?= htmlspecialchars($row['name']); ?></td>
                <td><?= htmlspecialchars($row['email']); ?></td>
                <td><?= htmlspecialchars($row['role']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5">No users found</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
  </div>

  <script>
    document.querySelectorAll('input.interest').forEach(input => {
      input.addEventListener('change', () => {
        const id = input.id.split('_')[1];
        const rate = input.value;
        fetch('update_interest.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `id=${id}&rate=${rate}`
          })
          .then(res => res.text())
          .then(data => alert(data))
          .catch(err => console.error(err));
      });
    });
  </script>

</body>
</html>