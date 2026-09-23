<?php
// ======================================================
// 🔐 BOOTSTRAP
// ======================================================
session_start();
require_once 'config.php';

require_login();

$uid      = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$userName = $_SESSION['name'] ?? $_SESSION['name'] ?? 'User';

$db = get_db();

// ======================================================
// 👤 HELPER: fetch poster info for a list of user IDs
// ======================================================
function fetchPosters(PDO $db, array $userIds): array {
    $posters = [];
    $userIds = array_values(array_unique(array_filter($userIds)));

    if (empty($userIds)) return $posters;

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    try {
        $stmt = $db->prepare("
            SELECT id, name, name, profile_pic
            FROM users
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($userIds);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $posters[(int) $row['id']] = [
                'name'    => $row['name'] ?? $row['name'] ?? 'User',
                'picture' => $row['profile_pic'] ?? null,
            ];
        }
    } catch (PDOException $e) {
        try {
            $stmt = $db->prepare("
                SELECT id, name, name
                FROM users
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($userIds);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $posters[(int) $row['id']] = [
                    'name'    => $row['name'] ?? $row['name'] ?? 'User',
                    'picture' => null,
                ];
            }
        } catch (PDOException $e2) {}
    }

    return $posters;
}

// ======================================================
// 📈 PUBLIC INVESTMENTS
// ======================================================
$publicInvestments = [];
try {
    $stmt = $db->prepare("
        SELECT id, user_id, title, description, category, amount,
               expected_roi, duration_months, status, created_at
        FROM personal_investments
        WHERE visibility = 'public'
          AND status IN ('pending','approved','active')
        ORDER BY created_at DESC
        LIMIT 60
    ");
    $stmt->execute();
    $publicInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ======================================================
// 💳 PUBLIC LOANS
// ======================================================
$publicLoans = [];
try {
    $stmt = $db->prepare("
        SELECT id, user_id, principal, interest_rate, interest_amount,
               total_repayable, purpose, term_months, status, created_at
        FROM personal_loans
        WHERE visibility = 'public'
          AND status IN ('pending','approved','active')
        ORDER BY created_at DESC
        LIMIT 60
    ");
    $stmt->execute();
    $publicLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ======================================================
// 💡 PUBLIC PROPOSALS
// ======================================================
$publicProposals = [];
try {
    $stmt = $db->prepare("
        SELECT id, user_id, title, description, type, amount_needed,
               contact_link, contact_whatsapp, contact_email,
               contact_telegram, business_plan, status, created_at
        FROM personal_proposals
        WHERE visibility = 'public'
          AND status IN ('pending','approved','active')
        ORDER BY created_at DESC
        LIMIT 60
    ");
    $stmt->execute();
    $publicProposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ======================================================
// 👥 BATCH LOOKUP — all posters across the three lists
// ======================================================
$allPosterIds = array_merge(
    array_column($publicInvestments, 'user_id'),
    array_column($publicLoans, 'user_id'),
    array_column($publicProposals, 'user_id')
);
$posters = fetchPosters($db, $allPosterIds);

// ======================================================
// 🔢 COUNTS
// ======================================================
$totalPublic = count($publicInvestments) + count($publicLoans) + count($publicProposals);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PUBLIC FEED | SFS</title>
    <meta name="description" content="See public investments, loans, and business proposals from Senoamadi members." />
    <meta name="author" content="Judge Tshwarelo" />
    <link rel="icon" type="image/png" href="/files/SFS-LOGO.png" />

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

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
                radial-gradient(circle at top right, rgba(212,175,55,.12), transparent 25%),
                radial-gradient(circle at bottom left, rgba(0,140,255,.12), transparent 35%),
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
            background: linear-gradient(180deg, transparent, rgba(212,175,55,.6), transparent);
        }

        nav::-webkit-scrollbar { width: 4px; }
        nav::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }

        .logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
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
            background: linear-gradient(135deg, rgba(212,175,55,.25), rgba(212,175,55,.08));
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

        .public-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(212,175,55,.2), rgba(212,175,55,.05));
            color: var(--gold-light);
            padding: 6px 16px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 14px;
            border: 1px solid rgba(212, 175, 55, .35);
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
           TABS
        ====================================================== */
        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 22px 0 24px;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .12);
            font-size: .68rem;
            font-weight: 800;
        }

        .tab-btn.active .tab-count {
            background: rgba(8, 24, 46, .25);
        }

        .tab-panel { display: none; animation: fadeIn .4s ease; }
        .tab-panel.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ======================================================
           SECTION HEAD
        ====================================================== */
        .section-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .section-head .accent {
            width: 5px;
            height: 28px;
            border-radius: 4px;
            background: linear-gradient(180deg, var(--gold), rgba(212,175,55,.2));
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
           CARD GRID
        ====================================================== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .pub-card {
            position: relative;
            overflow: hidden;
            padding: 26px 24px;
            border-radius: 22px;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .2);
            display: flex;
            flex-direction: column;
            transition: .35s;
        }

        .pub-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 22px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(212,175,55,.5), transparent 45%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: .35s;
            pointer-events: none;
        }

        .pub-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 25px 55px rgba(212, 175, 55, .15);
        }
        .pub-card:hover::before { opacity: 1; }

        .card-tags {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .badge-gold {
            display: inline-block;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            font-weight: 800;
            padding: 6px 15px;
            border-radius: 999px;
            font-size: .68rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .badge-info {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(58, 123, 213, .15);
            color: var(--light);
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: .7rem;
            border: 1px solid rgba(58, 123, 213, .3);
        }

        .badge-status {
            padding: 5px 12px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-block;
            border: 1px solid transparent;
        }
        .badge-status.pending  { background: rgba(241, 196, 15, .15); color: var(--warning); border-color: rgba(241, 196, 15, .35); }
        .badge-status.approved,
        .badge-status.active   { background: rgba(46, 204, 113, .15); color: var(--success); border-color: rgba(46, 204, 113, .35); }

        /* Poster row */
        .poster-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .poster-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid rgba(212, 175, 55, .5);
            background: rgba(212, 175, 55, .1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .85rem;
            color: var(--gold-light);
            flex-shrink: 0;
            overflow: hidden;
        }

        .poster-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .poster-meta {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
            min-width: 0;
        }

        .poster-meta .poster-name {
            font-size: .85rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .poster-meta .poster-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, .45);
            font-weight: 600;
        }

        .pub-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            line-height: 1.35;
        }

        .pub-card p {
            font-size: .9rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, .68);
            flex-grow: 1;
            margin-bottom: 16px;
        }

        .amount-line {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 4px 0 16px;
            font-size: .82rem;
            color: rgba(255, 255, 255, .55);
        }

        .amount-line strong {
            color: var(--gold-light);
            font-weight: 800;
            font-size: .95rem;
        }

        .contact-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, .07);
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 15px;
            border-radius: 999px;
            font-weight: 600;
            font-size: .78rem;
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .85);
            border: 1px solid rgba(255, 255, 255, .12);
            transition: .3s;
            cursor: pointer;
            flex: 1 0 auto;
        }

        .contact-btn i { font-size: 1rem; }

        .contact-btn:hover {
            background: var(--gold);
            color: var(--navy);
            border-color: var(--gold);
            transform: translateY(-2px);
        }

        .contact-btn.whatsapp:hover { background: #25D366; border-color: #25D366; color: white; }
        .contact-btn.email:hover    { background: #D44638; border-color: #D44638; color: white; }
        .contact-btn.link:hover     { background: var(--blue); border-color: var(--blue); color: white; }
        .contact-btn.telegram:hover { background: #0088cc; border-color: #0088cc; color: white; }

        /* ======================================================
           EMPTY STATE
        ====================================================== */
        .empty-state {
            padding: 60px 30px;
            text-align: center;
            border-radius: 22px;
            background: rgba(8, 20, 40, .5);
            border: 1px dashed rgba(212, 175, 55, .3);
            color: rgba(255, 255, 255, .6);
        }

        .empty-state i {
            font-size: 2.6rem;
            color: var(--gold);
            display: block;
            margin-bottom: 14px;
            opacity: .75;
        }

        .empty-state strong {
            display: block;
            font-size: 1.1rem;
            color: white;
            margin-bottom: 6px;
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
            box-shadow: 0 0 25px rgba(212,175,55,.5), 0 0 60px rgba(212,175,55,.25);
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

            .cards-grid { grid-template-columns: 1fr; }
            .pub-card { padding: 22px 18px; }

            .tab-btn { padding: 9px 13px; font-size: .78rem; }

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
            <a class="menu-item active" href="user_page.php">
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
         MAIN
    ====================================================== -->
    <main>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <h1>Public Feed</h1>
                <p>
                    <strong style="color:var(--gold-light);"><?= htmlspecialchars($userName) ?></strong>,
                    explore public investments, loans, and business proposals.
                </p>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-investments')">
                <i class="bx bx-trending-up"></i> Investments
                <span class="tab-count"><?= count($publicInvestments) ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-loans')">
                <i class="bx bx-credit-card"></i> Loans
                <span class="tab-count"><?= count($publicLoans) ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-proposals')">
                <i class="bx bx-bulb"></i> Business Proposals
                <span class="tab-count"><?= count($publicProposals) ?></span>
            </button>
        </div>

        <!-- ======================================================
             TAB 1: INVESTMENTS
        ====================================================== -->
        <div id="tab-investments" class="tab-panel active">
            <div class="section-head">
                <div class="accent"></div>
                <h2><i class="bx bx-trending-up"></i> Public Investments</h2>
            </div>

            <?php if (!empty($publicInvestments)): ?>
                <div class="cards-grid">
                    <?php foreach ($publicInvestments as $inv):
                        $posterId = (int) $inv['user_id'];
                        $poster   = $posters[$posterId] ?? null;
                        $pName    = $poster['name'] ?? 'Unknown';
                        $pPic     = $poster['picture'] ?? null;
                        $initial  = strtoupper(mb_substr($pName, 0, 1));
                    ?>
                        <div class="pub-card">
                            <div class="card-tags">
                                <span class="badge-gold"><?= htmlspecialchars($inv['category'] ?: 'Investment') ?></span>
                                <span class="badge-info"><i class="bx bx-time"></i> <?= $inv['duration_months'] ? (int)$inv['duration_months'] . ' mo' : 'Open' ?></span>
                                <span class="badge-status <?= htmlspecialchars($inv['status']) ?>"><?= htmlspecialchars($inv['status']) ?></span>
                            </div>

                            <div class="poster-row">
                                <div class="poster-avatar">
                                    <?php if (!empty($pPic)): ?>
                                        <img src="<?= htmlspecialchars($pPic) ?>" alt="<?= htmlspecialchars($pName) ?>">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="poster-meta">
                                    <span class="poster-label">Posted by</span>
                                    <span class="poster-name"><?= htmlspecialchars($pName) ?></span>
                                </div>
                            </div>

                            <h3><?= htmlspecialchars($inv['title']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($inv['description'])) ?></p>

                            <div class="amount-line">
                                <span>Amount: <strong>R<?= number_format($inv['amount'], 2) ?></strong></span>
                                <?php if ($inv['expected_roi'] !== null): ?>
                                    <span>ROI: <strong><?= number_format($inv['expected_roi'], 2) ?>%</strong></span>
                                <?php endif; ?>
                            </div>

                            <div class="contact-buttons">
                                <a href="mailto:?subject=Investment%20interest:%20<?= urlencode($inv['title']) ?>" class="contact-btn email">
                                    <i class="fas fa-envelope"></i> Contact
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-trending-up"></i>
                    <strong>No public investments yet</strong>
                    <p>Check back later — members post new opportunities regularly.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             TAB 2: LOANS
        ====================================================== -->
        <div id="tab-loans" class="tab-panel">
            <div class="section-head">
                <div class="accent"></div>
                <h2><i class="bx bx-credit-card"></i> Public Loans</h2>
            </div>

            <?php if (!empty($publicLoans)): ?>
                <div class="cards-grid">
                    <?php foreach ($publicLoans as $loan):
                        $posterId = (int) $loan['user_id'];
                        $poster   = $posters[$posterId] ?? null;
                        $pName    = $poster['name'] ?? 'Unknown';
                        $pPic     = $poster['picture'] ?? null;
                        $initial  = strtoupper(mb_substr($pName, 0, 1));
                    ?>
                        <div class="pub-card">
                            <div class="card-tags">
                                <span class="badge-gold">Loan Request</span>
                                <span class="badge-info"><i class="bx bx-time"></i> <?= $loan['term_months'] ? (int)$loan['term_months'] . ' mo' : 'Flexible' ?></span>
                                <span class="badge-status <?= htmlspecialchars($loan['status']) ?>"><?= htmlspecialchars($loan['status']) ?></span>
                            </div>

                            <div class="poster-row">
                                <div class="poster-avatar">
                                    <?php if (!empty($pPic)): ?>
                                        <img src="<?= htmlspecialchars($pPic) ?>" alt="<?= htmlspecialchars($pName) ?>">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="poster-meta">
                                    <span class="poster-name"><?= htmlspecialchars($pName) ?></span>
                                    <span class="poster-label">Requested by</span>
                                </div>
                            </div>

                            <h3><?= htmlspecialchars($loan['purpose'] ?: 'Loan Request') ?></h3>

                            <div class="amount-line">
                                <span>Principal: <strong>R<?= number_format($loan['principal'], 2) ?></strong></span>
                                <span>Interest (<?= number_format($loan['interest_rate'], 0) ?>%): <strong>R<?= number_format($loan['interest_amount'], 2) ?></strong></span>
                            </div>
                            <div class="amount-line">
                                <span>Total repayable: <strong>R<?= number_format($loan['total_repayable'], 2) ?></strong></span>
                            </div>

                            <div class="contact-buttons">
                                <a href="mailto:?subject=Loan%20support:%20<?= urlencode($loan['purpose']) ?>" class="contact-btn email">
                                    <i class="fas fa-envelope"></i> Reach out
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-credit-card"></i>
                    <strong>No public loan requests yet</strong>
                    <p>Check back later — members post new requests regularly.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             TAB 3: PROPOSALS
        ====================================================== -->
        <div id="tab-proposals" class="tab-panel">
            <div class="section-head">
                <div class="accent"></div>
                <h2><i class="bx bx-bulb"></i> Public Business Proposals</h2>
            </div>

            <?php if (!empty($publicProposals)): ?>
                <div class="cards-grid">
                    <?php foreach ($publicProposals as $proposal):
                        $posterId = (int) $proposal['user_id'];
                        $poster   = $posters[$posterId] ?? null;
                        $pName    = $poster['name'] ?? 'Unknown';
                        $pPic     = $poster['picture'] ?? null;
                        $initial  = strtoupper(mb_substr($pName, 0, 1));
                    ?>
                        <div class="pub-card">
                            <div class="card-tags">
                                <span class="badge-gold"><?= htmlspecialchars($proposal['type'] ?? 'FUNDING') ?></span>
                                <span class="badge-info"><i class="bx bx-target-lock"></i> R<?= number_format((float) $proposal['amount_needed'], 0) ?></span>
                                <span class="badge-status <?= htmlspecialchars($proposal['status']) ?>"><?= htmlspecialchars($proposal['status']) ?></span>
                            </div>

                            <div class="poster-row">
                                <div class="poster-avatar">
                                    <?php if (!empty($pPic)): ?>
                                        <img src="<?= htmlspecialchars($pPic) ?>" alt="<?= htmlspecialchars($pName) ?>">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="poster-meta">
                                    <span class="poster-name"><?= htmlspecialchars($pName) ?></span>
                                    <span class="poster-label">Posted by</span>
                                </div>
                            </div>

                            <h3><?= htmlspecialchars($proposal['title']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($proposal['description'])) ?></p>

                            <div class="contact-buttons">
                                <?php if (!empty($proposal['contact_link'])): ?>
                                    <a href="<?= htmlspecialchars($proposal['contact_link']) ?>" target="_blank" rel="noopener" class="contact-btn link">
                                        <i class="fas fa-link"></i> Website
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($proposal['contact_whatsapp'])): ?>
                                    <a href="https://wa.me/<?= htmlspecialchars($proposal['contact_whatsapp']) ?>" target="_blank" rel="noopener" class="contact-btn whatsapp">
                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($proposal['contact_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($proposal['contact_email']) ?>" class="contact-btn email">
                                        <i class="fas fa-envelope"></i> Email
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($proposal['contact_telegram'])): ?>
                                    <a href="https://t.me/<?= htmlspecialchars($proposal['contact_telegram']) ?>" target="_blank" rel="noopener" class="contact-btn telegram">
                                        <i class="fab fa-telegram-plane"></i> Telegram
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($proposal['business_plan'])): ?>
                                    <a href="<?= htmlspecialchars($proposal['business_plan']) ?>" target="_blank" class="contact-btn link">
                                        <i class="bx bx-paperclip"></i> Plan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bx bx-bulb"></i>
                    <strong>No public proposals right now</strong>
                    <p>Check back later — new opportunities are added regularly.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Floating chat icon (mobile) -->
    <div id="chat-icon" onclick="alert('AI Buddy coming soon!')">
        <i class='bx bx-message-rounded-dots'></i>
    </div>

    <script>
        // =====================================================
        // TAB SWITCHING
        // =====================================================
        function switchTab(evt, tabId) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        console.log('🌍 Public Feed loaded for <?= htmlspecialchars($userName, ENT_QUOTES) ?>');
    </script>

</body>

</html>