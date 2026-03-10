<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['email'])) {
    header("Location: index2.php");
    exit();
}

$message = '';
$message_type = ''; // 'success' or 'error'

if (isset($_POST['invest'])) {
    $user_id = $_SESSION['id'];
    $amount = floatval($_POST['amount']); // ensure numeric
    $plan = trim($_POST['plan']);

    $stmt = $conn->prepare("INSERT INTO investments (user_id, amount, plan) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $amount, $plan);

    if ($stmt->execute()) {
        $message = "Investment submitted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error submitting investment: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

$stmt = $conn->prepare("INSERT INTO investments (user_id, amount, plan, status) VALUES (?, ?, ?, 'pending')");
$stmt->bind_param("ids", $user_id, $amount, $plan);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title that appears in search results -->
    <title>INVEST | SENOAMADI_BANK</title>
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        /* keep same styling as loan page */
        body {
            background: var(--light-bg);
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
        }

        .container,
        .loan-container {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: var(--navy);
            margin-bottom: 20px;
        }

        input,
        textarea,
        select,
        button {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        button {
            background: var(--navy);
            color: var(--gold);
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }

        button:hover {
            opacity: 0.9;
        }

        .message {
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .message.success {
            background: #e0f7e9;
            color: #007a3d;
        }

        .message.error {
            background: #fde0e0;
            color: #b30000;
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

        /* Show only on phones */
        @media (max-width: 768px) {
            #chat-icon {
                display: flex;
            }
        }

        /* Hide mic button on desktop */
        @media (min-width: 769px) {
            #micBtn {
                display: none;
            }
        }

        body {
            background-color: gray;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            height: 100vh;
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

    <div class="loan-container">
        <h1>Make an Investment</h1>
        <?php if ($message): ?>
            <p class="message <?= $message_type; ?>"><?= $message; ?></p>
        <?php endif; ?>
        <form method="POST">
            <label>Investment Amount (R):</label>
            <input type="number" name="amount" step="0.01" required>

            <label>Investment Plan:</label>
            <select name="plan" required>
                <option value="">Select Plan</option>
                <option value="Short Term">Short Term</option>
                <option value="Medium Term">Medium Term</option>
                <option value="Long Term">Long Term</option>
            </select>

            <button type="submit" name="invest">Submit Investment</button>
        </form>
    </div>
    <script>
        // Optional: prevent multiple submits or add client-side validation
        document.getElementById('loanForm').addEventListener('submit', function() {
            alert("Your loan application has been submitted!");
        });

        function toggleMenu() {
            document.getElementById("sidebar").classList.toggle("active");
        }

        function toggleInverse() {
            document.body.classList.toggle("inverse");
        }
    </script>
</body>

</html>