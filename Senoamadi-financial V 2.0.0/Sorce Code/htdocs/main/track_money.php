<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];
$userName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

$db = get_db();

// ---------------------------------------------------------
// Fetch user balance + profile pic + email
// ---------------------------------------------------------
$current_balance = 0.00;
$userProfilePic  = '';
$userEmail       = $_SESSION['email'] ?? '';

try {
    $stmt = $db->prepare("SELECT balance, profile_pic, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row) {
        if (isset($row['balance']))      $current_balance = (float) $row['balance'];
        if (!empty($row['profile_pic'])) $userProfilePic  = $row['profile_pic'];
        if (!empty($row['email']))       $userEmail       = $row['email'];
    }
} catch (PDOException $e) {}

if (empty($userProfilePic)) {
    try {
        $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row && !empty($row['profile_pic'])) {
            $userProfilePic = $row['profile_pic'];
        }
    } catch (PDOException $e) {}
}

// ---------------------------------------------------------
// Fetch all personal items (investments, loans, proposals)
// ---------------------------------------------------------
$investments = [];
try {
    $stmt = $db->prepare("
        SELECT id, title, description, category, amount, expected_roi, duration_months,
               visibility, status, created_at
        FROM personal_investments
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$uid]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $investments = []; }

$loans = [];
try {
    $stmt = $db->prepare("
        SELECT id, principal, interest_rate, interest_amount, total_repayable,
               amount_repaid, purpose, term_months, visibility, status, created_at
        FROM personal_loans
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$uid]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $loans = []; }

$proposals = [];
try {
    $stmt = $db->prepare("
        SELECT id, title, description, type, amount_needed, visibility, status, created_at
        FROM personal_proposals
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$uid]);
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $proposals = []; }

// ---------------------------------------------------------
// Analytics
// ---------------------------------------------------------

// Investments: only count approved/active
$totalInvestments = 0;
foreach ($investments as $inv) {
    if (in_array(strtolower($inv['status']), ['approved', 'active'])) {
        $totalInvestments += (float) $inv['amount'];
    }
}

// Assets vs Liabilities derived from user's categories
// Everything classified as asset goes to totalAssets
// Everything classified as liability goes to totalLiabilities
$totalAssets      = 0;
$totalLiabilities = 0;

// We treat the investments array as both investment totals and asset-like contributions
// based on category. Savings-style entries are simply part of investments.
// For now: any investment marked category 'asset' is counted as an asset.
foreach ($investments as $inv) {
    $cat = strtolower($inv['category'] ?? '');
    if ($cat === 'asset' && in_array(strtolower($inv['status']), ['approved', 'active'])) {
        $totalAssets += (float) $inv['amount'];
    }
    if ($cat === 'liability' && in_array(strtolower($inv['status']), ['approved', 'active'])) {
        $totalLiabilities += (float) $inv['amount'];
    }
}

// Savings = balance on users table (treated as user's free savings)
$totalSavings = $current_balance;

// Loan aggregates (exclude cancelled/rejected)
$totalLoanPrincipal = 0;
$totalLoanInterest  = 0;
$totalLoanRepayable = 0;
$totalLoanRepaid    = 0;
$activeLoans        = 0;

foreach ($loans as $l) {
    $s = strtolower($l['status']);
    if (in_array($s, ['cancelled', 'rejected'])) continue;

    $totalLoanPrincipal += (float) $l['principal'];
    $totalLoanInterest  += (float) $l['interest_amount'];
    $totalLoanRepayable += (float) $l['total_repayable'];
    $totalLoanRepaid    += (float) $l['amount_repaid'];

    if (in_array($s, ['pending', 'approved', 'active'])) $activeLoans++;
}

// Loans also become a liability (money you owe)
$totalLiabilities += $totalLoanRepayable;

// Net worth = savings + investments + assets − liabilities
$netWorth = ($totalSavings + $totalInvestments + $totalAssets) - $totalLiabilities;

// ---------------------------------------------------------
// Category breakdown (for portfolio bars)
// ---------------------------------------------------------
$categoryBreakdown = [
    'savings'    => $totalSavings,
    'investment' => $totalInvestments,
    'asset'      => $totalAssets,
    'liability'  => $totalLiabilities,
];

$categoryMax = max(array_values($categoryBreakdown)) ?: 1;

// ---------------------------------------------------------
// Loan summary stats (counts)
// ---------------------------------------------------------
$user_pending_loans  = 0;
$user_approved_loans = 0;
$user_rejected_loans = 0;

foreach ($loans as $l) {
    $s = strtolower($l['status']);
    if ($s === 'pending')  $user_pending_loans++;
    if ($s === 'approved') $user_approved_loans++;
    if ($s === 'rejected') $user_rejected_loans++;
}

// ---------------------------------------------------------
// Investment summary stats
// ---------------------------------------------------------
$investmentsSummary = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_approved' => 0];
foreach ($investments as $inv) {
    $s = strtolower($inv['status']);
    if (isset($investmentsSummary[$s])) {
        $investmentsSummary[$s] += (float) $inv['amount'];
    }
    if ($s === 'approved' || $s === 'active') {
        $investmentsSummary['total_approved'] += (float) $inv['amount'];
    }
}

// ---------------------------------------------------------
// Notifications — most recent approved/rejected items
// ---------------------------------------------------------
$notifications = [];
foreach ($loans as $l) {
    if (strtolower($l['status']) !== 'pending') {
        $notifications[] = [
            'type'       => 'Loan',
            'amount'     => (float) $l['principal'],
            'status'     => $l['status'],
            'created_at' => $l['created_at'],
        ];
    }
}
foreach ($investments as $inv) {
    if (strtolower($inv['status']) !== 'pending') {
        $notifications[] = [
            'type'       => 'Investment',
            'amount'     => (float) $inv['amount'],
            'status'     => $inv['status'],
            'created_at' => $inv['created_at'],
        ];
    }
}
usort($notifications, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$notifications = array_slice($notifications, 0, 10);

// Totals for summary/PDF
$totalInvested  = $investmentsSummary['total_approved'];
$totalOwed      = $totalLoanRepayable;
$statementDate  = date('d F Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TRACK MONEY | SFS</title>
    <meta name="description" content="Track your investments, loans, and download a PDF statement." />
    <meta name="author" content="Judge Tshwarelo" />
    <link rel="icon" type="image/png" href="/files/SFS-LOGO.png" />

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
            --success: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
            --info: #3498db;
            --purple: #9b59b6;
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
           SIDEBAR
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
           MAIN
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
            margin-bottom: 32px;
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
            max-width: 620px;
        }

        .date-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            background: rgba(8, 24, 46, .7);
            border: 1px solid rgba(212, 175, 55, .25);
            color: var(--gold-light);
            font-size: .85rem;
            font-weight: 600;
        }

        /* ======================================================
           KPI GRID
        ====================================================== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin: 26px 0 28px;
        }

        .kpi-card {
            position: relative;
            overflow: hidden;
            padding: 22px 22px;
            border-radius: 18px;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .18);
            transition: .35s;
        }

        .kpi-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(212, 175, 55, .12), transparent 70%);
            transform: translate(35%, -35%);
            pointer-events: none;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
            box-shadow: 0 18px 42px rgba(212, 175, 55, .15);
        }

        .kpi-card .kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(212, 175, 55, .22), rgba(212, 175, 55, .05));
            border: 1px solid rgba(212, 175, 55, .3);
            margin-bottom: 14px;
        }

        .kpi-card .kpi-icon i { font-size: 20px; color: var(--gold); }

        .kpi-card .kpi-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: rgba(255, 255, 255, .55);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .kpi-card .kpi-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            line-height: 1;
            letter-spacing: -.5px;
        }

        .kpi-card .kpi-value.gold {
            background: linear-gradient(90deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .kpi-card .kpi-value.positive { color: var(--success); }
        .kpi-card .kpi-value.negative { color: var(--danger); }

        .kpi-card .kpi-sub {
            font-size: .74rem;
            color: rgba(255, 255, 255, .5);
            margin-top: 8px;
        }

        /* ======================================================
           PORTFOLIO BREAKDOWN
        ====================================================== */
        .progress-wrapper {
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .18);
            border-radius: 20px;
            padding: 24px 26px;
            margin: 0 0 32px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            font-size: .9rem;
            color: rgba(255, 255, 255, .75);
            font-weight: 600;
        }

        .progress-header i { color: var(--gold); margin-right: 6px; }

        .progress-header strong {
            background: linear-gradient(90deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.05rem;
        }

        .category-bars {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .cat-bar-row {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: .88rem;
        }

        .cat-bar-label {
            flex: 0 0 110px;
            color: rgba(255, 255, 255, .7);
            font-weight: 600;
            text-transform: capitalize;
        }

        .cat-bar-track {
            flex: 1;
            height: 10px;
            background: rgba(255, 255, 255, .08);
            border-radius: 10px;
            overflow: hidden;
        }

        .cat-bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
            position: relative;
        }

        .cat-bar-fill::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .3), transparent);
            animation: shimmer 2.4s infinite;
        }

        @keyframes shimmer {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .cat-bar-fill.savings    { background: linear-gradient(90deg, #2ecc71, #27ae60); }
        .cat-bar-fill.investment { background: linear-gradient(90deg, #3498db, #2980b9); }
        .cat-bar-fill.asset      { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
        .cat-bar-fill.liability  { background: linear-gradient(90deg, #e74c3c, #c0392b); }

        .cat-bar-value {
            flex: 0 0 120px;
            text-align: right;
            color: var(--gold-light);
            font-weight: 700;
            font-size: .85rem;
        }

        /* ======================================================
           ACTION BUTTONS
        ====================================================== */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 10px 0 36px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 12px;
            font-weight: 700;
            font-size: .88rem;
            cursor: pointer;
            transition: .3s;
            border: 1px solid transparent;
            font-family: inherit;
            letter-spacing: .3px;
        }

        .btn i { font-size: 18px; }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 8px 20px rgba(212, 175, 55, .25);
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(212, 175, 55, .4);
        }

        .btn-outline {
            background: rgba(255, 255, 255, .04);
            color: var(--gold-light);
            border-color: rgba(212, 175, 55, .35);
        }
        .btn-outline:hover {
            background: rgba(212, 175, 55, .12);
            border-color: var(--gold);
            transform: translateY(-2px);
        }

        /* ======================================================
           SECTION HEADS
        ====================================================== */
        .section-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 34px 0 20px;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-head h2 i { color: var(--gold); }

        /* ======================================================
           TABLES
        ====================================================== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 18px;
            border: 1px solid rgba(212, 175, 55, .18);
            background: rgba(8, 20, 40, .5);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px;
        }

        table thead { background: rgba(212, 175, 55, .08); }

        table th {
            padding: 14px 16px;
            text-align: left;
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--gold);
            font-weight: 800;
            white-space: nowrap;
            border-bottom: 1px solid rgba(212, 175, 55, .2);
        }

        table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
            font-size: .88rem;
            vertical-align: middle;
            color: rgba(255, 255, 255, .85);
        }

        table tbody tr { transition: .2s; }
        table tbody tr:hover { background: rgba(212, 175, 55, .04); }
        table tbody tr:last-child td { border-bottom: none; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-block;
            white-space: nowrap;
        }

        .status-badge.pending  { background: rgba(241, 196, 15, .15); color: var(--warning); border: 1px solid rgba(241, 196, 15, .35); }
        .status-badge.approved,
        .status-badge.active   { background: rgba(46, 204, 113, .15); color: var(--success); border: 1px solid rgba(46, 204, 113, .35); }
        .status-badge.rejected,
        .status-badge.cancelled { background: rgba(231, 76, 60, .15); color: var(--danger); border: 1px solid rgba(231, 76, 60, .35); }
        .status-badge.closed,
        .status-badge.repaid   { background: rgba(139, 163, 199, .15); color: #8ba3c7; border: 1px solid rgba(139, 163, 199, .35); }

        .visibility-badge {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-block;
        }

        .visibility-badge.private  { background: rgba(139, 163, 199, .15); color: #8ba3c7; border: 1px solid rgba(139, 163, 199, .35); }
        .visibility-badge.public   { background: rgba(212, 175, 55, .18); color: var(--gold-light); border: 1px solid rgba(212, 175, 55, .4); }

        /* ======================================================
           NOTIFICATIONS
        ====================================================== */
        .notifications {
            margin-top: 34px;
            padding: 24px 26px;
            border-radius: 20px;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .2);
            border-left: 4px solid var(--gold);
        }

        .notifications h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gold-light);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .notifications h3 i { color: var(--gold); font-size: 20px; }

        .notifications ul { list-style: none; padding: 0; }

        .notifications li {
            padding: 10px 0;
            font-size: .88rem;
            color: rgba(255, 255, 255, .75);
            border-bottom: 1px solid rgba(255, 255, 255, .05);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .notifications li:last-child { border-bottom: none; }
        .notifications li strong { color: white; }
        .notifications li .amount { color: var(--gold-light); font-weight: 700; }

        /* ======================================================
           EMPTY STATE
        ====================================================== */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            border-radius: 18px;
            background: rgba(8, 20, 40, .4);
            border: 1px dashed rgba(212, 175, 55, .25);
            color: rgba(255, 255, 255, .55);
        }

        .empty-state i {
            font-size: 2.4rem;
            color: var(--gold);
            display: block;
            margin-bottom: 10px;
            opacity: .7;
        }

        .empty-state strong {
            display: block;
            color: white;
            font-size: 1.05rem;
            margin-bottom: 6px;
        }

        /* ======================================================
           PDF STATEMENT (print only)
        ====================================================== */
        #pdf-statement { display: none; }

        @media print {
            body * { visibility: hidden; }

            #pdf-statement,
            #pdf-statement * { visibility: visible; }

            #pdf-statement {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #ffffff;
                color: #001f3f;
                padding: 20mm;
                font-family: Arial, Helvetica, sans-serif;
            }

            .pdf-header {
                text-align: center;
                border-bottom: 3px solid #D4AF37;
                padding-bottom: 18px;
                margin-bottom: 24px;
            }

            .pdf-logo {
                width: 80px;
                height: 80px;
                object-fit: contain;
                border-radius: 14px;
                display: block;
                margin: 0 auto 10px;
                border: 3px solid #D4AF37;
                background: #ffffff;
                padding: 4px;
            }

            .pdf-header h1 {
                color: #001f3f;
                font-size: 1.6rem;
                margin-bottom: 4px;
                letter-spacing: 1px;
                font-weight: bold;
            }

            .pdf-header p {
                color: #555;
                font-size: 0.85rem;
                margin: 2px 0;
            }

            .pdf-header p strong { color: #D4AF37; }

            .pdf-section { margin-bottom: 24px; }

            .pdf-section h2 {
                color: #001f3f;
                font-size: 1.05rem;
                border-bottom: 2px solid #D4AF37;
                padding-bottom: 6px;
                margin-bottom: 12px;
                font-weight: bold;
                letter-spacing: 0.5px;
            }

            .pdf-holder {
                display: flex;
                align-items: center;
                gap: 18px;
                background: #faf6e8;
                border-left: 4px solid #D4AF37;
                border-radius: 10px;
                padding: 16px 18px;
                margin-bottom: 14px;
            }

            .pdf-avatar {
                width: 72px;
                height: 72px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid #D4AF37;
                background: #ffffff;
                flex-shrink: 0;
            }

            .pdf-holder-info { flex: 1; min-width: 0; }

            .pdf-holder-info .name {
                font-size: 1.05rem;
                font-weight: bold;
                color: #001f3f;
                margin-bottom: 3px;
                word-break: break-word;
            }

            .pdf-holder-info .meta {
                font-size: 0.8rem;
                color: #555;
                margin: 2px 0;
                word-break: break-word;
            }

            .pdf-holder-info .meta strong { color: #001f3f; }

            .pdf-info-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 14px;
            }

            .pdf-info-item {
                flex: 1 1 180px;
                background: #faf6e8;
                padding: 12px 14px;
                border-radius: 10px;
                border-left: 4px solid #D4AF37;
            }

            .pdf-info-item .label {
                font-size: 0.68rem;
                text-transform: uppercase;
                color: #666;
                letter-spacing: 1px;
                font-weight: bold;
            }

            .pdf-info-item .value {
                font-size: 1.1rem;
                font-weight: bold;
                color: #001f3f;
                margin-top: 3px;
            }

            .pdf-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 8px;
            }

            .pdf-table th {
                background: #001f3f;
                color: #D4AF37;
                padding: 9px 11px;
                text-align: left;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-weight: bold;
            }

            .pdf-table td {
                padding: 9px 11px;
                border-bottom: 1px solid #e5e5e5;
                font-size: 0.8rem;
                color: #001f3f;
                vertical-align: top;
            }

            .pdf-table tr:nth-child(even) { background: #f9f9f9; }

            .pdf-badge {
                display: inline-block;
                padding: 3px 9px;
                border-radius: 12px;
                font-size: 0.66rem;
                font-weight: bold;
                text-transform: uppercase;
            }

            .pdf-badge.approved,
            .pdf-badge.active   { background: #d4edda; color: #155724; }
            .pdf-badge.pending  { background: #fff3cd; color: #856404; }
            .pdf-badge.rejected,
            .pdf-badge.cancelled { background: #f8d7da; color: #721c24; }
            .pdf-badge.closed,
            .pdf-badge.repaid   { background: #e2e3e5; color: #383d41; }

            .pdf-footer {
                margin-top: 30px;
                text-align: center;
                font-size: 0.72rem;
                color: #666;
                border-top: 2px solid #D4AF37;
                padding-top: 16px;
            }

            @page { margin: 15mm; }
        }

        /* ======================================================
           FLOATING CHAT (mobile)
        ====================================================== */
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

        /* ======================================================
           RESPONSIVE
        ====================================================== */
        @media(max-width: 1024px) {
            main { padding: 30px 28px 100px; }
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

            .page-header { flex-direction: column; align-items: flex-start; }

            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .kpi-card { padding: 16px 14px; }
            .kpi-card .kpi-value { font-size: 1.15rem; }
            .kpi-card .kpi-icon { width: 34px; height: 34px; margin-bottom: 10px; }
            .kpi-card .kpi-icon i { font-size: 16px; }

            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }

            .cat-bar-label { flex: 0 0 80px; font-size: .8rem; }
            .cat-bar-value { flex: 0 0 90px; font-size: .78rem; }

            .section-head h2 { font-size: 1.05rem; }

            #chat-icon { display: flex; }
        }

        @media(max-width: 420px) {
            .kpi-grid { grid-template-columns: 1fr; }
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
            <a class="menu-item active" href="track_money.php">
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
            <a class="menu-item" href="profile.php">
                <i class="bx bx-user-circle"></i>
                <span>PROFILE</span>
            </a>
            <a class="menu-item logout-btn" href="logout.php">
                <i class="bx bx-power-off"></i>
                <span>LOGOUT</span>
            </a>
        </div>
    </nav>


    <!-- ======================================================
         MAIN CONTENT
    ====================================================== -->
    <main>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h1>Track Your Money</h1>
                <p>
                    A full summary of your savings, investments, loans, and net worth.
                </p>
            </div>
        </div>

        <!-- ======================================================
             KPI GRID
        ====================================================== -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-trending-up"></i></div>
                <div class="kpi-label">Investments</div>
                <div class="kpi-value">R<?= number_format($totalInvestments, 2) ?></div>
                <div class="kpi-sub">Approved & active</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-home-alt"></i></div>
                <div class="kpi-label">Assets</div>
                <div class="kpi-value">R<?= number_format($totalAssets, 2) ?></div>
                <div class="kpi-sub">Property & holdings</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-down-arrow-circle"></i></div>
                <div class="kpi-label">Liabilities</div>
                <div class="kpi-value negative">R<?= number_format($totalLiabilities, 2) ?></div>
                <div class="kpi-sub">Debts & obligations</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-diamond"></i></div>
                <div class="kpi-label">Net Worth</div>
                <div class="kpi-value <?= $netWorth >= 0 ? 'positive' : 'negative' ?>">
                    R<?= number_format($netWorth, 2) ?>
                </div>
                <div class="kpi-sub">Savings + Inv + Assets − Liab</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-credit-card"></i></div>
                <div class="kpi-label">Active Loans</div>
                <div class="kpi-value"><?= $activeLoans ?></div>
                <div class="kpi-sub">Total repayable: R<?= number_format($totalLoanRepayable, 2) ?></div>
            </div>
        </div>

        <!-- ======================================================
             PORTFOLIO BREAKDOWN
        ====================================================== -->
        <?php if (array_sum($categoryBreakdown) > 0): ?>
            <div class="progress-wrapper">
                <div class="progress-header">
                    <span><i class="bx bx-pie-chart-alt"></i> Portfolio Breakdown</span>
                    <span>Total: <strong>R<?= number_format(array_sum($categoryBreakdown), 2) ?></strong></span>
                </div>
                <div class="category-bars">
                    <?php foreach ($categoryBreakdown as $cat => $val): ?>
                        <div class="cat-bar-row">
                            <div class="cat-bar-label"><?= ucfirst($cat) ?></div>
                            <div class="cat-bar-track">
                                <div class="cat-bar-fill <?= $cat ?>" style="width: <?= $categoryMax > 0 ? ($val / $categoryMax) * 100 : 0 ?>%;"></div>
                            </div>
                            <div class="cat-bar-value">R<?= number_format($val, 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             ACTION BUTTONS
        ====================================================== -->
        <div class="action-buttons">
            <button type="button" class="btn btn-gold" onclick="downloadPDF()">
                <i class="bx bx-download"></i> Download PDF Statement
            </button>
            <a href="personal.php" class="btn btn-outline">
                <i class="bx bx-plus-circle"></i> New Investment / Loan / Proposal
            </a>
        </div>

        <!-- ======================================================
             INVESTMENT HISTORY
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-trending-up"></i> Investment History</h2>
        </div>

        <?php if (!empty($investments)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>ROI</th>
                            <th>Visibility</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $i => $inv): ?>
                            <tr>
                                <td>#<?= $i + 1 ?></td>
                                <td style="color:rgba(255,255,255,0.65);font-size:0.82rem;"><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($inv['title']) ?></strong></td>
                                <td style="color:rgba(255,255,255,0.75);"><?= htmlspecialchars($inv['category']) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($inv['amount'], 2) ?></strong></td>
                                <td><?= $inv['expected_roi'] !== null ? number_format($inv['expected_roi'], 2) . '%' : '—' ?></td>
                                <td>
                                    <span class="visibility-badge <?= htmlspecialchars($inv['visibility']) ?>">
                                        <?= htmlspecialchars($inv['visibility']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($inv['status']) ?>">
                                        <?= htmlspecialchars($inv['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-trending-up"></i>
                <strong>No investments recorded yet</strong>
                <p>Post your first one on the Personal page.</p>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             LOAN HISTORY
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-credit-card"></i> Loan Applications</h2>
        </div>

        <?php if (!empty($loans)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Principal</th>
                            <th>Interest (25%)</th>
                            <th>Total Repayable</th>
                            <th>Repaid</th>
                            <th>Visibility</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $i => $loan): ?>
                            <tr>
                                <td>#<?= $i + 1 ?></td>
                                <td style="color:rgba(255,255,255,0.65);font-size:0.82rem;"><?= date('d M Y', strtotime($loan['created_at'])) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($loan['principal'], 2) ?></strong></td>
                                <td style="color:var(--warning);font-weight:700;">R<?= number_format($loan['interest_amount'], 2) ?></td>
                                <td><strong style="color:var(--gold-light);">R<?= number_format($loan['total_repayable'], 2) ?></strong></td>
                                <td style="color:var(--success);font-weight:600;">R<?= number_format($loan['amount_repaid'], 2) ?></td>
                                <td>
                                    <span class="visibility-badge <?= htmlspecialchars($loan['visibility']) ?>">
                                        <?= htmlspecialchars($loan['visibility']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($loan['status']) ?>">
                                        <?= htmlspecialchars($loan['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-credit-card"></i>
                <strong>No loan applications yet</strong>
                <p>Your loan history will appear here once you apply.</p>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             PROPOSAL HISTORY
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-bulb"></i> Business Proposals</h2>
        </div>

        <?php if (!empty($proposals)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Amount Needed</th>
                            <th>Visibility</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proposals as $i => $p): ?>
                            <tr>
                                <td>#<?= $i + 1 ?></td>
                                <td style="color:rgba(255,255,255,0.65);font-size:0.82rem;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($p['type']) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($p['amount_needed'], 2) ?></strong></td>
                                <td>
                                    <span class="visibility-badge <?= htmlspecialchars($p['visibility']) ?>">
                                        <?= htmlspecialchars($p['visibility']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($p['status']) ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-bulb"></i>
                <strong>No proposals yet</strong>
                <p>Post one on the Personal page to see it here.</p>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             NOTIFICATIONS
        ====================================================== -->
        <?php if (!empty($notifications)): ?>
            <div class="notifications">
                <h3><i class="bx bx-bell"></i> Recent Notifications</h3>
                <ul>
                    <?php foreach ($notifications as $n): ?>
                        <li>
                            <strong><?= htmlspecialchars($n['type']) ?></strong>
                            of
                            <span class="amount">R<?= number_format($n['amount'], 2) ?></span>
                            was
                            <strong><?= strtoupper(htmlspecialchars($n['status'])) ?></strong>
                            on <?= date('d M Y', strtotime($n['created_at'])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </main>

    <!-- ======================================================
         HIDDEN PDF STATEMENT
    ====================================================== -->
    <div id="pdf-statement">
        <div class="pdf-header">
            <img src="/files/SFS-LOGO.png" alt="SFS Logo" class="pdf-logo">
            <h1>SENOAMADI FINANCIAL SERVICES</h1>
            <p>Official Investment &amp; Financial Statement</p>
            <p><strong>Statement Date:</strong> <?= $statementDate ?></p>
        </div>

        <div class="pdf-section">
            <h2>Account Holder</h2>
            <div class="pdf-holder">
                <img
                    src="<?= !empty($userProfilePic) ? htmlspecialchars($userProfilePic) : '/files/SFS-LOGO.png' ?>"
                    alt="Profile"
                    class="pdf-avatar"
                    onerror="this.onerror=null;this.src='/files/SFS-LOGO.png';"
                >
                <div class="pdf-holder-info">
                    <div class="name"><?= htmlspecialchars($userName) ?></div>
                    <div class="meta"><strong>Email:</strong> <?= htmlspecialchars($userEmail) ?></div>
                    <div class="meta"><strong>User ID:</strong> #<?= str_pad((string)$uid, 6, '0', STR_PAD_LEFT) ?></div>
                </div>
            </div>
        </div>

        <div class="pdf-section">
            <h2>Financial Summary</h2>
            <div class="pdf-info-grid">
                <div class="pdf-info-item">
                    <div class="label">Current Balance</div>
                    <div class="value">R<?= number_format($current_balance, 2) ?></div>
                </div>
                <div class="pdf-info-item">
                    <div class="label">Total Invested</div>
                    <div class="value">R<?= number_format($totalInvested, 2) ?></div>
                </div>
                <div class="pdf-info-item">
                    <div class="label">Total Owed</div>
                    <div class="value">R<?= number_format($totalOwed, 2) ?></div>
                </div>
                <div class="pdf-info-item">
                    <div class="label">Net Worth</div>
                    <div class="value" style="color: <?= $netWorth >= 0 ? '#155724' : '#721c24' ?>;">
                        R<?= number_format($netWorth, 2) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="pdf-section">
            <h2>Investment History</h2>
            <?php if (!empty($investments)): ?>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $i => $inv): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                                <td><?= htmlspecialchars($inv['title']) ?></td>
                                <td><?= htmlspecialchars($inv['category']) ?></td>
                                <td>R<?= number_format($inv['amount'], 2) ?></td>
                                <td>
                                    <span class="pdf-badge <?= htmlspecialchars($inv['status']) ?>">
                                        <?= htmlspecialchars($inv['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No investments recorded.</p>
            <?php endif; ?>
        </div>

        <div class="pdf-section">
            <h2>Loan Applications</h2>
            <?php if (!empty($loans)): ?>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Total Repayable</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $i => $loan): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= date('d M Y', strtotime($loan['created_at'])) ?></td>
                                <td>R<?= number_format($loan['principal'], 2) ?></td>
                                <td>R<?= number_format($loan['interest_amount'], 2) ?></td>
                                <td>R<?= number_format($loan['total_repayable'], 2) ?></td>
                                <td>
                                    <span class="pdf-badge <?= htmlspecialchars($loan['status']) ?>">
                                        <?= htmlspecialchars($loan['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No loan applications recorded.</p>
            <?php endif; ?>
        </div>

        <div class="pdf-section">
            <h2>Business Proposals</h2>
            <?php if (!empty($proposals)): ?>
                <table class="pdf-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Amount Needed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proposals as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                                <td><?= htmlspecialchars($p['title']) ?></td>
                                <td><?= htmlspecialchars($p['type']) ?></td>
                                <td>R<?= number_format($p['amount_needed'], 2) ?></td>
                                <td>
                                    <span class="pdf-badge <?= htmlspecialchars($p['status']) ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No proposals recorded.</p>
            <?php endif; ?>
        </div>

        <div class="pdf-footer">
            <p>This is a computer-generated statement from Senoamadi Financial Services.</p>
            <p>Generated on <?= date('d F Y \a\t H:i') ?> | © <?= date('Y') ?> Senoamadi Financial Services. All rights reserved.</p>
        </div>
    </div>

    <!-- Floating chat icon (mobile) -->
    <div id="chat-icon" onclick="alert('AI Buddy coming soon!')">
        <i class='bx bx-message-rounded-dots'></i>
    </div>

    <script>
        // =====================================================
        // PDF DOWNLOAD
        // =====================================================
        function downloadPDF() {
            const pdfStatement = document.getElementById('pdf-statement');
            pdfStatement.style.display = 'block';

            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    pdfStatement.style.display = 'none';
                }, 500);
            }, 100);
        }

        // =====================================================
        // KEYBOARD SHORTCUT: Ctrl + P → PDF
        // =====================================================
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                downloadPDF();
            }
        });

        console.log('✅ Track Money page loaded for <?= htmlspecialchars($userName, ENT_QUOTES) ?>');
    </script>

</body>

</html>