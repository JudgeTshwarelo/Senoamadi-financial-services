<?php
session_start();
require_once 'config.php';

// =========================================================
// USER ACCESS GATE
// =========================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$uid       = (int) $_SESSION['user_id'];
$userName  = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

$db = get_db();

// =========================================================
// CONFIG: LOAN INTEREST
// =========================================================
define('LOAN_INTEREST_RATE', 25.00); // 25% flat on principal

// =========================================================
// HANDLE POST ACTIONS
// =========================================================
$flash     = '';
$flashType = '';

// -------------------- ADD INVESTMENT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_investment'])) {

    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $category     = trim($_POST['category'] ?? 'General');
    $amount       = (float) ($_POST['amount'] ?? 0);
    $expected_roi = $_POST['expected_roi'] !== '' ? (float) $_POST['expected_roi'] : null;
    $duration     = $_POST['duration_months'] !== '' ? (int) $_POST['duration_months'] : null;
    $visibility   = trim($_POST['visibility'] ?? 'private');

    $errors = [];
    if ($title === '')        $errors[] = 'Title is required.';
    if ($description === '')  $errors[] = 'Description is required.';
    if ($amount <= 0)         $errors[] = 'Amount must be greater than 0.';
    if (!in_array($visibility, ['private','public'])) $errors[] = 'Invalid visibility.';

    if (empty($errors)) {
        try {
            // Only columns that exist in personal_investments
            $stmt = $db->prepare("
                INSERT INTO personal_investments
                    (user_id, title, description, category, amount,
                     expected_roi, duration_months, visibility, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $uid, $title, $description, $category, $amount,
                $expected_roi, $duration, $visibility
            ]);
            $flash = 'Investment submitted successfully (' . $visibility . ').';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    } else {
        $flash = implode('<br>', $errors);
        $flashType = 'error';
    }
}

// -------------------- TAKE LOAN --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_loan'])) {

    $principal  = (float) ($_POST['principal'] ?? 0);
    $purpose    = trim($_POST['purpose'] ?? '');
    $term       = $_POST['term_months'] !== '' ? (int) $_POST['term_months'] : null;
    $visibility = trim($_POST['visibility'] ?? 'private');

    $errors = [];
    if ($principal <= 0)      $errors[] = 'Loan amount must be greater than 0.';
    if ($principal > 1000000) $errors[] = 'Maximum loan is R1,000,000.';
    if ($purpose === '')      $errors[] = 'Purpose is required.';
    if ($term !== null && ($term < 1 || $term > 60)) $errors[] = 'Term must be between 1 and 60 months.';
    if (!in_array($visibility, ['private','public'])) $errors[] = 'Invalid visibility.';

    if (empty($errors)) {
        $rate           = LOAN_INTEREST_RATE;
        $interestAmount = round($principal * ($rate / 100), 2);
        $totalRepayable = round($principal + $interestAmount, 2);

        try {
            // personal_loans has no `notes` and no `amount_repaid` columns
            $stmt = $db->prepare("
                INSERT INTO personal_loans
                    (user_id, principal, interest_rate, interest_amount, total_repayable,
                     purpose, term_months, visibility, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $uid, $principal, $rate, $interestAmount, $totalRepayable,
                $purpose, $term, $visibility
            ]);
            $flash = 'Loan application submitted (' . $visibility . '). Principal: R' . number_format($principal, 2)
                   . ' • Interest: R' . number_format($interestAmount, 2)
                   . ' • Total repayable: R' . number_format($totalRepayable, 2);
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    } else {
        $flash = implode('<br>', $errors);
        $flashType = 'error';
    }
}

// -------------------- POST PROPOSAL --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_proposal'])) {

    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $type             = trim($_POST['type'] ?? 'funding');
    $amount_needed    = (float) ($_POST['amount_needed'] ?? 0);
    $contact_link     = trim($_POST['contact_link'] ?? '');
    $contact_whatsapp = trim($_POST['contact_whatsapp'] ?? '');
    $contact_email    = trim($_POST['contact_email'] ?? '');
    $contact_telegram = trim($_POST['contact_telegram'] ?? '');
    $visibility       = trim($_POST['visibility'] ?? 'private');

    $errors = [];
    if ($title === '')        $errors[] = 'Title is required.';
    if ($description === '')  $errors[] = 'Description is required.';

    // Valid enum values from SQL: funding, partnership, investment
    if (!in_array($type, ['funding','partnership','investment'])) {
        $errors[] = 'Invalid type. Choose funding, partnership, or investment.';
    }
    if ($amount_needed <= 0)  $errors[] = 'Amount needed must be greater than 0.';
    if (!in_array($visibility, ['private','public'])) $errors[] = 'Invalid visibility.';

    if (empty($contact_link) && empty($contact_whatsapp) && empty($contact_email) && empty($contact_telegram)) {
        $errors[] = 'Please provide at least one contact method.';
    }

    // WhatsApp column is varchar(20) — trim if too long
    if (!empty($contact_whatsapp)) {
        $contact_whatsapp = substr($contact_whatsapp, 0, 20);
    }

    // ----- File upload (business plan) -----
    $uploaded_file_path = null;

    if (isset($_FILES['business_plan']) && $_FILES['business_plan']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['business_plan'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error. Please try again.';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'File is too large. Maximum size is 10MB.';
        } else {
            $allowed = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $errors[] = 'Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.';
            } else {
                $uploadDir = __DIR__ . '/uploads/proposals/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safeName = 'proposal_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $target   = $uploadDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $uploaded_file_path = 'uploads/proposals/' . $safeName;
                } else {
                    $errors[] = 'Failed to save uploaded file.';
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO personal_proposals
                    (user_id, title, description, type, amount_needed,
                     contact_link, contact_whatsapp, contact_email, contact_telegram,
                     business_plan, visibility, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $uid, $title, $description, $type, $amount_needed,
                $contact_link     ?: null,
                $contact_whatsapp ?: null,
                $contact_email    ?: null,
                $contact_telegram ?: null,
                $uploaded_file_path,
                $visibility
            ]);
            $flash = 'Proposal submitted successfully (' . $visibility . ').';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    } else {
        $flash = implode('<br>', $errors);
        $flashType = 'error';
    }
}

// -------------------- DELETE ITEMS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $table  = $_POST['item_table'] ?? '';

    $allowedTables = ['personal_investments', 'personal_loans', 'personal_proposals'];

    if ($itemId > 0 && in_array($table, $allowedTables, true)) {
        try {
            if ($table === 'personal_proposals') {
                $stmt = $db->prepare("SELECT business_plan FROM personal_proposals WHERE id = ? AND user_id = ? LIMIT 1");
                $stmt->execute([$itemId, $uid]);
                $row = $stmt->fetch();
                if ($row && !empty($row['business_plan'])) {
                    $path = __DIR__ . '/' . $row['business_plan'];
                    if (is_file($path)) @unlink($path);
                }
            }

            $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$itemId, $uid]);

            $flash = 'Item deleted.';
            $flashType = 'success';
        } catch (PDOException $e) {
            $flash = 'DB error: ' . $e->getMessage();
            $flashType = 'error';
        }
    } else {
        $flash = 'Invalid delete request.';
        $flashType = 'error';
    }
}

// -------------------- CANCEL LOAN --------------------
// SQL status enum: pending, approved, rejected, paid — no 'cancelled'.
// We use 'rejected' as the user-initiated cancellation path.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_loan'])) {
    $id = (int) $_POST['id'];
    try {
        $stmt = $db->prepare("
            UPDATE personal_loans
            SET status = 'rejected', updated_at = NOW()
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$id, $uid]);
        $flash = 'Loan application withdrawn.';
        $flashType = 'success';
    } catch (PDOException $e) {
        $flash = 'DB error: ' . $e->getMessage();
        $flashType = 'error';
    }
}

// =========================================================
// FETCH ALL USER ITEMS
// =========================================================
$investments = [];
try {
    $stmt = $db->prepare("SELECT * FROM personal_investments WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$loans = [];
try {
    $stmt = $db->prepare("SELECT * FROM personal_loans WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$proposals = [];
try {
    $stmt = $db->prepare("SELECT * FROM personal_proposals WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$privInvestments = array_filter($investments, fn($i) => $i['visibility'] === 'private');
$pubInvestments  = array_filter($investments, fn($i) => $i['visibility'] === 'public');

$privLoans = array_filter($loans, fn($l) => $l['visibility'] === 'private');
$pubLoans  = array_filter($loans, fn($l) => $l['visibility'] === 'public');

$privProposals = array_filter($proposals, fn($p) => $p['visibility'] === 'private');
$pubProposals  = array_filter($proposals, fn($p) => $p['visibility'] === 'public');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PERSONAL | SFS</title>
    <meta name="description" content="Post private and public investments, loans, and proposals to SFS." />
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
            max-width: 640px;
        }

        /* ======================================================
           POST FORM
        ====================================================== */
        .post-card {
            position: relative;
            overflow: hidden;
            padding: 30px 28px;
            border-radius: 22px;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .2);
            margin-bottom: 40px;
        }

        .post-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(58, 123, 213, .14), transparent 70%);
            transform: translate(30%, -30%);
            pointer-events: none;
        }

        .field { margin-bottom: 20px; position: relative; z-index: 2; }

        .field > label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .55);
            margin-bottom: 10px;
        }

        .field > label i {
            color: var(--gold);
            margin-right: 6px;
            font-size: 14px;
        }

        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }

        .option-card {
            position: relative;
            cursor: pointer;
        }

        .option-card input { position: absolute; opacity: 0; cursor: pointer; }

        .option-card .opt-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 18px 12px;
            border-radius: 14px;
            border: 1.5px solid rgba(255, 255, 255, .1);
            background: rgba(4, 11, 22, .6);
            transition: .3s;
            text-align: center;
        }

        .option-card .opt-box i { font-size: 1.7rem; color: var(--gold); }

        .option-card .opt-box span {
            font-weight: 600;
            font-size: .85rem;
            color: rgba(255, 255, 255, .75);
        }

        .option-card .opt-box:hover {
            border-color: rgba(212, 175, 55, .5);
            background: rgba(212, 175, 55, .05);
        }

        .option-card input:checked + .opt-box {
            border-color: var(--gold);
            background: rgba(212, 175, 55, .12);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, .12);
        }

        .option-card input:checked + .opt-box i,
        .option-card input:checked + .opt-box span { color: var(--gold-light); }

        .visibility-note {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: .78rem;
            color: rgba(255, 255, 255, .55);
            margin-top: 10px;
            line-height: 1.5;
        }

        .visibility-note i { color: var(--gold); font-size: 16px; margin-top: 2px; }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            position: relative;
            z-index: 2;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrap > i {
            position: absolute;
            left: 16px;
            font-size: 18px;
            color: var(--gold);
            pointer-events: none;
            z-index: 2;
        }

        .input-wrap input,
        .input-wrap textarea {
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

        .input-wrap textarea {
            min-height: 130px;
            resize: vertical;
            line-height: 1.6;
            padding-top: 16px;
        }

        .input-wrap input::placeholder,
        .input-wrap textarea::placeholder { color: rgba(255, 255, 255, .35); }

        .input-wrap input:focus,
        .input-wrap textarea:focus {
            border-color: var(--gold);
            background: rgba(4, 11, 22, .85);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, .12);
        }

        .file-upload-wrapper {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 26px 20px;
            border: 1.5px dashed rgba(212, 175, 55, .35);
            border-radius: 16px;
            text-align: center;
            transition: .3s;
            cursor: pointer;
            background: rgba(4, 11, 22, .5);
            position: relative;
            z-index: 2;
        }

        .file-upload-wrapper:hover {
            border-color: var(--gold);
            background: rgba(212, 175, 55, .06);
        }

        .file-upload-wrapper i { font-size: 2rem; color: var(--gold); }
        .file-upload-wrapper p {
            color: rgba(255, 255, 255, .7);
            font-size: .88rem;
            margin: 0;
            font-weight: 600;
        }
        .file-upload-wrapper p.hint {
            font-size: .72rem;
            color: rgba(255, 255, 255, .45);
            font-weight: 500;
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        #fileName {
            margin-top: 10px;
            font-size: .82rem;
            color: var(--gold-light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(212, 175, 55, .08);
            border-radius: 10px;
            border: 1px solid rgba(212, 175, 55, .25);
            position: relative;
            z-index: 2;
        }
        #fileName:empty { display: none; }

        .btn-submit {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            font-size: .95rem;
            font-weight: 800;
            cursor: pointer;
            transition: .3s;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 25px rgba(212, 175, 55, .25);
            font-family: inherit;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(212, 175, 55, .4);
        }

        /* ======================================================
           FORM SECTIONS (toggled by type)
        ====================================================== */
        .form-section { display: none; }
        .form-section.active { display: block; }

        /* ======================================================
           SECTION HEAD
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

        .section-head .count {
            margin-left: auto;
            font-size: .72rem;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--gold-light);
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(212, 175, 55, .1);
            border: 1px solid rgba(212, 175, 55, .3);
            font-weight: 700;
        }

        /* ======================================================
           TABLES
        ====================================================== */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 18px;
            border: 1px solid rgba(212, 175, 55, .18);
            background: rgba(8, 20, 40, .5);
            margin-bottom: 22px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
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
        .badge.private  { background: rgba(139, 163, 199, .15); color: #8ba3c7; border: 1px solid rgba(139, 163, 199, .35); }
        .badge.public   { background: rgba(212, 175, 55, .18); color: var(--gold-light); border: 1px solid rgba(212, 175, 55, .4); }
        .badge.pending  { background: rgba(241, 196, 15, .15); color: var(--warning); border: 1px solid rgba(241, 196, 15, .35); }
        .badge.approved { background: rgba(46, 204, 113, .15); color: var(--success); border: 1px solid rgba(46, 204, 113, .35); }
        .badge.active   { background: rgba(46, 204, 113, .15); color: var(--success); border: 1px solid rgba(46, 204, 113, .35); }
        .badge.rejected { background: rgba(231, 76, 60, .15); color: var(--danger); border: 1px solid rgba(231, 76, 60, .35); }
        .badge.paid     { background: rgba(52, 152, 219, .15); color: var(--info); border: 1px solid rgba(52, 152, 219, .35); }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: .76rem;
            cursor: pointer;
            transition: .3s;
            border: 1px solid transparent;
            font-family: inherit;
            letter-spacing: .4px;
        }

        .btn-danger {
            background: rgba(231, 76, 60, .12);
            color: #e74c3c;
            border-color: rgba(231, 76, 60, .4);
        }
        .btn-danger:hover { background: #e74c3c; color: white; }

        /* ======================================================
           NOTICE / MESSAGE
        ====================================================== */
        .message {
            padding: 15px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-weight: 600;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: .9rem;
            line-height: 1.5;
        }
        .message i { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
        .message.success { background: rgba(46, 204, 113, .12); color: var(--success); border: 1px solid rgba(46, 204, 113, .35); }
        .message.error   { background: rgba(231, 76, 60, .12);  color: #ff6b6b; border: 1px solid rgba(231, 76, 60, .35); }

        /* ======================================================
           LOAN PREVIEW
        ====================================================== */
        .loan-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin: 8px 0 22px;
            position: relative;
            z-index: 2;
        }
        .loan-preview-box {
            padding: 16px 18px;
            border-radius: 14px;
            background: rgba(4, 11, 22, .7);
            border: 1px solid rgba(212, 175, 55, .3);
        }
        .loan-preview-box .lp-label {
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            color: rgba(255, 255, 255, .5);
            font-weight: 700;
            margin-bottom: 6px;
        }
        .loan-preview-box .lp-value {
            font-size: 1.2rem;
            font-weight: 800;
            color: white;
            letter-spacing: -.5px;
        }
        .loan-preview-box .lp-value.interest { color: var(--warning); }
        .loan-preview-box .lp-value.total {
            background: linear-gradient(90deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* ======================================================
           EMPTY STATE
        ====================================================== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            border-radius: 18px;
            background: rgba(8, 20, 40, .4);
            border: 1px dashed rgba(212, 175, 55, .25);
            color: rgba(255, 255, 255, .55);
            margin-bottom: 22px;
        }
        .empty-state i {
            font-size: 2.4rem;
            color: var(--gold);
            margin-bottom: 12px;
            display: block;
            opacity: .7;
        }
        .empty-state strong {
            display: block;
            color: white;
            font-size: 1.02rem;
            margin-bottom: 6px;
        }
        .empty-state p { font-size: .88rem; }

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

            .post-card { padding: 22px 18px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .option-grid { grid-template-columns: 1fr; }
            .loan-preview { grid-template-columns: 1fr; }

            .section-head h2 { font-size: 1.05rem; }
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
            <a class="menu-item active" href="personal.php">
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
                <h1>Personal Desk</h1>
                <p>
                    Post investments, loans, and proposals — choose <strong>private</strong> (only SFS sees) or <strong>public</strong> (everyone sees).
                </p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="message <?= $flashType ?>">
                <i class="bx <?= $flashType === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?>"></i>
                <span><?= $flash ?></span>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             POST NEW ITEM
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-plus-circle"></i> Post New</h2>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" class="post-card" id="postForm">

            <!-- Type selector -->
            <div class="field">
                <label><i class="bx bx-category"></i> What do you want to post? *</label>
                <div class="option-grid">
                    <label class="option-card">
                        <input type="radio" name="post_type" value="investment" checked onchange="switchForm('investment')">
                        <div class="opt-box">
                            <i class="bx bx-trending-up"></i>
                            <span>Investment</span>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="post_type" value="loan" onchange="switchForm('loan')">
                        <div class="opt-box">
                            <i class="bx bx-credit-card"></i>
                            <span>Loan</span>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="post_type" value="proposal" onchange="switchForm('proposal')">
                        <div class="opt-box">
                            <i class="bx bx-bulb"></i>
                            <span>Business Proposal</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Visibility selector -->
            <div class="field">
                <label><i class="bx bx-show"></i> Visibility *</label>
                <div class="option-grid">
                    <label class="option-card">
                        <input type="radio" name="visibility" value="private" checked>
                        <div class="opt-box">
                            <i class="bx bx-lock-alt"></i>
                            <span>Private</span>
                        </div>
                    </label>
                    <label class="option-card">
                        <input type="radio" name="visibility" value="public">
                        <div class="opt-box">
                            <i class="bx bx-globe"></i>
                            <span>Public</span>
                        </div>
                    </label>
                </div>
                <div class="visibility-note">
                    <i class="bx bx-info-circle"></i>
                    <span>
                        <strong>Private</strong> — only you and SFS admins see this.
                        <strong>Public</strong> — everyone (including other members) can see this.
                    </span>
                </div>
            </div>

            <!-- ======================================================
                 INVESTMENT FORM
            ====================================================== -->
            <div class="form-section active" id="section-investment">

                <div class="form-row">
                    <div class="field">
                        <label><i class="bx bx-purchase-tag"></i> Title *</label>
                        <div class="input-wrap">
                            <i class="bx bx-bookmark"></i>
                            <input type="text" name="title" placeholder="e.g. Solar Farm Expansion" maxlength="200">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-category"></i> Category *</label>
                        <div class="input-wrap">
                            <i class="bx bx-category-alt"></i>
                            <input type="text" name="category" placeholder="e.g. Renewable Energy" maxlength="100">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label><i class="bx bx-money"></i> Amount (R) *</label>
                        <div class="input-wrap">
                            <i class="bx bx-coin-stack"></i>
                            <input type="number" name="amount" placeholder="0.00" step="0.01" min="0.01">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-line-chart"></i> Expected ROI (%)</label>
                        <div class="input-wrap">
                            <i class="bx bx-line-chart"></i>
                            <input type="number" name="expected_roi" placeholder="e.g. 15" step="0.01" min="0" max="999.99">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-time"></i> Duration (months)</label>
                        <div class="input-wrap">
                            <i class="bx bx-calendar"></i>
                            <input type="number" name="duration_months" placeholder="e.g. 12" min="1" max="120">
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label><i class="bx bx-detail"></i> Description *</label>
                    <div class="input-wrap">
                        <i class="bx bx-edit"></i>
                        <textarea name="description" placeholder="Describe the investment, expected returns, and any risks..." maxlength="2000"></textarea>
                    </div>
                </div>

                <button type="submit" name="add_investment" class="btn-submit">
                    <i class="bx bx-send"></i> Submit Investment
                </button>
            </div>

            <!-- ======================================================
                 LOAN FORM
            ====================================================== -->
            <div class="form-section" id="section-loan">

                <div class="form-row">
                    <div class="field">
                        <label><i class="bx bx-money"></i> Loan Amount (R) *</label>
                        <div class="input-wrap">
                            <i class="bx bx-coin-stack"></i>
                            <input type="number" name="principal" id="loanPrincipal"
                                   placeholder="e.g. 100" step="0.01" min="1" max="1000000">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-time"></i> Term (months, optional)</label>
                        <div class="input-wrap">
                            <i class="bx bx-calendar"></i>
                            <input type="number" name="term_months" placeholder="e.g. 12" min="1" max="60">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-purchase-tag"></i> Purpose *</label>
                        <div class="input-wrap">
                            <i class="bx bx-bookmark"></i>
                            <input type="text" name="purpose" placeholder="e.g. Business stock" maxlength="200">
                        </div>
                    </div>
                </div>

                <div class="loan-preview">
                    <div class="loan-preview-box">
                        <div class="lp-label">Principal</div>
                        <div class="lp-value" id="previewPrincipal">R0.00</div>
                    </div>
                    <div class="loan-preview-box">
                        <div class="lp-label">Interest (25%)</div>
                        <div class="lp-value interest" id="previewInterest">R0.00</div>
                    </div>
                    <div class="loan-preview-box">
                        <div class="lp-label">Total Repayable</div>
                        <div class="lp-value total" id="previewTotal">R0.00</div>
                    </div>
                </div>

                <button type="submit" name="take_loan" class="btn-submit">
                    <i class="bx bx-send"></i> Submit Loan Application
                </button>
            </div>

            <!-- ======================================================
                 PROPOSAL FORM
            ====================================================== -->
            <div class="form-section" id="section-proposal">

                <div class="form-row">
                    <div class="field">
                        <label><i class="bx bx-purchase-tag"></i> Title *</label>
                        <div class="input-wrap">
                            <i class="bx bx-bookmark"></i>
                            <input type="text" name="title" placeholder="e.g. Solar Farm Expansion Project" maxlength="200">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-category"></i> Proposal Type *</label>
                        <div class="input-wrap">
                            <i class="bx bx-category-alt"></i>
                            <select name="type" required style="width:100%;padding:14px 16px 14px 48px;border-radius:14px;border:1px solid rgba(255,255,255,.1);background:rgba(4,11,22,.6);color:white;font-size:.95rem;font-family:inherit;outline:none;">
                                <option value="funding">Funding</option>
                                <option value="partnership">Partnership</option>
                                <option value="investment">Investment</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="bx bx-money"></i> Amount Needed (R) *</label>
                        <div class="input-wrap">
                            <i class="bx bx-coin-stack"></i>
                            <input type="number" name="amount_needed" placeholder="0.00" step="0.01" min="1">
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label><i class="bx bx-detail"></i> Description *</label>
                    <div class="input-wrap">
                        <i class="bx bx-edit"></i>
                        <textarea name="description" placeholder="Describe your proposal, its purpose, and why people should support it..." maxlength="2000"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label><i class="fas fa-link"></i> Website Link</label>
                        <div class="input-wrap">
                            <i class="bx bx-link"></i>
                            <input type="url" name="contact_link" placeholder="https://www.sfs.com" maxlength="255">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                        <div class="input-wrap">
                            <i class="bx bxl-whatsapp"></i>
                            <input type="text" name="contact_whatsapp" placeholder="e.g. 0721448175" maxlength="20">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <div class="input-wrap">
                            <i class="bx bx-envelope"></i>
                            <input type="email" name="contact_email" placeholder="sfs@gmail.com" maxlength="150">
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label><i class="bx bx-paperclip"></i> Attach Business Plan (optional)</label>
                    <div class="file-upload-wrapper">
                        <i class="bx bx-cloud-upload"></i>
                        <p>Click to upload or drag &amp; drop</p>
                        <p class="hint">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG — Max 10MB</p>
                        <input type="file" name="business_plan" id="businessPlanInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    </div>
                    <div id="fileName"></div>
                </div>

                <button type="submit" name="post_proposal" class="btn-submit">
                    <i class="bx bx-send"></i> Submit Proposal
                </button>
            </div>

        </form>

        <!-- ======================================================
             PRIVATE ITEMS
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-lock-alt"></i> Private</h2>
        </div>

        <!-- Private investments -->
        <h3 style="font-size:.95rem;margin:0 0 12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;">
            <i class="bx bx-trending-up" style="color:var(--gold);"></i> Investments (<?= count($privInvestments) ?>)
        </h3>
        <?php if (!empty($privInvestments)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>ROI</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($privInvestments as $i): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($i['title']) ?></strong></td>
                                <td style="color:rgba(255,255,255,.7);"><?= htmlspecialchars($i['category']) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($i['amount'], 2) ?></strong></td>
                                <td><?= $i['expected_roi'] !== null ? number_format($i['expected_roi'], 2) . '%' : '—' ?></td>
                                <td><?= $i['duration_months'] ? (int) $i['duration_months'] . ' mo' : '—' ?></td>
                                <td><span class="badge <?= htmlspecialchars($i['status']) ?>"><?= htmlspecialchars($i['status']) ?></span></td>
                                <td style="color:rgba(255,255,255,.65);font-size:.82rem;"><?= date('d M Y', strtotime($i['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this investment?');">
                                        <input type="hidden" name="item_id" value="<?= (int) $i['id'] ?>">
                                        <input type="hidden" name="item_table" value="personal_investments">
                                        <button type="submit" name="delete_item" class="btn btn-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-trending-up"></i>
                <strong>No private investments yet</strong>
                <p>Post one above with visibility set to Private.</p>
            </div>
        <?php endif; ?>

        <!-- Private loans -->
        <h3 style="font-size:.95rem;margin:24px 0 12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;">
            <i class="bx bx-credit-card" style="color:var(--gold);"></i> Loans (<?= count($privLoans) ?>)
        </h3>
        <?php if (!empty($privLoans)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Principal</th>
                            <th>Interest (25%)</th>
                            <th>Total Repayable</th>
                            <th>Purpose</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($privLoans as $l): ?>
                            <tr>
                                <td><strong style="color:var(--gold);">R<?= number_format($l['principal'], 2) ?></strong></td>
                                <td style="color:var(--warning);font-weight:700;">R<?= number_format($l['interest_amount'], 2) ?></td>
                                <td><strong style="color:var(--gold-light);">R<?= number_format($l['total_repayable'], 2) ?></strong></td>
                                <td style="color:rgba(255,255,255,.75);"><?= htmlspecialchars($l['purpose'] ?: '—') ?></td>
                                <td><?= $l['term_months'] ? (int) $l['term_months'] . ' mo' : '—' ?></td>
                                <td><span class="badge <?= htmlspecialchars($l['status']) ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                                <td style="color:rgba(255,255,255,.65);font-size:.82rem;"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if ($l['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Withdraw this loan application?');">
                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                            <button type="submit" name="cancel_loan" class="btn btn-danger">
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this loan record?');">
                                        <input type="hidden" name="item_id" value="<?= (int) $l['id'] ?>">
                                        <input type="hidden" name="item_table" value="personal_loans">
                                        <button type="submit" name="delete_item" class="btn btn-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-credit-card"></i>
                <strong>No private loans yet</strong>
                <p>Post one above with visibility set to Private.</p>
            </div>
        <?php endif; ?>

        <!-- Private proposals -->
        <h3 style="font-size:.95rem;margin:24px 0 12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;">
            <i class="bx bx-bulb" style="color:var(--gold);"></i> Business Proposals (<?= count($privProposals) ?>)
        </h3>
        <?php if (!empty($privProposals)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Amount Needed</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($privProposals as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($p['type']) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($p['amount_needed'], 2) ?></strong></td>
                                <td>
                                    <?php if (!empty($p['business_plan'])): ?>
                                        <a href="<?= htmlspecialchars($p['business_plan']) ?>" target="_blank" style="color:var(--light);font-weight:700;font-size:.8rem;">
                                            <i class="bx bx-paperclip"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span style="color:rgba(255,255,255,.4);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                                <td style="color:rgba(255,255,255,.65);font-size:.82rem;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this proposal?');">
                                        <input type="hidden" name="item_id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="item_table" value="personal_proposals">
                                        <button type="submit" name="delete_item" class="btn btn-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-bulb"></i>
                <strong>No private proposals yet</strong>
                <p>Post one above with visibility set to Private.</p>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             PUBLIC ITEMS
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-globe"></i> Public</h2>
        </div>

        <!-- Public investments -->
        <h3 style="font-size:.95rem;margin:0 0 12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;">
            <i class="bx bx-trending-up" style="color:var(--gold);"></i> Investments (<?= count($pubInvestments) ?>)
        </h3>
        <?php if (!empty($pubInvestments)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>ROI</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pubInvestments as $i): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($i['title']) ?></strong></td>
                                <td style="color:rgba(255,255,255,.7);"><?= htmlspecialchars($i['category']) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($i['amount'], 2) ?></strong></td>
                                <td><?= $i['expected_roi'] !== null ? number_format($i['expected_roi'], 2) . '%' : '—' ?></td>
                                <td><?= $i['duration_months'] ? (int) $i['duration_months'] . ' mo' : '—' ?></td>
                                <td><span class="badge <?= htmlspecialchars($i['status']) ?>"><?= htmlspecialchars($i['status']) ?></span></td>
                                <td style="color:rgba(255,255,255,.65);font-size:.82rem;"><?= date('d M Y', strtotime($i['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this investment?');">
                                        <input type="hidden" name="item_id" value="<?= (int) $i['id'] ?>">
                                        <input type="hidden" name="item_table" value="personal_investments">
                                        <button type="submit" name="delete_item" class="btn btn-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-trending-up"></i>
                <strong>No public investments yet</strong>
                <p>Post one above with visibility set to Public.</p>
            </div>
        <?php endif; ?>

        <!-- Public loans -->
        <h3 style="font-size:.95rem;margin:24px 0 12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;">
            <i class="bx bx-credit-card" style="color:var(--gold);"></i> Loans (<?= count($pubLoans) ?>)
        </h3>
        <?php if (!empty($pubLoans)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Principal</th>
                            <th>Interest (25%)</th>
                            <th>Total Repayable</th>
                            <th>Purpose</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pubLoans as $l): ?>
                            <tr>
                                <td><strong style="color:var(--gold);">R<?= number_format($l['principal'], 2) ?></strong></td>
                                <td style="color:var(--warning);font-weight:700;">R<?= number_format($l['interest_amount'], 2) ?></td>
                                <td><strong style="color:var(--gold-light);">R<?= number_format($l['total_repayable'], 2) ?></strong></td>
                                <td style="color:rgba(255,255,255,.75);"><?= htmlspecialchars($l['purpose'] ?: '—') ?></td>
                                <td><?= $l['term_months'] ? (int) $l['term_months'] . ' mo' : '—' ?></td>
                                <td><span class="badge <?= htmlspecialchars($l['status']) ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                                <td style="color:rgba(255,255,255,.65);font-size:.82rem;"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this loan record?');">
                                        <input type="hidden" name="item_id" value="<?= (int) $l['id'] ?>">
                                        <input type="hidden" name="item_table" value="personal_loans">
                                        <button type="submit" name="delete_item" class="btn btn-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-credit-card"></i>
                <strong>No public loans yet</strong>
                <p>Post one above with visibility set to Public.</p>
            </div>
        <?php endif; ?>

        <!-- Public proposals -->
        <h3 style="font-size:.95rem;margin:24px 0 12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;">
            <i class="bx bx-bulb" style="color:var(--gold);"></i> Business Proposals (<?= count($pubProposals) ?>)
        </h3>
        <?php if (!empty($pubProposals)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Amount Needed</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pubProposals as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                                <td style="text-transform:capitalize;"><?= htmlspecialchars($p['type']) ?></td>
                                <td><strong style="color:var(--gold);">R<?= number_format($p['amount_needed'], 2) ?></strong></td>
                                <td>
                                    <?php if (!empty($p['business_plan'])): ?>
                                        <a href="<?= htmlspecialchars($p['business_plan']) ?>" target="_blank" style="color:var(--light);font-weight:700;font-size:.8rem;">
                                            <i class="bx bx-paperclip"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span style="color:rgba(255,255,255,.4);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                                <td style="color:rgba(255,255,255,.65);font-size:.82rem;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this proposal?');">
                                        <input type="hidden" name="item_id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="item_table" value="personal_proposals">
                                        <button type="submit" name="delete_item" class="btn btn-danger">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-bulb"></i>
                <strong>No public proposals yet</strong>
                <p>Post one above with visibility set to Public.</p>
            </div>
        <?php endif; ?>

    </main>

    <script>
        // =====================================================
// DISABLE HIDDEN FORM SECTIONS BEFORE SUBMIT
// =====================================================
document.querySelectorAll('.form-section').forEach(function (section) {
    const inputs = section.querySelectorAll('input, textarea, select');

    // Disable inputs when section isn't active
    const observer = new MutationObserver(function () {
        const isActive = section.classList.contains('active');
        inputs.forEach(function (el) {
            // Never disable the post_type / visibility radios — they must always post
            if (el.name === 'post_type' || el.name === 'visibility') return;
            el.disabled = !isActive;
        });
    });

    observer.observe(section, { attributes: true, attributeFilter: ['class'] });

    // Initialize on load
    const isActive = section.classList.contains('active');
    inputs.forEach(function (el) {
        if (el.name === 'post_type' || el.name === 'visibility') return;
        el.disabled = !isActive;
    });
});
        
        function switchForm(type) {
            document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
            const target = document.getElementById('section-' + type);
            if (target) target.classList.add('active');
        }

        (function () {
            const LOAN_RATE = 25.00;
            const principalInput = document.getElementById('loanPrincipal');
            const previewPrincipal = document.getElementById('previewPrincipal');
            const previewInterest  = document.getElementById('previewInterest');
            const previewTotal     = document.getElementById('previewTotal');

            if (!principalInput) return;

            function formatR(n) {
                return 'R' + (isFinite(n) ? n : 0).toLocaleString('en-ZA', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function recalc() {
                const principal = parseFloat(principalInput.value) || 0;
                const interest  = principal * (LOAN_RATE / 100);
                const total     = principal + interest;
                previewPrincipal.textContent = formatR(principal);
                previewInterest.textContent  = formatR(interest);
                previewTotal.textContent     = formatR(total);
            }

            principalInput.addEventListener('input', recalc);
            recalc();
        })();

        (function () {
            const input = document.getElementById('businessPlanInput');
            const display = document.getElementById('fileName');
            if (!input || !display) return;

            input.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const f = this.files[0];
                    const sizeMB = (f.size / 1024 / 1024).toFixed(2);
                    display.innerHTML = '<i class="bx bx-paperclip"></i> ' + f.name + ' (' + sizeMB + ' MB)';
                } else {
                    display.innerHTML = '';
                }
            });
        })();

        console.log('📝 Personal Desk loaded for <?= htmlspecialchars($userName, ENT_QUOTES) ?>');
    </script>

</body>

</html>