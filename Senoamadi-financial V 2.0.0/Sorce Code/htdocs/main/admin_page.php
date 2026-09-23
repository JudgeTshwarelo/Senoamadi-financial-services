<?php
session_start();
require_once 'config.php';

// =========================================================
// ADMIN ACCESS GATE
// =========================================================
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$userRole   = $_SESSION['role'] ?? 'user';
$userEmail  = $_SESSION['email'] ?? '';
$adminEmails = ['admin@senoamadi.com', 'judge@senoamadi.com', 'baloyijudge@gmail.com'];

$isAdmin = ($userRole === 'admin') || in_array(strtolower($userEmail), array_map('strtolower', $adminEmails));

if (!$isAdmin) {
    header("Location: user_page.php");
    exit();
}

$uid      = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$userName = $_SESSION['name'] ?? $_SESSION['name'] ?? 'Admin';

// =========================================================
// DB CONNECTION
// =========================================================
try {
    $db = get_db();
} catch (PDOException $e) {
    die("Database connection failed.");
}

// =========================================================
// AUTO-CREATE / ENSURE TABLES EXIST
// =========================================================
$db->exec("
    CREATE TABLE IF NOT EXISTS admin_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(60) NOT NULL,
        target_type VARCHAR(40) NOT NULL,
        target_id INT NOT NULL,
        details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_admin (admin_id),
        INDEX idx_audit_target (target_type, target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Ensure feedback admin columns
try {
    $db->exec("ALTER TABLE feedback
        ADD COLUMN IF NOT EXISTS priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
        ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL,
        ADD COLUMN IF NOT EXISTS replied_at DATETIME NULL,
        ADD COLUMN IF NOT EXISTS replied_by VARCHAR(100) NULL
    ");
} catch (PDOException $e) {}

// =========================================================
// AUDIT HELPER
// =========================================================
function logAudit($db, $adminId, $action, $targetType, $targetId, $details = '') {
    try {
        $stmt = $db->prepare("
            INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$adminId, $action, $targetType, $targetId, $details]);
    } catch (PDOException $e) {}
}

// =========================================================
// HANDLE ADMIN ACTIONS
// =========================================================
$flash     = '';
$flashType = '';

// ---------- PROPOSALS (personal_proposals) ----------
// Allowed statuses: pending, approved, rejected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposal_action'])) {
    $pid    = (int) ($_POST['proposal_id'] ?? 0);
    $action = $_POST['proposal_action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'reset' => 'pending'];

    if ($pid > 0 && isset($map[$action])) {
        try {
            $stmt = $db->prepare("
                UPDATE personal_proposals
                SET status = ?, admin_note = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$map[$action], $note ?: null, $uid, $pid]);
            logAudit($db, $uid, 'proposal_' . $action, 'personal_proposal', $pid, $note);
            $flash = 'Proposal #' . $pid . ' set to ' . $map[$action] . '.';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    }
}

// ---------- PERSONAL INVESTMENTS ----------
// Allowed statuses: pending, approved, rejected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pi_action'])) {
    $piid   = (int) ($_POST['pi_id'] ?? 0);
    $action = $_POST['pi_action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'reset' => 'pending'];

    if ($piid > 0 && isset($map[$action])) {
        try {
            $stmt = $db->prepare("
                UPDATE personal_investments
                SET status = ?, admin_note = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$map[$action], $note ?: null, $uid, $piid]);
            logAudit($db, $uid, 'investment_' . $action, 'personal_investment', $piid, $note);
            $flash = 'Investment #' . $piid . ' set to ' . $map[$action] . '.';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    }
}

// ---------- LOANS (personal_loans) ----------
// Allowed statuses: pending, approved, rejected, paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_action'])) {
    $lid    = (int) ($_POST['loan_id'] ?? 0);
    $action = $_POST['loan_action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');
    $map = [
        'approve' => 'approved',
        'reject'  => 'rejected',
        'paid'    => 'paid',
        'reset'   => 'pending',
    ];

    if ($lid > 0 && isset($map[$action])) {
        try {
            $stmt = $db->prepare("
                UPDATE personal_loans
                SET status = ?, admin_note = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$map[$action], $note ?: null, $uid, $lid]);
            logAudit($db, $uid, 'loan_' . $action, 'personal_loan', $lid, $note);
            $flash = 'Loan #' . $lid . ' set to ' . $map[$action] . '.';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    }
}

// ---------- FEEDBACK ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_action'])) {
    $fid    = (int) ($_POST['feedback_id'] ?? 0);
    $action = $_POST['feedback_action'] ?? '';
    $reply  = trim($_POST['admin_reply'] ?? '');
    $statusMap = ['review' => 'in_review', 'resolve' => 'resolved', 'close' => 'closed', 'reopen' => 'new'];

    if ($fid > 0 && isset($statusMap[$action])) {
        try {
            $stmt = $db->prepare("
                UPDATE feedback
                SET status = ?, admin_reply = ?, replied_at = NOW(), replied_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$statusMap[$action], $reply ?: null, $userName, $fid]);
            logAudit($db, $uid, 'feedback_' . $action, 'feedback', $fid, $reply);
            $flash = 'Feedback #' . $fid . ' updated.';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    }
}

// =========================================================
// FETCH DATA
// =========================================================

// Users
$users = [];
try {
    $stmt = $db->query("SELECT id, name, email, role FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// All proposals (private + public — admin sees everything)
$proposals = [];
try {
    $stmt = $db->query("
        SELECT p.*, u.name AS user_name, u.email AS user_email
        FROM personal_proposals p
        LEFT JOIN users u ON u.id = p.user_id
        ORDER BY p.created_at DESC
        LIMIT 200
    ");
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $proposals = [];
}

// All personal investments (private + public)
$personalInvestments = [];
try {
    $stmt = $db->query("
        SELECT pi.*, u.name AS user_name, u.email AS user_email
        FROM personal_investments pi
        LEFT JOIN users u ON u.id = pi.user_id
        ORDER BY pi.created_at DESC
        LIMIT 200
    ");
    $personalInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $personalInvestments = [];
}

// All personal loans (private + public)
$loans = [];
try {
    $stmt = $db->query("
        SELECT l.*, u.name AS user_name, u.email AS user_email
        FROM personal_loans l
        LEFT JOIN users u ON u.id = l.user_id
        ORDER BY l.created_at DESC
        LIMIT 200
    ");
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $loans = [];
}

// Feedback
$feedback = [];
try {
    $stmt = $db->query("
        SELECT f.*, u.name AS user_name, u.email AS user_email
        FROM feedback f
        LEFT JOIN users u ON u.id = f.user_id
        ORDER BY
            CASE f.priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END,
            f.created_at DESC
        LIMIT 200
    ");
    $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = [];
}

// Audit log
$auditLog = [];
try {
    $stmt = $db->query("
        SELECT a.*, u.name AS admin_name
        FROM admin_audit_log a
        LEFT JOIN users u ON u.id = a.admin_id
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    $auditLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $auditLog = [];
}

// =========================================================
// ANALYTICS
// =========================================================
$stats = [
    'users'                => count($users),
    'proposals'            => count($proposals),
    'proposals_pending'    => 0,
    'proposals_approved'   => 0,
    'proposals_rejected'   => 0,
    'feedback'             => count($feedback),
    'feedback_new'         => 0,
    'feedback_urgent'      => 0,
    'personal_inv'         => count($personalInvestments),
    'personal_pending'     => 0,
    'personal_approved'    => 0,
    'loans'                => count($loans),
    'loans_pending'        => 0,
    'loans_approved'       => 0,
    'loans_paid'           => 0,
    'loans_total_value'    => 0,
    'total_balance'        => 0,
    'total_invested'       => 0,
    'total_loan_principal' => 0,
    'total_loan_interest'  => 0,
];

foreach ($proposals as $p) {
    $s = $p['status'] ?? 'pending';
    if ($s === 'pending')  $stats['proposals_pending']++;
    if ($s === 'approved') $stats['proposals_approved']++;
    if ($s === 'rejected') $stats['proposals_rejected']++;
}

foreach ($feedback as $f) {
    if (($f['status'] ?? '') === 'new')      $stats['feedback_new']++;
    if (($f['priority'] ?? '') === 'urgent') $stats['feedback_urgent']++;
}

foreach ($personalInvestments as $pi) {
    $s = $pi['status'];
    if ($s === 'pending')  $stats['personal_pending']++;
    if ($s === 'approved') $stats['personal_approved']++;
    if ($s === 'approved') {
        $stats['total_invested'] += (float) $pi['amount'];
    }
}

foreach ($loans as $l) {
    $s = strtolower($l['status']);
    if ($s === 'pending')  $stats['loans_pending']++;
    if ($s === 'approved') $stats['loans_approved']++;
    if ($s === 'paid')     $stats['loans_paid']++;

    $stats['total_loan_principal'] += (float) $l['principal'];
    $stats['total_loan_interest']  += (float) $l['interest_amount'];
    $stats['loans_total_value']    += (float) $l['total_repayable'];
}

foreach ($users as $u) {
    $stats['total_balance'] += (float) ($u['balance'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ADMIN CENTER | SENOAMADI_BANK</title>
    <meta name="description" content="Full admin oversight for Senoamadi Bank." />
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
           SIDEBAR NAVIGATION
        ====================================================== */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            z-index: 1000;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-y: auto;
            transition: .3s;

            background: rgba(4, 11, 22, .85);
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
            pointer-events: none;
        }

        nav::-webkit-scrollbar { width: 4px; }
        nav::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }

        .logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
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

        .logo span.brand {
            font-size: .85rem;
            font-weight: 700;
            letter-spacing: .5px;
            color: var(--gold-light);
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, rgba(231, 76, 60, .25), rgba(231, 76, 60, .1));
            color: #ff6b6b;
            font-size: .65rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 999px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            border: 1px solid rgba(231, 76, 60, .4);
            margin-top: 6px;
        }

        .menu-section-title {
            width: 100%;
            color: rgba(255, 255, 255, .35);
            font-size: .62rem;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            padding: 14px 12px 6px;
            font-weight: 700;
        }

        .menu-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 100%;
        }

        .menu-item {
            width: 100%;
            padding: 11px 13px;
            border-radius: 12px;
            color: rgba(255, 255, 255, .75);
            display: flex;
            align-items: center;
            gap: 13px;
            transition: .3s;
            font-weight: 500;
            font-size: .85rem;
            cursor: pointer;
            position: relative;
        }

        .menu-item i { font-size: 19px; }

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
            left: -16px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 60%;
            background: var(--gold);
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px var(--gold);
        }

        .menu-item .badge-count {
            margin-left: auto;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            font-size: .65rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(231, 76, 60, .4);
        }

        .logout-btn { margin-top: 8px; color: rgba(255, 120, 120, .85); }
        .logout-btn:hover { background: rgba(192, 57, 43, .2); color: #ff6b6b; }

        /* ======================================================
           MAIN
        ====================================================== */
        main {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 34px 44px 80px;
            transition: .3s;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 32px;
            padding-bottom: 24px;
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

        .admin-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(231, 76, 60, .2), rgba(231, 76, 60, .05));
            color: #ff6b6b;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 14px;
            border: 1px solid rgba(231, 76, 60, .35);
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
           KPI CARDS
        ====================================================== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin: 26px 0 34px;
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
            font-size: 1.55rem;
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

        .kpi-card .kpi-sub {
            font-size: .74rem;
            color: rgba(255, 255, 255, .5);
            margin-top: 8px;
        }

        /* ======================================================
           TABS
        ====================================================== */
        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 22px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .tab-btn {
            padding: 11px 18px;
            background: transparent;
            color: rgba(255, 255, 255, .55);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
            border-radius: 999px;
            font-size: .85rem;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }

        .tab-btn:hover {
            color: var(--gold-light);
            background: rgba(212, 175, 55, .08);
        }

        .tab-btn.active {
            color: var(--navy);
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            box-shadow: 0 8px 22px rgba(212, 175, 55, .3);
        }

        .tab-btn .tab-count {
            background: rgba(255, 255, 255, .15);
            color: inherit;
            font-size: .68rem;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 800;
        }

        .tab-btn.active .tab-count {
            background: rgba(4, 11, 22, .25);
        }

        .tab-panel { display: none; animation: fadeIn 0.4s ease; }
        .tab-panel.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .tab-panel h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.35rem;
            font-weight: 700;
            color: white;
            margin: 24px 0 6px;
            padding-left: 16px;
            position: relative;
        }

        .tab-panel h2::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 24px;
            border-radius: 4px;
            background: linear-gradient(180deg, var(--gold), rgba(212, 175, 55, .2));
            box-shadow: 0 0 16px rgba(212, 175, 55, .5);
        }

        .tab-panel h2 i { color: var(--gold); }

        .tab-panel > p {
            color: rgba(255, 255, 255, .55);
            font-size: .88rem;
            margin-bottom: 12px;
        }

        /* ======================================================
           FILTERS
        ====================================================== */
        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 18px 0;
            align-items: center;
        }

        .search-box {
            flex: 1 1 220px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(4, 11, 22, .6);
            color: white;
            font-size: .9rem;
            font-family: inherit;
            transition: .3s;
            outline: none;
        }

        .search-box input::placeholder { color: rgba(255, 255, 255, .35); }

        .search-box input:focus {
            border-color: var(--gold);
            background: rgba(4, 11, 22, .85);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, .12);
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: 18px;
        }

        .filter-select {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(4, 11, 22, .6);
            color: white;
            font-size: .85rem;
            font-family: inherit;
            cursor: pointer;
            outline: none;
            transition: .3s;
        }

        .filter-select:focus { border-color: var(--gold); }
        .filter-select option { background: var(--navy); color: white; }

        /* ======================================================
           TABLE
        ====================================================== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 18px;
            margin-top: 12px;
            border: 1px solid rgba(212, 175, 55, .18);
            background: rgba(8, 20, 40, .5);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
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

        /* Badges */
        .badge {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-block;
            white-space: nowrap;
        }

        .badge.pending  { background: rgba(241, 196, 15, .15); color: var(--warning); border: 1px solid rgba(241, 196, 15, .35); }
        .badge.approved,
        .badge.active,
        .badge.resolved { background: rgba(46, 204, 113, .15); color: var(--success); border: 1px solid rgba(46, 204, 113, .35); }
        .badge.rejected,
        .badge.cancelled,
        .badge.closed   { background: rgba(231, 76, 60, .15); color: var(--danger); border: 1px solid rgba(231, 76, 60, .35); }
        .badge.in_review { background: rgba(52, 152, 219, .15); color: var(--info); border: 1px solid rgba(52, 152, 219, .35); }
        .badge.paid,
        .badge.repaid    { background: rgba(52, 152, 219, .15); color: var(--info); border: 1px solid rgba(52, 152, 219, .35); }
        .badge.admin     { background: rgba(212, 175, 55, .18); color: var(--gold-light); border: 1px solid rgba(212, 175, 55, .4); }
        .badge.user      { background: rgba(116, 185, 255, .15); color: var(--light); border: 1px solid rgba(116, 185, 255, .35); }
        .badge.private   { background: rgba(139, 163, 199, .15); color: #8ba3c7; border: 1px solid rgba(139, 163, 199, .35); }
        .badge.public    { background: rgba(212, 175, 55, .18); color: var(--gold-light); border: 1px solid rgba(212, 175, 55, .4); }

        .priority-pill {
            padding: 3px 11px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .priority-pill.low     { background: rgba(46, 204, 113, .15); color: var(--success); }
        .priority-pill.medium  { background: rgba(52, 152, 219, .15); color: var(--info); }
        .priority-pill.high    { background: rgba(241, 196, 15, .15); color: var(--warning); }
        .priority-pill.urgent  { background: rgba(231, 76, 60, .15); color: var(--danger); }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 13px;
            border-radius: 10px;
            font-weight: 700;
            font-size: .74rem;
            cursor: pointer;
            transition: .25s;
            border: 1px solid transparent;
            font-family: inherit;
            white-space: nowrap;
            letter-spacing: .3px;
        }

        .btn i { font-size: 15px; }

        .btn-approve {
            background: rgba(46, 204, 113, .12);
            color: #2ecc71;
            border-color: rgba(46, 204, 113, .4);
        }
        .btn-approve:hover { background: #2ecc71; color: white; border-color: #2ecc71; transform: translateY(-1px); }

        .btn-reject {
            background: rgba(231, 76, 60, .12);
            color: #e74c3c;
            border-color: rgba(231, 76, 60, .4);
        }
        .btn-reject:hover { background: #e74c3c; color: white; border-color: #e74c3c; transform: translateY(-1px); }

        .btn-info {
            background: rgba(52, 152, 219, .12);
            color: #3498db;
            border-color: rgba(52, 152, 219, .4);
        }
        .btn-info:hover { background: #3498db; color: white; border-color: #3498db; transform: translateY(-1px); }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        /* Message */
        .message {
            padding: 15px 20px;
            border-radius: 14px;
            margin-bottom: 22px;
            font-weight: 600;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: .9rem;
        }
        .message.success { background: rgba(46, 204, 113, .12); color: #2ecc71; border: 1px solid rgba(46, 204, 113, .35); }
        .message.error   { background: rgba(231, 76, 60, .12);  color: #ff6b6b; border: 1px solid rgba(231, 76, 60, .35); }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 14, .8);
            backdrop-filter: blur(8px);
            z-index: 5000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-overlay.active { display: flex; }

        .modal {
            background: rgba(8, 20, 40, .95);
            border: 1px solid rgba(212, 175, 55, .4);
            border-radius: 22px;
            padding: 30px;
            max-width: 580px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            color: white;
            position: relative;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .6), 0 0 60px rgba(212, 175, 55, .1);
            animation: modalIn 0.3s ease;
        }

        .modal::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(212, 175, 55, .14), transparent 70%);
            transform: translate(35%, -35%);
            pointer-events: none;
            border-radius: 22px;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.92) translateY(20px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal h3 {
            color: var(--gold-light);
            margin-bottom: 16px;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 2;
        }

        .modal h3 i { color: var(--gold); }

        .modal-close {
            position: absolute;
            top: 18px;
            right: 18px;
            background: rgba(255, 255, 255, .05);
            border: none;
            color: rgba(255, 255, 255, .7);
            font-size: 22px;
            line-height: 1;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .3s;
            z-index: 3;
        }
        .modal-close:hover { background: rgba(231, 76, 60, .2); color: #ff6b6b; }

        .modal-body {
            font-size: .9rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, .82);
            margin-bottom: 18px;
            position: relative;
            z-index: 2;
        }

        .modal-body p { margin-bottom: 8px; }

        .modal-meta {
            font-size: .8rem;
            color: rgba(255, 255, 255, .6);
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            position: relative;
            z-index: 2;
        }

        .modal textarea {
            width: 100%;
            min-height: 90px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(4, 11, 22, .6);
            color: white;
            font-family: inherit;
            font-size: .9rem;
            resize: vertical;
            margin-bottom: 14px;
            outline: none;
            transition: .3s;
            position: relative;
            z-index: 2;
        }

        .modal textarea:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, .12);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, .55);
            border-radius: 18px;
            background: rgba(8, 20, 40, .4);
            border: 1px dashed rgba(212, 175, 55, .25);
            margin-top: 12px;
        }
        .empty-state i {
            font-size: 2.8rem;
            color: var(--gold);
            margin-bottom: 12px;
            display: block;
            opacity: .7;
        }
        .empty-state strong {
            display: block;
            color: white;
            font-size: 1.1rem;
            margin-bottom: 6px;
        }

        /* Audit log */
        .audit-item {
            padding: 14px 18px;
            border-left: 3px solid var(--gold);
            background: rgba(8, 20, 40, .55);
            border-radius: 12px;
            margin-bottom: 10px;
            font-size: .88rem;
            transition: .25s;
        }

        .audit-item:hover {
            background: rgba(8, 20, 40, .8);
            transform: translateX(3px);
        }

        .audit-item .audit-time {
            color: rgba(255, 255, 255, .45);
            font-size: .75rem;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ======================================================
           MOBILE NAV
        ====================================================== */
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

            .logo,
            .admin-badge,
            .menu-section-title { display: none; }

            .menu-links {
                flex-direction: row;
                justify-content: space-around;
                align-items: center;
                gap: 0;
                height: 100%;
                width: 100%;
            }

            .menu-links:nth-of-type(n+3) { display: none; }

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
                flex: 1;
            }

            .menu-item.active::before { display: none; }

            .menu-item i { font-size: 20px; }
            .menu-item span { font-size: 9px; white-space: nowrap; }

            .menu-item .badge-count {
                position: absolute;
                top: 0;
                right: 22%;
                font-size: .55rem;
                padding: 1px 5px;
                margin-left: 0;
            }

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

            .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .kpi-card { padding: 16px 14px; }
            .kpi-card .kpi-value { font-size: 1.3rem; }
            .kpi-card .kpi-icon { width: 36px; height: 36px; }
            .kpi-card .kpi-icon i { font-size: 17px; }

            .tab-btn { padding: 9px 13px; font-size: .78rem; }

            .filters-row { flex-direction: column; align-items: stretch; }
            .search-box,
            .filter-select { width: 100%; }

            .tab-panel h2 { font-size: 1.1rem; }
            .modal { padding: 22px 18px; border-radius: 18px; }
        }

        @media(max-width: 420px) {
            .kpi-grid { grid-template-columns: 1fr; }
        }

        @media(min-width:1600px) {
            main { padding: 50px 60px 100px; }
            .kpi-card .kpi-value { font-size: 1.9rem; }
        }
    </style>
</head>

<body>

    <!-- ======================================================
         ADMIN SIDEBAR
    ====================================================== -->
    <nav>
        <div class="logo">
            <div class="logo-orbit">
                <div class="orbit-ring"></div>
                <img src="/files/SFS-LOGO.png" alt="Senoamadi Bank Logo" />
            </div>
        </div>

        <div class="menu-section-title">Overview</div>
        <div class="menu-links">
            <a class="menu-item active" href="admin_page.php">
                <i class="bx bx-grid-alt"></i>
                <span>DASHBOARD</span>
            </a>
            <a class="menu-item" href="#" onclick="switchTab(event, 'tab-users'); return false;">
                <i class="bx bx-group"></i>
                <span>USERS</span>
                <span class="badge-count"><?= $stats['users'] ?></span>
            </a>
            <a class="menu-item" href="admin_investments.php">
                <i class="bx bx-briefcase"></i>
                <span>MY DESK</span>
            </a>
        </div>

        <div class="menu-section-title">Approvals</div>
        <div class="menu-links">
            <a class="menu-item" href="#" onclick="switchTab(event, 'tab-proposals'); return false;">
                <i class="bx bx-bulb"></i>
                <span>PROPOSALS</span>
                <?php if ($stats['proposals_pending'] > 0): ?>
                    <span class="badge-count"><?= $stats['proposals_pending'] ?></span>
                <?php endif; ?>
            </a>
            <a class="menu-item" href="#" onclick="switchTab(event, 'tab-personal'); return false;">
                <i class="bx bx-piggy-bank"></i>
                <span>INVESTMENTS</span>
                <?php if ($stats['personal_pending'] > 0): ?>
                    <span class="badge-count"><?= $stats['personal_pending'] ?></span>
                <?php endif; ?>
            </a>
            <a class="menu-item" href="#" onclick="switchTab(event, 'tab-loans'); return false;">
                <i class="bx bx-money"></i>
                <span>LOANS</span>
                <?php if ($stats['loans_pending'] > 0): ?>
                    <span class="badge-count"><?= $stats['loans_pending'] ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="menu-section-title">Support</div>
        <div class="menu-links">
            <a class="menu-item" href="#" onclick="switchTab(event, 'tab-feedback'); return false;">
                <i class="bx bx-envelope"></i>
                <span>FEEDBACK</span>
                <?php if ($stats['feedback_new'] > 0): ?>
                    <span class="badge-count"><?= $stats['feedback_new'] ?></span>
                <?php endif; ?>
            </a>
            <a class="menu-item" href="#" onclick="switchTab(event, 'tab-audit'); return false;">
                <i class="bx bx-history"></i>
                <span>AUDIT LOG</span>
            </a>
        </div>

        <div class="menu-section-title">System</div>
        <div class="menu-links">
            <a class="menu-item" href="user_page.php">
                <i class="bx bx-user"></i>
                <span>USER VIEW</span>
            </a>
            <a class="menu-item logout-btn" href="logout.php">
                <i class="bx bx-log-out"></i>
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
                <h1>Admin Control Center</h1>
                <p>
                    <strong style="color:var(--gold-light);"><?= htmlspecialchars($userName) ?></strong>.
                    Full oversight across users, proposals, investments, loans, and feedback.
                </p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="message <?= $flashType ?>">
                <i class="bx <?= $flashType === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?>"></i>
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             KPI GRID
        ====================================================== -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-group"></i></div>
                <div class="kpi-label">Total Users</div>
                <div class="kpi-value"><?= $stats['users'] ?></div>
                <div class="kpi-sub">Registered members</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-bulb"></i></div>
                <div class="kpi-label">Proposals</div>
                <div class="kpi-value"><?= $stats['proposals'] ?></div>
                <div class="kpi-sub"><?= $stats['proposals_pending'] ?> pending review</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-piggy-bank"></i></div>
                <div class="kpi-label">Investments</div>
                <div class="kpi-value"><?= $stats['personal_inv'] ?></div>
                <div class="kpi-sub"><?= $stats['personal_pending'] ?> pending</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-money"></i></div>
                <div class="kpi-label">Loans</div>
                <div class="kpi-value"><?= $stats['loans'] ?></div>
                <div class="kpi-sub"><?= $stats['loans_pending'] ?> pending</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-envelope"></i></div>
                <div class="kpi-label">Feedback</div>
                <div class="kpi-value"><?= $stats['feedback'] ?></div>
                <div class="kpi-sub">
                    <?= $stats['feedback_new'] ?> new
                    <?php if ($stats['feedback_urgent'] > 0): ?>
                        • <span style="color:#ff6b6b;"><?= $stats['feedback_urgent'] ?> urgent</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-wallet"></i></div>
                <div class="kpi-label">Total Balance</div>
                <div class="kpi-value gold">R<?= number_format($stats['total_balance'], 2) ?></div>
                <div class="kpi-sub">Across all users</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-check-shield"></i></div>
                <div class="kpi-label">Approved Invested</div>
                <div class="kpi-value gold">R<?= number_format($stats['total_invested'], 2) ?></div>
                <div class="kpi-sub">Approved investments</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="bx bx-credit-card"></i></div>
                <div class="kpi-label">Loan Book</div>
                <div class="kpi-value gold">R<?= number_format($stats['loans_total_value'], 2) ?></div>
                <div class="kpi-sub">
                    Principal R<?= number_format($stats['total_loan_principal'], 2) ?>
                    • Interest R<?= number_format($stats['total_loan_interest'], 2) ?>
                </div>
            </div>
        </div>

        <!-- ======================================================
             TABS
        ====================================================== -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-proposals')">
                <i class="bx bx-bulb"></i> Proposals
                <span class="tab-count"><?= $stats['proposals'] ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-personal')">
                <i class="bx bx-piggy-bank"></i> Investments
                <span class="tab-count"><?= $stats['personal_inv'] ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-loans')">
                <i class="bx bx-money"></i> Loans
                <span class="tab-count"><?= $stats['loans'] ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-feedback')">
                <i class="bx bx-envelope"></i> Feedback
                <span class="tab-count"><?= $stats['feedback'] ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-users')">
                <i class="bx bx-group"></i> Users
                <span class="tab-count"><?= $stats['users'] ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-audit')">
                <i class="bx bx-history"></i> Audit Log
            </button>
        </div>

        <!-- ======================================================
             TAB: PROPOSALS
        ====================================================== -->
        <div id="tab-proposals" class="tab-panel active">
            <h2><i class="bx bx-bulb"></i> Business Proposals</h2>
            <p>All proposals from users (private + public).</p>

            <div class="filters-row">
                <div class="search-box">
                    <i class="bx bx-search"></i>
                    <input type="text" placeholder="Search by title, user, or description..." oninput="filterTable('tbl-proposals', this.value)">
                </div>
                <select class="filter-select" onchange="filterByStatus('tbl-proposals', this.value)">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <?php if (!empty($proposals)): ?>
                <div class="table-wrapper">
                    <table id="tbl-proposals">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Visibility</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proposals as $p):
                                $astatus = $p['status'] ?? 'pending';
                            ?>
                                <tr data-status="<?= htmlspecialchars($astatus) ?>">
                                    <td>#<?= (int) $p['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($p['title']) ?></strong>
                                        <?php if (!empty($p['business_plan'])): ?>
                                            <a href="<?= htmlspecialchars($p['business_plan']) ?>" target="_blank" style="color:var(--gold);display:inline-block;margin-left:6px;" title="View attachment">
                                                <i class="bx bx-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);margin-top:2px;">
                                            <?= htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 55, '…')) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($p['user_name'] ?? 'Unknown') ?>
                                        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($p['user_email'] ?? '') ?></div>
                                    </td>
                                    <td style="text-transform:capitalize;"><?= htmlspecialchars($p['type'] ?? '—') ?></td>
                                    <td><strong style="color:var(--gold);">R<?= number_format($p['amount_needed'] ?? 0, 2) ?></strong></td>
                                    <td><span class="badge <?= htmlspecialchars($p['visibility'] ?? 'private') ?>"><?= htmlspecialchars($p['visibility'] ?? 'private') ?></span></td>
                                    <td><span class="badge <?= htmlspecialchars($astatus) ?>"><?= htmlspecialchars($astatus) ?></span></td>
                                    <td style="font-size:0.78rem;color:rgba(255,255,255,0.5);"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info" onclick='openProposalModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'>
                                                <i class="bx bx-show"></i> View
                                            </button>
                                            <?php if ($astatus !== 'approved'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this proposal?');">
                                                    <input type="hidden" name="proposal_id" value="<?= (int) $p['id'] ?>">
                                                    <button type="submit" name="proposal_action" value="approve" class="btn btn-approve">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($astatus !== 'rejected'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this proposal?');">
                                                    <input type="hidden" name="proposal_id" value="<?= (int) $p['id'] ?>">
                                                    <button type="submit" name="proposal_action" value="reject" class="btn btn-reject">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
                    <p>Proposals submitted by members will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             TAB: INVESTMENTS
        ====================================================== -->
        <div id="tab-personal" class="tab-panel">
            <h2><i class="bx bx-piggy-bank"></i> Personal Investments</h2>
            <p>All investments (private + public).</p>

            <div class="filters-row">
                <div class="search-box">
                    <i class="bx bx-search"></i>
                    <input type="text" placeholder="Search by title or user..." oninput="filterTable('tbl-personal', this.value)">
                </div>
                <select class="filter-select" onchange="filterByStatus('tbl-personal', this.value)">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <?php if (!empty($personalInvestments)): ?>
                <div class="table-wrapper">
                    <table id="tbl-personal">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>ROI</th>
                                <th>Duration</th>
                                <th>Visibility</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personalInvestments as $pi): ?>
                                <tr data-status="<?= htmlspecialchars($pi['status']) ?>">
                                    <td>#<?= (int) $pi['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($pi['title']) ?></strong>
                                        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);margin-top:2px;">
                                            <?= htmlspecialchars(mb_strimwidth($pi['category'] ?? '', 0, 40, '…')) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($pi['user_name'] ?? 'Unknown') ?>
                                        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($pi['user_email'] ?? '') ?></div>
                                    </td>
                                    <td><strong style="color:var(--gold);">R<?= number_format($pi['amount'], 2) ?></strong></td>
                                    <td><?= $pi['expected_roi'] !== null ? number_format($pi['expected_roi'], 2) . '%' : '—' ?></td>
                                    <td><?= $pi['duration_months'] ? $pi['duration_months'] . ' mo' : '—' ?></td>
                                    <td><span class="badge <?= htmlspecialchars($pi['visibility']) ?>"><?= htmlspecialchars($pi['visibility']) ?></span></td>
                                    <td><span class="badge <?= htmlspecialchars($pi['status']) ?>"><?= htmlspecialchars($pi['status']) ?></span></td>
                                    <td style="font-size:0.78rem;color:rgba(255,255,255,0.5);"><?= date('d M Y', strtotime($pi['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info" onclick='openPersonalModal(<?= htmlspecialchars(json_encode($pi), ENT_QUOTES) ?>)'>
                                                <i class="bx bx-show"></i>
                                            </button>
                                            <?php if ($pi['status'] !== 'approved'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve?');">
                                                    <input type="hidden" name="pi_id" value="<?= (int) $pi['id'] ?>">
                                                    <button type="submit" name="pi_action" value="approve" class="btn btn-approve">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($pi['status'] !== 'rejected'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject?');">
                                                    <input type="hidden" name="pi_id" value="<?= (int) $pi['id'] ?>">
                                                    <button type="submit" name="pi_action" value="reject" class="btn btn-reject">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-piggy-bank"></i>
                    <strong>No investments yet</strong>
                    <p>User investments will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             TAB: LOANS
        ====================================================== -->
        <div id="tab-loans" class="tab-panel">
            <h2><i class="bx bx-money"></i> Loan Applications</h2>

            <div class="filters-row">
                <div class="search-box">
                    <i class="bx bx-search"></i>
                    <input type="text" placeholder="Search by user or purpose..." oninput="filterTable('tbl-loans', this.value)">
                </div>
                <select class="filter-select" onchange="filterByStatus('tbl-loans', this.value)">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <?php if (!empty($loans)): ?>
                <div class="table-wrapper">
                    <table id="tbl-loans">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Total Repayable</th>
                                <th>Purpose</th>
                                <th>Visibility</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $l):
                                $lstatus = strtolower($l['status']);
                            ?>
                                <tr data-status="<?= htmlspecialchars($lstatus) ?>">
                                    <td>#<?= (int) $l['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($l['user_name'] ?? 'Unknown') ?>
                                        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($l['user_email'] ?? '') ?></div>
                                    </td>
                                    <td><strong style="color:var(--gold);">R<?= number_format($l['principal'], 2) ?></strong></td>
                                    <td style="color:var(--warning);font-weight:700;">R<?= number_format($l['interest_amount'], 2) ?></td>
                                    <td><strong style="color:var(--gold-light);">R<?= number_format($l['total_repayable'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars(mb_strimwidth($l['purpose'] ?? '—', 0, 35, '…')) ?></td>
                                    <td><span class="badge <?= htmlspecialchars($l['visibility']) ?>"><?= htmlspecialchars($l['visibility']) ?></span></td>
                                    <td><span class="badge <?= htmlspecialchars($lstatus) ?>"><?= htmlspecialchars($lstatus) ?></span></td>
                                    <td style="font-size:0.78rem;color:rgba(255,255,255,0.5);"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($lstatus !== 'approved' && $lstatus !== 'paid'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this loan?');">
                                                    <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                                                    <button type="submit" name="loan_action" value="approve" class="btn btn-approve">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($lstatus !== 'paid'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                                                    <button type="submit" name="loan_action" value="paid" class="btn btn-info" title="Mark paid">
                                                        <i class="bx bx-check-double"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($lstatus !== 'rejected'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this loan?');">
                                                    <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
                                                    <button type="submit" name="loan_action" value="reject" class="btn btn-reject">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-money"></i>
                    <strong>No loans yet</strong>
                    <p>Loan applications will appear here once submitted.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             TAB: FEEDBACK
        ====================================================== -->
        <div id="tab-feedback" class="tab-panel">
            <h2><i class="bx bx-envelope"></i> Complaints &amp; Feedback</h2>

            <div class="filters-row">
                <div class="search-box">
                    <i class="bx bx-search"></i>
                    <input type="text" placeholder="Search by subject or user..." oninput="filterTable('tbl-feedback', this.value)">
                </div>
                <select class="filter-select" onchange="filterByStatus('tbl-feedback', this.value)">
                    <option value="">All Statuses</option>
                    <option value="new">New</option>
                    <option value="in_review">In Review</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <?php if (!empty($feedback)): ?>
                <div class="table-wrapper">
                    <table id="tbl-feedback">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback as $f): ?>
                                <tr data-status="<?= htmlspecialchars($f['status']) ?>">
                                    <td>#<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($f['subject']) ?></strong>
                                        <?php if (!empty($f['attachment'])): ?>
                                            <a href="<?= htmlspecialchars($f['attachment']) ?>" target="_blank" style="color:var(--gold);margin-left:6px;">
                                                <i class="bx bx-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);margin-top:2px;">
                                            <?= htmlspecialchars(mb_strimwidth($f['message'], 0, 55, '…')) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($f['is_anonymous'])): ?>
                                            <em style="color:var(--purple);">Anonymous</em>
                                        <?php else: ?>
                                            <?= htmlspecialchars($f['user_name'] ?? 'Unknown') ?>
                                            <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($f['user_email'] ?? '') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-transform:capitalize;font-size:0.8rem;"><?= htmlspecialchars($f['category']) ?></td>
                                    <td>
                                        <span class="priority-pill <?= htmlspecialchars($f['priority'] ?? 'medium') ?>">
                                            <?= htmlspecialchars($f['priority'] ?? 'medium') ?>
                                        </span>
                                    </td>
                                    <td><span class="badge <?= htmlspecialchars($f['status']) ?>"><?= str_replace('_', ' ', htmlspecialchars($f['status'])) ?></span></td>
                                    <td style="font-size:0.78rem;color:rgba(255,255,255,0.5);"><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info" onclick='openFeedbackModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)'>
                                                <i class="bx bx-show"></i> View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-envelope"></i>
                    <strong>No feedback yet</strong>
                    <p>Feedback and complaints will appear here when received.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             TAB: USERS
        ====================================================== -->
        <div id="tab-users" class="tab-panel">
            <h2><i class="bx bx-group"></i> All Registered Users</h2>

            <div class="filters-row">
                <div class="search-box">
                    <i class="bx bx-search"></i>
                    <input type="text" placeholder="Search by name or email..." oninput="filterTable('tbl-users', this.value)">
                </div>
            </div>

            <div class="table-wrapper">
                <table id="tbl-users">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= (int) $u['id'] ?></td>
                                <td><strong><?= htmlspecialchars($u['name'] ?? '—') ?></strong></td>
                                <td style="color:rgba(255,255,255,0.6);font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge <?= strtolower($u['role']) === 'admin' ? 'admin' : 'user' ?>">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td><strong style="color:var(--gold);">R<?= number_format((float) ($u['balance'] ?? 0), 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ======================================================
             TAB: AUDIT LOG
        ====================================================== -->
        <div id="tab-audit" class="tab-panel">
            <h2><i class="bx bx-history"></i> Recent Admin Actions</h2>

            <?php if (!empty($auditLog)): ?>
                <?php foreach ($auditLog as $a): ?>
                    <div class="audit-item">
                        <strong><?= htmlspecialchars($a['admin_name'] ?? 'Admin') ?></strong>
                        performed <strong style="color:var(--gold);"><?= htmlspecialchars($a['action']) ?></strong>
                        on <?= htmlspecialchars($a['target_type']) ?> #<?= (int) $a['target_id'] ?>
                        <?php if (!empty($a['details'])): ?>
                            — <em style="color:rgba(255,255,255,0.6);"><?= htmlspecialchars(mb_strimwidth($a['details'], 0, 80, '…')) ?></em>
                        <?php endif; ?>
                        <div class="audit-time">
                            <i class="bx bx-time-five"></i> <?= date('d M Y, H:i', strtotime($a['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-history"></i>
                    <strong>No activity recorded</strong>
                    <p>Admin actions will be logged here for full transparency.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ======================================================
         MODALS
    ====================================================== -->

    <!-- Proposal modal -->
    <div class="modal-overlay" id="proposalModal">
        <div class="modal">
            <button class="modal-close" onclick="closeModal('proposalModal')">&times;</button>
            <h3><i class="bx bx-bulb"></i> Proposal Details</h3>
            <div class="modal-meta" id="pm-meta"></div>
            <div class="modal-body" id="pm-body"></div>
            <form method="POST" id="pm-form">
                <input type="hidden" name="proposal_id" id="pm-id">
                <label style="font-size:0.85rem;color:rgba(255,255,255,0.75);display:block;margin-bottom:6px;position:relative;z-index:2;">
                    Admin note (optional)
                </label>
                <textarea name="admin_note" placeholder="Add a note for the user..."></textarea>
                <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:2;">
                    <button type="submit" name="proposal_action" value="approve" class="btn btn-approve">
                        <i class="bx bx-check"></i> Approve
                    </button>
                    <button type="submit" name="proposal_action" value="reject" class="btn btn-reject">
                        <i class="bx bx-x"></i> Reject
                    </button>
                    <button type="submit" name="proposal_action" value="reset" class="btn btn-info">
                        <i class="bx bx-reset"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Personal investment modal -->
    <div class="modal-overlay" id="personalModal">
        <div class="modal">
            <button class="modal-close" onclick="closeModal('personalModal')">&times;</button>
            <h3><i class="bx bx-piggy-bank"></i> Investment Details</h3>
            <div class="modal-meta" id="pim-meta"></div>
            <div class="modal-body" id="pim-body"></div>
            <form method="POST" id="pim-form">
                <input type="hidden" name="pi_id" id="pim-id">
                <label style="font-size:0.85rem;color:rgba(255,255,255,0.75);display:block;margin-bottom:6px;position:relative;z-index:2;">
                    Admin note (optional)
                </label>
                <textarea name="admin_note" placeholder="Add a note for the user..."></textarea>
                <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:2;">
                    <button type="submit" name="pi_action" value="approve" class="btn btn-approve">
                        <i class="bx bx-check"></i> Approve
                    </button>
                    <button type="submit" name="pi_action" value="reject" class="btn btn-reject">
                        <i class="bx bx-x"></i> Reject
                    </button>
                    <button type="submit" name="pi_action" value="reset" class="btn btn-info">
                        <i class="bx bx-reset"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Feedback modal -->
    <div class="modal-overlay" id="feedbackModal">
        <div class="modal">
            <button class="modal-close" onclick="closeModal('feedbackModal')">&times;</button>
            <h3><i class="bx bx-envelope"></i> Feedback / Complaint</h3>
            <div class="modal-meta" id="fm-meta"></div>
            <div class="modal-body" id="fm-body"></div>
            <form method="POST" id="fm-form">
                <input type="hidden" name="feedback_id" id="fm-id">
                <label style="font-size:0.85rem;color:rgba(255,255,255,0.75);display:block;margin-bottom:6px;position:relative;z-index:2;">
                    Reply to user
                </label>
                <textarea name="admin_reply" id="fm-reply" placeholder="Write your reply..."></textarea>
                <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:2;">
                    <button type="submit" name="feedback_action" value="review" class="btn btn-info">
                        <i class="bx bx-time"></i> Review
                    </button>
                    <button type="submit" name="feedback_action" value="resolve" class="btn btn-approve">
                        <i class="bx bx-check"></i> Resolve
                    </button>
                    <button type="submit" name="feedback_action" value="close" class="btn btn-reject">
                        <i class="bx bx-x"></i> Close
                    </button>
                    <button type="submit" name="feedback_action" value="reopen" class="btn btn-info">
                        <i class="bx bx-refresh"></i> Reopen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // =====================================================
        // TAB SWITCHING
        // =====================================================
        function switchTab(evt, tabId) {
            if (evt && evt.preventDefault) evt.preventDefault();
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

            const panel = document.getElementById(tabId);
            if (panel) panel.classList.add('active');

            if (evt && evt.currentTarget && evt.currentTarget.classList && evt.currentTarget.classList.contains('tab-btn')) {
                evt.currentTarget.classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn').forEach(b => {
                    if (b.getAttribute('onclick')?.includes(tabId)) b.classList.add('active');
                });
            }
        }

        // =====================================================
        // TABLE FILTERS
        // =====================================================
        function filterTable(tableId, query) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            const q = query.toLowerCase().trim();
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function filterByStatus(tableId, status) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rs = (row.getAttribute('data-status') || '').toLowerCase();
                row.style.display = (!status || rs === status.toLowerCase()) ? '' : 'none';
            });
        }

        // =====================================================
        // MODALS
        // =====================================================
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(o => {
            o.addEventListener('click', e => {
                if (e.target === o) o.classList.remove('active');
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(o => o.classList.remove('active'));
            }
        });

        function openProposalModal(p) {
            document.getElementById('pm-id').value = p.id;
            document.getElementById('pm-meta').innerHTML = `
                <strong>${escapeHtml(p.title || '')}</strong> •
                ${escapeHtml(p.user_name || 'Unknown')} (${escapeHtml(p.user_email || '')}) •
                ${escapeHtml(p.created_at || '')}
            `;
            document.getElementById('pm-body').innerHTML = `
                <p><strong style="color:var(--gold);">Type:</strong> ${escapeHtml(p.type || '—')}</p>
                <p><strong style="color:var(--gold);">Amount:</strong> R${parseFloat(p.amount_needed || 0).toFixed(2)}</p>
                <p><strong style="color:var(--gold);">Visibility:</strong> ${escapeHtml(p.visibility || 'private')}</p>
                <p><strong style="color:var(--gold);">Status:</strong> ${escapeHtml(p.status || 'pending')}</p>
                <p><strong style="color:var(--gold);">Description:</strong></p>
                <p style="white-space:pre-wrap;">${escapeHtml(p.description || '')}</p>
                ${p.business_plan ? `<p><a href="${escapeHtml(p.business_plan)}" target="_blank" style="color:var(--gold-light);"><i class="bx bx-paperclip"></i> Download Business Plan</a></p>` : ''}
                ${p.contact_link ? `<p><strong style="color:var(--gold);">Website:</strong> ${escapeHtml(p.contact_link)}</p>` : ''}
                ${p.contact_whatsapp ? `<p><strong style="color:var(--gold);">WhatsApp:</strong> ${escapeHtml(p.contact_whatsapp)}</p>` : ''}
                ${p.contact_email ? `<p><strong style="color:var(--gold);">Email:</strong> ${escapeHtml(p.contact_email)}</p>` : ''}
                ${p.contact_telegram ? `<p><strong style="color:var(--gold);">Telegram:</strong> ${escapeHtml(p.contact_telegram)}</p>` : ''}
            `;
            document.getElementById('proposalModal').classList.add('active');
        }

        function openPersonalModal(pi) {
            document.getElementById('pim-id').value = pi.id;
            document.getElementById('pim-meta').innerHTML = `
                <strong>${escapeHtml(pi.title || '')}</strong> •
                ${escapeHtml(pi.user_name || 'Unknown')} (${escapeHtml(pi.user_email || '')})
            `;
            document.getElementById('pim-body').innerHTML = `
                <p><strong style="color:var(--gold);">Category:</strong> ${escapeHtml(pi.category || '—')}</p>
                <p><strong style="color:var(--gold);">Amount:</strong> R${parseFloat(pi.amount || 0).toFixed(2)}</p>
                <p><strong style="color:var(--gold);">Expected ROI:</strong> ${pi.expected_roi !== null ? parseFloat(pi.expected_roi).toFixed(2) + '%' : '—'}</p>
                <p><strong style="color:var(--gold);">Duration:</strong> ${pi.duration_months ? pi.duration_months + ' months' : '—'}</p>
                <p><strong style="color:var(--gold);">Visibility:</strong> ${escapeHtml(pi.visibility || 'private')}</p>
                <p><strong style="color:var(--gold);">Status:</strong> ${escapeHtml(pi.status)}</p>
                ${pi.description ? `<p><strong style="color:var(--gold);">Description:</strong></p><p style="white-space:pre-wrap;">${escapeHtml(pi.description)}</p>` : ''}
            `;
            document.getElementById('personalModal').classList.add('active');
        }

        function openFeedbackModal(f) {
            document.getElementById('fm-id').value = f.id;
            document.getElementById('fm-meta').innerHTML = `
                <strong>${escapeHtml(f.subject || '')}</strong> •
                ${f.is_anonymous ? '<em style="color:var(--purple);">Anonymous</em>' : escapeHtml(f.user_name || 'Unknown')} •
                ${escapeHtml(f.created_at || '')}
            `;
            document.getElementById('fm-body').innerHTML = `
                <p><strong style="color:var(--gold);">Category:</strong> ${escapeHtml(f.category)}</p>
                <p><strong style="color:var(--gold);">Priority:</strong> ${escapeHtml(f.priority || 'medium')}</p>
                <p style="white-space:pre-wrap;">${escapeHtml(f.message)}</p>
                ${f.attachment ? `<p><a href="${escapeHtml(f.attachment)}" target="_blank" style="color:var(--gold-light);"><i class="bx bx-paperclip"></i> View Attachment</a></p>` : ''}
                ${f.admin_reply ? `<div style="background:rgba(212,175,55,0.08);padding:12px;border-left:3px solid var(--gold);border-radius:10px;margin-top:12px;"><strong style="color:var(--gold);font-size:0.75rem;letter-spacing:1px;">PREVIOUS REPLY</strong><br>${escapeHtml(f.admin_reply)}</div>` : ''}
            `;
            document.getElementById('fm-reply').value = f.admin_reply || '';
            document.getElementById('feedbackModal').classList.add('active');
        }

        // =====================================================
        // HTML ESCAPE
        // =====================================================
        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        console.log('🔐 Admin Control Center loaded — <?= htmlspecialchars($userName, ENT_QUOTES) ?>');
    </script>

</body>

</html>