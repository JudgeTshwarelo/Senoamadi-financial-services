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
$userEmail = $_SESSION['email'] ?? '';

$db = get_db();

// ---------------------------------------------------------
// Ensure feedback table exists
// ---------------------------------------------------------
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category ENUM('suggestion','complaint','improvement','bug','feature','other') NOT NULL DEFAULT 'suggestion',
            priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            subject VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            attachment VARCHAR(255) NULL,
            is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('new','in_review','resolved','closed') NOT NULL DEFAULT 'new',
            admin_reply TEXT NULL,
            replied_at DATETIME NULL,
            replied_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_feedback_user (user_id),
            INDEX idx_feedback_status (status),
            INDEX idx_feedback_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {}

// ---------------------------------------------------------
// Handle new feedback submission
// ---------------------------------------------------------
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {

    $category     = trim($_POST['category'] ?? 'suggestion');
    $priority     = trim($_POST['priority'] ?? 'medium');
    $subject      = trim($_POST['subject'] ?? '');
    $body         = trim($_POST['message'] ?? '');
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    $errors = [];

    $validCategories = ['suggestion','complaint','improvement','bug','feature','other'];
    if (!in_array($category, $validCategories)) {
        $errors[] = 'Invalid category selected.';
    }

    $validPriorities = ['low','medium','high','urgent'];
    if (!in_array($priority, $validPriorities)) {
        $errors[] = 'Invalid priority selected.';
    }

    if ($subject === '')    $errors[] = 'Subject is required.';
    if (strlen($subject) > 200) $errors[] = 'Subject is too long (max 200 characters).';
    if ($body === '')       $errors[] = 'Message is required.';
    if (strlen($body) < 10) $errors[] = 'Message must be at least 10 characters.';
    if (strlen($body) > 5000) $errors[] = 'Message is too long (max 5000 characters).';

    // ---------------------------------------------------------
    // Handle file attachment
    // ---------------------------------------------------------
    $attachmentPath = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File is too large. Maximum size is 5MB.';
        } else {
            $allowed = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, WEBP, DOC, DOCX, TXT.';
            } else {
                $uploadDir = __DIR__ . '/uploads/feedback/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safeName = 'feedback_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $target   = $uploadDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $attachmentPath = 'uploads/feedback/' . $safeName;
                } else {
                    $errors[] = 'Failed to save uploaded file.';
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO feedback
                    (user_id, category, priority, subject, message, attachment, is_anonymous, status, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, 'new', NOW())
            ");
            $stmt->execute([
                $uid,
                $category,
                $priority,
                $subject,
                $body,
                $attachmentPath,
                $is_anonymous
            ]);

            $message = 'Thank you! Your ' . htmlspecialchars($category) . ' has been sent to the admin. We appreciate your feedback.';
            $messageType = 'success';

        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}

// ---------------------------------------------------------
// Handle EDIT feedback
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_feedback'])) {

    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
    $category   = trim($_POST['category'] ?? 'suggestion');
    $priority   = trim($_POST['priority'] ?? 'medium');
    $subject    = trim($_POST['subject'] ?? '');
    $body       = trim($_POST['message'] ?? '');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    $errors = [];

    $validCategories = ['suggestion','complaint','improvement','bug','feature','other'];
    if (!in_array($category, $validCategories)) {
        $errors[] = 'Invalid category selected.';
    }

    $validPriorities = ['low','medium','high','urgent'];
    if (!in_array($priority, $validPriorities)) {
        $errors[] = 'Invalid priority selected.';
    }

    if ($subject === '')    $errors[] = 'Subject is required.';
    if (strlen($subject) > 200) $errors[] = 'Subject is too long (max 200 characters).';
    if ($body === '')       $errors[] = 'Message is required.';
    if (strlen($body) < 10) $errors[] = 'Message must be at least 10 characters.';
    if (strlen($body) > 5000) $errors[] = 'Message is too long (max 5000 characters).';

    // Verify ownership
    $owns = false;
    try {
        $stmt = $db->prepare("SELECT id FROM feedback WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$feedbackId, $uid]);
        $owns = (bool) $stmt->fetch();
    } catch (PDOException $e) {}

    if (!$owns) {
        $errors[] = 'Feedback not found or you do not have permission to edit it.';
    }

    // Handle optional new attachment
    $newAttachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File is too large. Maximum size is 5MB.';
        } else {
            $allowed = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, WEBP, DOC, DOCX, TXT.';
            } else {
                $uploadDir = __DIR__ . '/uploads/feedback/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safeName = 'feedback_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $target   = $uploadDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $newAttachment = 'uploads/feedback/' . $safeName;
                } else {
                    $errors[] = 'Failed to save uploaded file.';
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($newAttachment !== null) {
                $stmt = $db->prepare("
                    UPDATE feedback
                    SET category = ?, priority = ?, subject = ?, message = ?,
                        attachment = ?, is_anonymous = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $category, $priority, $subject, $body,
                    $newAttachment, $isAnonymous, $feedbackId, $uid
                ]);
            } else {
                $stmt = $db->prepare("
                    UPDATE feedback
                    SET category = ?, priority = ?, subject = ?, message = ?,
                        is_anonymous = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $category, $priority, $subject, $body,
                    $isAnonymous, $feedbackId, $uid
                ]);
            }

            $message = 'Your message has been updated successfully.';
            $messageType = 'success';

        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}

// ---------------------------------------------------------
// Handle DELETE feedback
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {

    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);

    try {
        $stmt = $db->prepare("SELECT attachment FROM feedback WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$feedbackId, $uid]);
        $row = $stmt->fetch();

        if ($row) {
            if (!empty($row['attachment'])) {
                $path = __DIR__ . '/' . $row['attachment'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            $stmt = $db->prepare("DELETE FROM feedback WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$feedbackId, $uid]);

            $message = 'Your message has been deleted.';
            $messageType = 'success';
        } else {
            $message = 'Message not found or already deleted.';
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ---------------------------------------------------------
// Fetch user's feedback history
// ---------------------------------------------------------
$myFeedback = [];
try {
    $stmt = $db->prepare("
        SELECT id, category, priority, subject, message, attachment, is_anonymous,
               status, admin_reply, replied_at, replied_by, created_at
        FROM feedback
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$uid]);
    $myFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $myFeedback = [];
}

// ---------------------------------------------------------
// Stats
// ---------------------------------------------------------
$stats = [
    'total'     => 0,
    'new'       => 0,
    'in_review' => 0,
    'resolved'  => 0,
    'closed'    => 0
];

try {
    $stmt = $db->prepare("
        SELECT status, COUNT(*) AS total
        FROM feedback
        WHERE user_id = ?
        GROUP BY status
    ");
    $stmt->execute([$uid]);
    while ($row = $stmt->fetch()) {
        $s = strtolower($row['status']);
        if (isset($stats[$s])) $stats[$s] = (int) $row['total'];
        $stats['total'] += (int) $row['total'];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SUGGESTIONS | SFS</title>
    <meta name="description" content="Send suggestions, complaints, or improvements to Senoamadi Bank admin." />
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
           MAIN CONTENT
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
            margin-bottom: 36px;
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

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(212, 175, 55, .12);
            color: var(--gold-light);
            padding: 6px 16px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 14px;
            border: 1px solid rgba(212, 175, 55, .3);
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

        /* ======================================================
           STATS GRID
        ====================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 18px;
            margin-bottom: 34px;
        }

        .stat-box {
            position: relative;
            overflow: hidden;
            padding: 20px 22px;
            border-radius: 18px;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .18);
            transition: .35s;
        }

        .stat-box::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(212, 175, 55, .12), transparent 70%);
            transform: translate(35%, -35%);
            pointer-events: none;
        }

        .stat-box:hover {
            transform: translateY(-4px);
            border-color: var(--gold);
            box-shadow: 0 14px 32px rgba(212, 175, 55, .15);
        }

        .stat-box .stat-label {
            display: block;
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: rgba(255, 255, 255, .55);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-box .stat-value {
            display: block;
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            letter-spacing: -.5px;
        }

        .stat-box .stat-value.gold {
            background: linear-gradient(90deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-box .stat-value.blue   { color: var(--info); }
        .stat-box .stat-value.yellow { color: var(--warning); }
        .stat-box .stat-value.green  { color: var(--success); }

        /* ======================================================
           SECTION HEAD
        ====================================================== */
        .section-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 30px 0 20px;
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
           FORM CARD
        ====================================================== */
        .form-card {
            position: relative;
            overflow: hidden;
            padding: 32px 30px;
            border-radius: 22px;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .2);
        }

        .form-card::before {
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

        .field { margin-bottom: 22px; position: relative; z-index: 2; }

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

        /* ======================================================
           CATEGORY GRID
        ====================================================== */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
        }

        .category-option {
            position: relative;
            cursor: pointer;
        }

        .category-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .category-option .cat-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 18px 10px;
            border-radius: 14px;
            border: 1.5px solid rgba(255, 255, 255, .1);
            background: rgba(4, 11, 22, .6);
            transition: .3s;
            text-align: center;
        }

        .category-option .cat-box i {
            font-size: 1.6rem;
            color: var(--gold);
        }

        .category-option .cat-box span {
            font-weight: 600;
            font-size: .82rem;
            color: rgba(255, 255, 255, .75);
        }

        .category-option .cat-box:hover {
            border-color: rgba(212, 175, 55, .5);
            background: rgba(212, 175, 55, .05);
        }

        .category-option input[type="radio"]:checked + .cat-box {
            border-color: var(--gold);
            background: rgba(212, 175, 55, .12);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, .12);
        }

        .category-option input[type="radio"]:checked + .cat-box i,
        .category-option input[type="radio"]:checked + .cat-box span {
            color: var(--gold-light);
        }

        /* ======================================================
           PRIORITY ROW
        ====================================================== */
        .priority-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 10px;
        }

        .priority-option {
            position: relative;
            cursor: pointer;
        }

        .priority-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .priority-option .prio-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1.5px solid rgba(255, 255, 255, .1);
            background: rgba(4, 11, 22, .6);
            transition: .3s;
            font-size: .85rem;
            font-weight: 700;
            color: rgba(255, 255, 255, .7);
        }

        .priority-option .prio-box i { font-size: 16px; }

        .priority-option .prio-box:hover {
            border-color: rgba(212, 175, 55, .4);
        }

        .priority-option.low input:checked + .prio-box     { color: var(--success); border-color: var(--success); background: rgba(46, 204, 113, .12); }
        .priority-option.medium input:checked + .prio-box  { color: var(--info);    border-color: var(--info);    background: rgba(52, 152, 219, .12); }
        .priority-option.high input:checked + .prio-box    { color: var(--warning); border-color: var(--warning); background: rgba(241, 196, 15, .12); }
        .priority-option.urgent input:checked + .prio-box  { color: var(--danger);  border-color: var(--danger);  background: rgba(231, 76, 60, .12); }

        /* ======================================================
           INPUT FIELDS
        ====================================================== */
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
            min-height: 160px;
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

        /* ======================================================
           FILE UPLOAD
        ====================================================== */
        .file-upload-wrapper {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 28px 20px;
            border: 1.5px dashed rgba(212, 175, 55, .35);
            border-radius: 16px;
            text-align: center;
            transition: .3s;
            cursor: pointer;
            background: rgba(4, 11, 22, .5);
        }

        .file-upload-wrapper:hover {
            border-color: var(--gold);
            background: rgba(212, 175, 55, .06);
        }

        .file-upload-wrapper i {
            font-size: 2.2rem;
            color: var(--gold);
        }

        .file-upload-wrapper p {
            color: rgba(255, 255, 255, .7);
            font-size: .88rem;
            margin: 0;
            font-weight: 600;
        }

        .file-upload-wrapper p.hint {
            font-size: .75rem;
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
            font-size: .85rem;
            color: var(--gold-light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(212, 175, 55, .08);
            border-radius: 10px;
            border: 1px solid rgba(212, 175, 55, .25);
        }

        #fileName:empty { display: none; }

        /* ======================================================
           ANONYMOUS TOGGLE
        ====================================================== */
        .toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            background: rgba(4, 11, 22, .55);
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .08);
            cursor: pointer;
            transition: .3s;
        }

        .toggle-wrapper:hover {
            border-color: rgba(212, 175, 55, .35);
            background: rgba(212, 175, 55, .04);
        }

        .toggle-wrapper input[type="checkbox"] {
            appearance: none;
            width: 46px;
            height: 26px;
            background: rgba(255, 255, 255, .15);
            border-radius: 20px;
            position: relative;
            cursor: pointer;
            transition: .3s;
            flex-shrink: 0;
            border: none;
            outline: none;
        }

        .toggle-wrapper input[type="checkbox"]::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: .3s;
        }

        .toggle-wrapper input[type="checkbox"]:checked {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
        }

        .toggle-wrapper input[type="checkbox"]:checked::after {
            left: 23px;
        }

        .toggle-wrapper .toggle-text {
            font-size: .9rem;
            color: rgba(255, 255, 255, .75);
            line-height: 1.4;
        }

        .toggle-wrapper .toggle-text strong { color: var(--gold-light); }

        /* ======================================================
           SUBMIT BUTTON
        ====================================================== */
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

        .btn-submit i { font-size: 20px; }

        /* ======================================================
           MESSAGE
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
           FEEDBACK HISTORY
        ====================================================== */
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feedback-item {
            position: relative;
            overflow: hidden;
            background: rgba(8, 20, 40, .65);
            border: 1px solid rgba(212, 175, 55, .18);
            border-left: 4px solid var(--gold);
            border-radius: 18px;
            padding: 24px 26px;
            transition: .3s;
        }

        .feedback-item::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(212, 175, 55, .06), transparent 70%);
            transform: translate(30%, -30%);
            pointer-events: none;
        }

        .feedback-item:hover {
            transform: translateY(-3px);
            border-color: rgba(212, 175, 55, .4);
            box-shadow: 0 18px 42px rgba(212, 175, 55, .12);
        }

        .feedback-item.cat-suggestion   { border-left-color: var(--info); }
        .feedback-item.cat-complaint    { border-left-color: var(--danger); }
        .feedback-item.cat-improvement  { border-left-color: var(--success); }
        .feedback-item.cat-bug          { border-left-color: var(--warning); }
        .feedback-item.cat-feature      { border-left-color: var(--purple); }
        .feedback-item.cat-other        { border-left-color: rgba(139, 163, 199, 1); }

        .feedback-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
            position: relative;
            z-index: 2;
        }

        .feedback-header h4 {
            font-size: 1.1rem;
            color: white;
            margin: 0;
            flex: 1 1 200px;
            font-weight: 700;
            line-height: 1.35;
        }

        .feedback-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
            position: relative;
            z-index: 2;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .68rem;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: .6px;
            border: 1px solid transparent;
        }

        .pill-category {
            background: rgba(116, 185, 255, .12);
            color: var(--light);
            border-color: rgba(116, 185, 255, .3);
        }

        .pill-priority.low     { background: rgba(46, 204, 113, .12); color: var(--success); border-color: rgba(46, 204, 113, .3); }
        .pill-priority.medium  { background: rgba(52, 152, 219, .12); color: var(--info);    border-color: rgba(52, 152, 219, .3); }
        .pill-priority.high    { background: rgba(241, 196, 15, .12); color: var(--warning); border-color: rgba(241, 196, 15, .3); }
        .pill-priority.urgent  { background: rgba(231, 76, 60, .12);  color: var(--danger);  border-color: rgba(231, 76, 60, .3); }

        .pill-anon {
            background: rgba(155, 89, 182, .12);
            color: var(--purple);
            border-color: rgba(155, 89, 182, .3);
        }

        .feedback-body {
            font-size: .93rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, .75);
            margin-bottom: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            position: relative;
            z-index: 2;
        }

        .feedback-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, .07);
            font-size: .78rem;
            color: rgba(255, 255, 255, .5);
            position: relative;
            z-index: 2;
        }

        .feedback-footer span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge {
            padding: 5px 13px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            display: inline-block;
            white-space: nowrap;
        }

        .status-badge.new        { background: rgba(52, 152, 219, .15); color: var(--info);    border: 1px solid rgba(52, 152, 219, .35); }
        .status-badge.in_review  { background: rgba(241, 196, 15, .15); color: var(--warning); border: 1px solid rgba(241, 196, 15, .35); }
        .status-badge.resolved   { background: rgba(46, 204, 113, .15); color: var(--success); border: 1px solid rgba(46, 204, 113, .35); }
        .status-badge.closed     { background: rgba(139, 163, 199, .15); color: #8ba3c7;      border: 1px solid rgba(139, 163, 199, .35); }

        .admin-reply {
            margin-top: 14px;
            padding: 16px 20px;
            background: rgba(212, 175, 55, .08);
            border-left: 3px solid var(--gold);
            border-radius: 12px;
            position: relative;
            z-index: 2;
        }

        .admin-reply .reply-label {
            font-size: .68rem;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 800;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .admin-reply .reply-text {
            font-size: .9rem;
            color: rgba(255, 255, 255, .85);
            line-height: 1.65;
            white-space: pre-wrap;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--light);
            font-size: .82rem;
            font-weight: 700;
            margin-top: 6px;
            padding: 6px 14px;
            background: rgba(116, 185, 255, .1);
            border: 1px solid rgba(116, 185, 255, .3);
            border-radius: 999px;
            transition: .3s;
            position: relative;
            z-index: 2;
        }

        .attachment-link:hover {
            background: rgba(212, 175, 55, .12);
            color: var(--gold-light);
            border-color: rgba(212, 175, 55, .4);
        }

        /* ======================================================
           FEEDBACK ACTIONS (EDIT / DELETE)
        ====================================================== */
        .feedback-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, .07);
            position: relative;
            z-index: 2;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 16px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            transition: .3s;
            border: 1px solid transparent;
            font-family: inherit;
            letter-spacing: .4px;
        }

        .btn-action i { font-size: 15px; }

        .btn-edit {
            background: rgba(58, 123, 213, .12);
            color: var(--light);
            border-color: rgba(58, 123, 213, .35);
        }

        .btn-edit:hover {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(58, 123, 213, .35);
        }

        .btn-delete {
            background: rgba(231, 76, 60, .12);
            color: #ff6b6b;
            border-color: rgba(231, 76, 60, .35);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, .35);
        }

        /* ======================================================
           MODAL (EDIT)
        ====================================================== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 11, 22, .85);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            width: 100%;
            max-width: 620px;
            max-height: 92vh;
            overflow-y: auto;
            background: linear-gradient(135deg, #071427, #040B16);
            border: 1px solid rgba(212, 175, 55, .3);
            border-radius: 22px;
            padding: 30px 28px;
            position: relative;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .6), 0 0 40px rgba(212, 175, 55, .15);
            animation: modalIn .3s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(20px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(90deg, white, var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i { color: var(--gold); -webkit-text-fill-color: var(--gold); }

        .modal-close {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .75);
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .3s;
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: rgba(231, 76, 60, .2);
            color: #ff6b6b;
            border-color: rgba(231, 76, 60, .4);
            transform: rotate(90deg);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, .07);
        }

        .modal-btn {
            flex: 1;
            padding: 14px;
            border-radius: 14px;
            font-size: .9rem;
            font-weight: 800;
            cursor: pointer;
            transition: .3s;
            letter-spacing: .8px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid transparent;
            font-family: inherit;
        }

        .modal-btn.cancel {
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .8);
            border-color: rgba(255, 255, 255, .12);
        }

        .modal-btn.cancel:hover {
            background: rgba(255, 255, 255, .1);
            color: white;
        }

        .modal-btn.save {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--navy);
            box-shadow: 0 10px 25px rgba(212, 175, 55, .25);
        }

        .modal-btn.save:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(212, 175, 55, .4);
        }

        .modal-btn i { font-size: 18px; }

        /* ======================================================
           CONFIRM DELETE MODAL
        ====================================================== */
        .confirm-box {
            max-width: 440px;
            text-align: center;
        }

        .confirm-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: rgba(231, 76, 60, .12);
            border: 2px solid rgba(231, 76, 60, .35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #ff6b6b;
            animation: pulseWarn 2s infinite;
        }

        @keyframes pulseWarn {
            0%, 100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, .4); }
            50%      { box-shadow: 0 0 0 14px rgba(231, 76, 60, 0); }
        }

        .confirm-box h3 {
            font-size: 1.3rem;
            color: white;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .confirm-box p {
            font-size: .92rem;
            color: rgba(255, 255, 255, .65);
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .confirm-actions {
            display: flex;
            gap: 12px;
        }

        .confirm-actions .modal-btn {
            text-transform: uppercase;
        }

        .modal-btn.danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 10px 25px rgba(231, 76, 60, .3);
        }

        .modal-btn.danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(231, 76, 60, .5);
        }

        /* ======================================================
           EMPTY STATE
        ====================================================== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            border-radius: 18px;
            background: rgba(8, 20, 40, .4);
            border: 1px dashed rgba(212, 175, 55, .25);
            color: rgba(255, 255, 255, .55);
        }

        .empty-state i {
            font-size: 2.8rem;
            color: var(--gold);
            margin-bottom: 14px;
            display: block;
            opacity: .7;
        }

        .empty-state strong {
            display: block;
            color: white;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: .9rem;
            line-height: 1.6;
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

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-box { padding: 16px 14px; }
            .stat-box .stat-value { font-size: 1.3rem; }

            .category-grid { grid-template-columns: repeat(2, 1fr); }
            .priority-row { grid-template-columns: repeat(2, 1fr); }

            .form-card { padding: 22px 18px; }

            .feedback-item { padding: 20px 18px; }
            .feedback-header h4 { font-size: 1rem; }

            .modal-box { padding: 22px 18px; }

            .feedback-actions { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }

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
        <a class="menu-item" href="user_page.php">
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
        <a class="menu-item active" href="suggestion.php">
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
                <h1>Suggestions </h1>
                <p>
                    Write a suggestion, complaint, or idea we should improve
                     — your voice shapes our future.
                </p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <i class="bx <?= $messageType === 'success' ? 'bx-check-circle' : 'bx-error-circle' ?>"></i>
                <span><?= $message ?></span>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             STATS
        ====================================================== -->
        <?php if ($stats['total'] > 0): ?>
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-label">Total Sent</span>
                    <span class="stat-value gold"><?= $stats['total'] ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">In Review</span>
                    <span class="stat-value yellow"><?= $stats['in_review'] ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Resolved</span>
                    <span class="stat-value green"><?= $stats['resolved'] ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- ======================================================
             FEEDBACK FORM
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-edit"></i> Send a Message</h2>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" class="form-card">

            <!-- Category -->
            <div class="field">
                <label><i class="bx bx-category"></i> What type of message is this? *</label>
                <div class="category-grid">
                    <label class="category-option">
                        <input type="radio" name="category" value="suggestion" checked>
                        <div class="cat-box">
                            <i class="bx bx-bulb"></i>
                            <span>Suggestion</span>
                        </div>
                    </label>
                    <label class="category-option">
                        <input type="radio" name="category" value="complaint">
                        <div class="cat-box">
                            <i class="bx bx-error-circle"></i>
                            <span>Complaint</span>
                        </div>
                    </label>
                    <label class="category-option">
                        <input type="radio" name="category" value="other">
                        <div class="cat-box">
                            <i class="bx bx-dots-horizontal-rounded"></i>
                            <span>Other</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Priority -->
            <div class="field">
                <label><i class="bx bx-flag"></i> Priority Level</label>
                <div class="priority-row">
                    <label class="priority-option medium">
                        <input type="radio" name="priority" value="medium" checked>
                        <div class="prio-box"><i class="bx bx-minus"></i> Medium</div>
                    </label>
                    <label class="priority-option urgent">
                        <input type="radio" name="priority" value="urgent">
                        <div class="prio-box"><i class="bx bx-error"></i> Urgent</div>
                    </label>
                </div>
            </div>

            <!-- Subject -->
            <div class="field">
                <label><i class="bx bx-purchase-tag"></i> Subject *</label>
                <div class="input-wrap">
                    <i class="bx bx-purchase-tag"></i>
                    <input type="text" name="subject"
                           placeholder="e.g. Add a mobile app for easier access"
                           maxlength="200" required>
                </div>
            </div>

            <!-- Message -->
            <div class="field">
                <label><i class="bx bx-message-detail"></i> Your Message *</label>
                <div class="input-wrap">
                    <i class="bx bx-edit"></i>
                    <textarea name="message"
                              placeholder="Tell us exactly what's on your mind. Be as detailed as you like — we read every message..."
                              maxlength="5000" required></textarea>
                </div>
            </div>

            <!-- Anonymous toggle -->
            <div class="field">
                <label class="toggle-wrapper">
                    <input type="checkbox" name="is_anonymous" value="1">
                    <span class="toggle-text">
                        Send <strong>anonymously</strong> — admin won't see your name or email
                    </span>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit" name="submit_feedback" class="btn-submit">
                <i class="bx bx-send"></i> Send to Admin
            </button>

        </form>

        <!-- ======================================================
             FEEDBACK HISTORY
        ====================================================== -->
        <div class="section-head">
            <div class="accent"></div>
            <h2><i class="bx bx-history"></i> Your Message History</h2>
        </div>

        <?php if (!empty($myFeedback)): ?>
            <div class="feedback-list">
                <?php foreach ($myFeedback as $fb): ?>
                    <div class="feedback-item cat-<?= htmlspecialchars($fb['category']) ?>"
                         data-id="<?= (int) $fb['id'] ?>"
                         data-category="<?= htmlspecialchars($fb['category'], ENT_QUOTES) ?>"
                         data-priority="<?= htmlspecialchars($fb['priority'], ENT_QUOTES) ?>"
                         data-subject="<?= htmlspecialchars($fb['subject'], ENT_QUOTES) ?>"
                         data-message="<?= htmlspecialchars($fb['message'], ENT_QUOTES) ?>"
                         data-anonymous="<?= (int) $fb['is_anonymous'] ?>">

                        <div class="feedback-header">
                            <h4><?= htmlspecialchars($fb['subject']) ?></h4>
                            <span class="status-badge <?= htmlspecialchars($fb['status']) ?>">
                                <?= str_replace('_', ' ', htmlspecialchars($fb['status'])) ?>
                            </span>
                        </div>

                        <div class="feedback-meta">
                            <span class="meta-pill pill-category">
                                <i class="bx bx-category"></i> <?= htmlspecialchars(ucfirst($fb['category'])) ?>
                            </span>
                            <span class="meta-pill pill-priority <?= htmlspecialchars($fb['priority']) ?>">
                                <i class="bx bx-flag"></i> <?= htmlspecialchars(ucfirst($fb['priority'])) ?>
                            </span>
                            <?php if ($fb['is_anonymous']): ?>
                                <span class="meta-pill pill-anon">
                                    <i class="bx bx-hide"></i> Anonymous
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="feedback-body"><?= nl2br(htmlspecialchars($fb['message'])) ?></div>

                        <?php if (!empty($fb['attachment'])): ?>
                            <a href="<?= htmlspecialchars($fb['attachment']) ?>" target="_blank" class="attachment-link">
                                <i class="bx bx-paperclip"></i> View Attachment
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($fb['admin_reply'])): ?>
                            <div class="admin-reply">
                                <div class="reply-label">
                                    <i class="bx bx-shield-quarter"></i> Admin Reply
                                    <?php if (!empty($fb['replied_by'])): ?>
                                        — <?= htmlspecialchars($fb['replied_by']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($fb['replied_at'])): ?>
                                        (<?= date('d M Y, H:i', strtotime($fb['replied_at'])) ?>)
                                    <?php endif; ?>
                                </div>
                                <div class="reply-text"><?= nl2br(htmlspecialchars($fb['admin_reply'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="feedback-footer">
                            <span>
                                <i class="bx bx-time-five"></i>
                                <?= date('d M Y, H:i', strtotime($fb['created_at'])) ?>
                            </span>
                            <span>Ref #<?= str_pad($fb['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </div>

                        <!-- EDIT / DELETE ACTIONS -->
                        <div class="feedback-actions">
                            <button type="button" class="btn-action btn-edit js-edit">
                                <i class="bx bx-edit-alt"></i> Edit
                            </button>

                            <button type="button" class="btn-action btn-delete js-delete">
                                <i class="bx bx-trash"></i> Delete
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bx bx-message-square-dots"></i>
                <strong>No messages yet</strong>
                <p>Your first message will appear here once you submit the form above.</p>
            </div>
        <?php endif; ?>

    </main>

    <!-- ======================================================
         EDIT MODAL
    ====================================================== -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="bx bx-edit-alt"></i> Edit Message</h3>
                <button type="button" class="modal-close" onclick="closeEditModal()">
                    <i class="bx bx-x"></i>
                </button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="feedback_id" id="edit_feedback_id">

                <!-- Category -->
                <div class="field">
                    <label><i class="bx bx-category"></i> Category</label>
                    <div class="category-grid">
                        <label class="category-option">
                            <input type="radio" name="category" value="suggestion">
                            <div class="cat-box">
                                <i class="bx bx-bulb"></i>
                                <span>Suggestion</span>
                            </div>
                        </label>
                        <label class="category-option">
                            <input type="radio" name="category" value="complaint">
                            <div class="cat-box">
                                <i class="bx bx-error-circle"></i>
                                <span>Complaint</span>
                            </div>
                        </label>
                        <label class="category-option">
                            <input type="radio" name="category" value="other">
                            <div class="cat-box">
                                <i class="bx bx-dots-horizontal-rounded"></i>
                                <span>Other</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Priority -->
                <div class="field">
                    <label><i class="bx bx-flag"></i> Priority Level</label>
                    <div class="priority-row">
                        <label class="priority-option low">
                            <input type="radio" name="priority" value="low">
                            <div class="prio-box"><i class="bx bx-down-arrow-alt"></i> Low</div>
                        </label>
                        <label class="priority-option medium">
                            <input type="radio" name="priority" value="medium">
                            <div class="prio-box"><i class="bx bx-minus"></i> Medium</div>
                        </label>
                        <label class="priority-option high">
                            <input type="radio" name="priority" value="high">
                            <div class="prio-box"><i class="bx bx-up-arrow-alt"></i> High</div>
                        </label>
                        <label class="priority-option urgent">
                            <input type="radio" name="priority" value="urgent">
                            <div class="prio-box"><i class="bx bx-error"></i> Urgent</div>
                        </label>
                    </div>
                </div>

                <!-- Subject -->
                <div class="field">
                    <label><i class="bx bx-purchase-tag"></i> Subject *</label>
                    <div class="input-wrap">
                        <i class="bx bx-purchase-tag"></i>
                        <input type="text" name="subject" id="edit_subject" maxlength="200" required>
                    </div>
                </div>

                <!-- Message -->
                <div class="field">
                    <label><i class="bx bx-message-detail"></i> Your Message *</label>
                    <div class="input-wrap">
                        <i class="bx bx-edit"></i>
                        <textarea name="message" id="edit_message" maxlength="5000" required></textarea>
                    </div>
                </div>

                <!-- Optional new attachment -->
                <div class="field">
                    <label><i class="bx bx-paperclip"></i> Replace Attachment (optional)</label>
                    <label class="file-upload-wrapper">
                        <i class="bx bx-cloud-upload"></i>
                        <p>Click to upload a new file</p>
                        <p class="hint">PDF, JPG, PNG, GIF, WEBP, DOC, DOCX, TXT — max 5MB</p>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.txt">
                    </label>
                </div>

                <!-- Anonymous toggle -->
                <div class="field">
                    <label class="toggle-wrapper">
                        <input type="checkbox" name="is_anonymous" id="edit_is_anonymous" value="1">
                        <span class="toggle-text">
                            Send <strong>anonymously</strong> — admin won't see your name or email
                        </span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="modal-btn cancel" onclick="closeEditModal()">
                        <i class="bx bx-x"></i> Cancel
                    </button>
                    <button type="submit" name="edit_feedback" class="modal-btn save">
                        <i class="bx bx-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================
         DELETE CONFIRM MODAL
    ====================================================== -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box confirm-box">
            <div class="confirm-icon">
                <i class="bx bx-trash"></i>
            </div>
            <h3>Delete this message?</h3>
            <p id="deleteConfirmText">This action cannot be undone. The message will be permanently removed.</p>

            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="feedback_id" id="delete_feedback_id">
                <div class="confirm-actions">
                    <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                        <i class="bx bx-x"></i> Keep
                    </button>
                    <button type="submit" name="delete_feedback" class="modal-btn danger">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // =====================================================
        // FILE NAME DISPLAY (main form)
        // =====================================================
        (function () {
            const attachmentInput = document.getElementById('attachmentInput');
            const fileNameDisplay = document.getElementById('fileName');

            if (attachmentInput && fileNameDisplay) {
                attachmentInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const f = this.files[0];
                        const sizeMB = (f.size / 1024 / 1024).toFixed(2);
                        fileNameDisplay.innerHTML = '<i class="bx bx-paperclip"></i> ' + f.name + ' (' + sizeMB + ' MB)';
                    } else {
                        fileNameDisplay.innerHTML = '';
                    }
                });
            }
        })();

        // =====================================================
        // CHARACTER COUNTER FOR TEXTAREA
        // =====================================================
        (function () {
            const textarea = document.querySelector('textarea[name="message"]');
            if (!textarea) return;

            const counter = document.createElement('div');
            counter.style.cssText = 'text-align:right;font-size:0.78rem;color:rgba(255,255,255,0.5);margin-top:8px;font-weight:600;letter-spacing:0.5px;';
            counter.textContent = '0 / 5000 characters';
            textarea.parentNode.parentNode.appendChild(counter);

            textarea.addEventListener('input', function () {
                counter.textContent = textarea.value.length + ' / 5000 characters';
                if (textarea.value.length > 4900) {
                    counter.style.color = '#e74c3c';
                } else if (textarea.value.length > 4500) {
                    counter.style.color = '#f1c40f';
                } else {
                    counter.style.color = 'rgba(255,255,255,0.5)';
                }
            });
        })();

        // =====================================================
        // MAIN FORM VALIDATION
        // =====================================================
        (function () {
            const form = document.querySelector('form.form-card');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                const subject = form.querySelector('input[name="subject"]').value.trim();
                const msg = form.querySelector('textarea[name="message"]').value.trim();

                if (subject.length < 3) {
                    e.preventDefault();
                    alert('Please enter a subject of at least 3 characters.');
                    return;
                }
                if (msg.length < 10) {
                    e.preventDefault();
                    alert('Please write a message of at least 10 characters.');
                    return;
                }
            });
        })();

        // =====================================================
        // EDIT MODAL OPEN / CLOSE
        // =====================================================
        function openEditModal(data) {
            const modal = document.getElementById('editModal');

            document.getElementById('edit_feedback_id').value = data.id;
            document.getElementById('edit_subject').value = data.subject;
            document.getElementById('edit_message').value = data.message;

            document.querySelectorAll('#editForm input[name="category"]').forEach(function (r) {
                r.checked = (r.value === data.category);
            });

            document.querySelectorAll('#editForm input[name="priority"]').forEach(function (r) {
                r.checked = (r.value === data.priority);
            });

            document.getElementById('edit_is_anonymous').checked = !!data.is_anonymous;

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        // =====================================================
        // DELETE MODAL OPEN / CLOSE
        // =====================================================
        function openDeleteModal(id, subject) {
            document.getElementById('delete_feedback_id').value = id;
            document.getElementById('deleteConfirmText').textContent =
                'This will permanently delete "' + subject + '". This action cannot be undone.';

            document.getElementById('deleteModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        // =====================================================
        // WIRE UP EDIT / DELETE BUTTONS VIA DATA ATTRIBUTES
        // =====================================================
        (function () {
            document.querySelectorAll('.feedback-item').forEach(function (item) {
                const editBtn   = item.querySelector('.js-edit');
                const deleteBtn = item.querySelector('.js-delete');

                if (editBtn) {
                    editBtn.addEventListener('click', function () {
                        openEditModal({
                            id:           item.dataset.id,
                            category:     item.dataset.category,
                            priority:     item.dataset.priority,
                            subject:      item.dataset.subject,
                            message:      item.dataset.message,
                            is_anonymous: item.dataset.anonymous === '1'
                        });
                    });
                }

                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        openDeleteModal(item.dataset.id, item.dataset.subject);
                    });
                }
            });
        })();

        // =====================================================
        // CLOSE MODALS ON ESC + OVERLAY CLICK
        // =====================================================
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeEditModal();
                closeDeleteModal();
            }
        });

        document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeEditModal();
                    closeDeleteModal();
                }
            });
        });

        // =====================================================
        // EDIT FORM VALIDATION
        // =====================================================
        (function () {
            const editForm = document.getElementById('editForm');
            if (!editForm) return;

            editForm.addEventListener('submit', function (e) {
                const subject = document.getElementById('edit_subject').value.trim();
                const msg = document.getElementById('edit_message').value.trim();

                if (subject.length < 3) {
                    e.preventDefault();
                    alert('Please enter a subject of at least 3 characters.');
                    return;
                }
                if (msg.length < 10) {
                    e.preventDefault();
                    alert('Please write a message of at least 10 characters.');
                    return;
                }
            });
        })();

        console.log('✅ Contact Admin page loaded for <?= htmlspecialchars($userName, ENT_QUOTES) ?>');
    </script>

</body>

</html>