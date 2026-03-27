<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* ---------- ADMIN CHECK ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied");
}



/* ---------- DB CONNECTION ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli("localhost", "skdneoaa", "t3YnVb0HN**40f", "skdneoaa_Felhasznalok");
    $conn->set_charset("utf8mb4");

    // Inline Migration: ensure updated_at exists
    $check_upd = $conn->query("SHOW COLUMNS FROM reports LIKE 'updated_at'");
    if ($check_upd && $check_upd->num_rows == 0) {
        $conn->query("ALTER TABLE reports ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $conn->query("UPDATE reports SET updated_at = created_at WHERE updated_at IS NOT NULL");
    }
} catch (Exception $e) {
    http_response_code(500);
    die("Database connection error: " . $e->getMessage());
}

/* ---------- AJAX: FETCH USERS ---------- */
if (isset($_GET['ajax'])) {
    while (ob_get_level()) ob_end_clean();
    ob_start();
    header("Content-Type: application/json");
    try {
        $search = $_GET['search'] ?? '';
        $searchTerm = "%$search%";

        $stmt = $conn->prepare("
            SELECT user_id, username, role, is_banned, profile_img
            FROM users
            WHERE username LIKE ?
            ORDER BY username
            LIMIT 50
        ");
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();

        $users = $res->fetch_all(MYSQLI_ASSOC);
        $out = json_encode($users, JSON_INVALID_UTF8_SUBSTITUTE);
        while (ob_get_level()) ob_end_clean();
        echo $out;
    } catch (Exception $e) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

/* ---------- AJAX: FETCH REPORTS ---------- */
if (isset($_GET['ajax_reports'])) {
    while (ob_get_level()) ob_end_clean(); 
    ob_start();
    header("Content-Type: application/json");
    header("Cache-Control: no-store");
    try {
        $last_upd = $_GET['last_updated'] ?? '2000-01-01 00:00:00';
        $is_initial = ($last_upd === '2000-01-01 00:00:00');

        // Robust column detection
        $col_check = $conn->query("SHOW COLUMNS FROM reports LIKE 'updated_at'");
        $sort_col = ($col_check && $col_check->num_rows > 0) ? 'updated_at' : 'created_at';

        if ($is_initial) {
            $stmt = $conn->prepare("
                SELECT r.id, u.username, u.profile_img, r.headline, r.report, r.reply, r.created_at, 
                       (CASE WHEN '$sort_col'='updated_at' THEN r.updated_at ELSE r.created_at END) as fetched_ts,
                       r.status, g.title AS game_title, g.main_image AS game_thumb
                FROM reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN games g ON r.game_id = g.game_id
                ORDER BY $sort_col DESC
                LIMIT 50
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT r.id, u.username, u.profile_img, r.headline, r.report, r.reply, r.created_at,
                       (CASE WHEN '$sort_col'='updated_at' THEN r.updated_at ELSE r.created_at END) as fetched_ts,
                       r.status, g.title AS game_title, g.main_image AS game_thumb
                FROM reports r
                LEFT JOIN users u ON r.user_id = u.user_id
                LEFT JOIN games g ON r.game_id = g.game_id
                WHERE $sort_col > ?
                ORDER BY $sort_col ASC
                LIMIT 100
            ");
            $stmt->bind_param("s", $last_upd);
        }

        $stmt->execute();
        $reports_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Map fetched_ts to updated_at for JS consistency if needed
        $reports = array_map(function ($r) {
            $r['updated_at'] = $r['fetched_ts'];
            return $r;
        }, $reports_raw);

        $next_ts = $last_upd;
        foreach ($reports as $r) {
            if ($r['updated_at'] > $next_ts)
                $next_ts = $r['updated_at'];
        }

        $res_count = $conn->query("SELECT COUNT(*) as c FROM reports WHERE status = 'pending'");
        $pending_count = $res_count->fetch_assoc()['c'] ?? 0;

        $out = json_encode([
            'reports' => $reports, 
            'ts' => $next_ts, 
            'pending' => $pending_count,
            'debug' => ['used_column' => $sort_col]
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        while (ob_get_level()) ob_end_clean();
        echo $out;
    } catch (Exception $e) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

/* ---------- AJAX: USER ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Admin Reply Action
    if ($action === 'admin_reply') {
        if (isset($_POST['report_id'], $_POST['reply'])) {
            $rid = (int) $_POST['report_id'];
            $reply = trim($_POST['reply']);
            try {
                $stmt = $conn->prepare("UPDATE reports SET reply = ?, status = 'answered' WHERE id = ?");
                $stmt->bind_param("si", $reply, $rid);
                $stmt->execute();
                echo "ok";
            } catch (Exception $e) {
                http_response_code(500);
                echo "Reply failed";
            }
        }
        exit;
    }

    if (!isset($_POST['uid']))
        exit;
    $uid = (int) $_POST['uid'];

    try {
        if ($action === 'ban') {
            $stmt = $conn->prepare("UPDATE users SET is_banned=1 WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            // Secure DELETEs
            $stmt = $conn->prepare("DELETE FROM comments WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM games WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id=? OR receiver_id=?");
            $stmt->bind_param("ii", $uid, $uid);
            $stmt->execute();
        } elseif ($action === 'unban') {
            $stmt = $conn->prepare("UPDATE users SET is_banned=0 WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } elseif ($action === 'admin') {
            $stmt = $conn->prepare("UPDATE users SET role='admin' WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } elseif ($action === 'user') {
            $stmt = $conn->prepare("UPDATE users SET role='user' WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } elseif ($action === 'delete_comments') {
            $stmt = $conn->prepare("DELETE FROM comments WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        }
        echo "ok";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Action failed";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">

    <style>
        /* Themes */
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
            --poppins: 'Poppins', sans-serif;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f1c40f;
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191, 50, 241, 0.15);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --box-bg: rgba(191, 50, 241, 0.08);
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-text: #fff;
            --table-hover: rgba(191, 50, 241, 0.1);
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.2);
            --glass: rgba(247, 243, 232, 0.8);
            --glass-strong: rgba(255, 255, 255, 0.95);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --box-bg: rgba(155, 89, 182, 0.06);
            --input-bg: rgba(0, 0, 0, 0.04);
            --input-text: #333;
            --table-hover: rgba(155, 89, 182, 0.08);
        }

        body {
            font-family: var(--poppins);
            margin: 0;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            min-height: 100vh;
            background-attachment: fixed;
            transition: color 0.5s ease;
            overflow-x: hidden;
        }

        /* Bg Animation */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at 0% 0%, var(--bg-mesh-2) 0%, transparent 50%),
                radial-gradient(circle at 100% 0%, var(--bg-mesh-3) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, var(--bg-mesh-2) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, var(--bg-mesh-3) 0%, transparent 50%),
                var(--bg-mesh-1);
            background-size: 200% 200%;
            animation: meshFlow 20s ease infinite;
        }

        @keyframes meshFlow {
            0% {
                background-position: 0% 0%;
            }

            50% {
                background-position: 100% 100%;
            }

            100% {
                background-position: 0% 0%;
            }
        }

        /* ---------- LAYOUT ---------- */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--glass);
            backdrop-filter: blur(30px);
            padding: 20px 40px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: slideDown 0.8s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-title {
            font-family: var(--orbitron);
            font-size: 1.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: var(--glow);
        }

        /* Search Bar */
        .search-wrapper {
            position: relative;
        }

        .search-input {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            padding: 12px 20px 12px 45px;
            border-radius: 12px;
            color: var(--text-main);
            font-family: var(--poppins);
            width: 250px;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            width: 350px;
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        /*COSTUMER SERVICE TABLE*/
        .cs-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }

        .cs-btn:hover {
            background: #005fcc;
        }

        .cs-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-btn {
            background: transparent;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: var(--text-main);
        }

        .cs-panel-header h2 {
            font-family: var(--orbitron);
            font-size: 1.2rem;
            color: var(--accent);
            margin: 0;
        }

        .cs-table-msg {
            text-align: center;
            padding: 20px;
            opacity: 0.6;
            font-size: 0.9rem;
        }

        /* Badge on CS Button */
        #customerServiceBtn {
            position: relative;
        }

        .cs-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            border: 2px solid var(--bg-mesh-1);
            display: none;
        }

        .cs-badge.visible {
            display: block;
        }

        .cs-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .filter-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .filter-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .canned-responses {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }

        .canned-btn {
            background: rgba(191, 50, 241, 0.1);
            border: 1px solid rgba(191, 50, 241, 0.3);
            color: var(--accent);
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.2s;
        }

        .canned-btn:hover {
            background: var(--accent);
            color: white;
        }

        /* Dark overlay behind the panel */
        .cs-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.35);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 9998;
        }

        .cs-overlay.visible {
            opacity: 1;
            pointer-events: all;
        }

        /* Sliding panel */
        .cs-panel {
            position: fixed;
            top: 0;
            right: -520px;
            width: 420px;
            height: 100vh;
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            box-shadow: -6px 0 18px rgba(0, 0, 0, 0.2);
            padding: 20px;
            transition: right 0.45s cubic-bezier(0.25, 0.1, 0.25, 1);
            z-index: 9999;
            overflow-y: auto;
            border-radius: 12px 0 0 12px;
        }

        .cs-panel.open {
            right: 0;
        }

        /* Panel Content Styling */
        .report-item-admin {
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            padding: 20px;
            border-radius: 15px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .report-item-admin:hover {
            background: rgba(0, 0, 0, 0.05);
            transform: translateX(-5px);
        }

        body.dark .report-item-admin {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }

        body.dark .report-item-admin:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .report-item-admin .user {
            font-weight: 700;
            color: var(--accent);
            font-size: 0.9rem;
        }

        .report-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .report-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }

        .report-item-admin .headline {
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: block;
        }

        .report-item-admin .full-msg {
            display: none;
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--text-main);
            padding: 10px;
            background: var(--box-bg);
            border-radius: 5px;
        }

        .report-item-admin .reply-area {
            margin-top: 15px;
            display: block;
        }

        .report-item-admin .reply-area textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-family: var(--poppins);
            font-size: 0.85rem;
            background: var(--input-bg);
            color: var(--input-text);
        }

        .report-item-admin .reply-area button {
            margin-top: 8px;
            padding: 5px 15px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(241, 194, 15, 0.2);
            color: #f1c40f;
        }

        .status-answered {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .report-item-admin.new-item {
            animation: slideInLeft 0.5s ease-out;
        }

        .report-game-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 10px 0;
            padding: 10px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            border-left: 3px solid var(--accent);
        }

        body.bright .report-game-info {
            background: rgba(0, 0, 0, 0.04);
        }

        .report-game-thumb {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }

        .report-game-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ---------- TABLE ---------- */
        .table-container {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            overflow: hidden;
            /* Rounded corners for table */
            animation: fadeIn 1s ease 0.2s backwards;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: rgba(0, 0, 0, 0.2);
            padding: 20px;
            font-family: var(--orbitron);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--table-hover);
        }

        /* User Info */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .username {
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-admin {
            background: rgba(191, 50, 241, 0.2);
            color: var(--accent);
            border: 1px solid var(--accent);
        }

        .badge-user {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .status-active {
            color: var(--success);
        }

        .status-banned {
            color: var(--danger);
            font-weight: bold;
        }

        /* Actions */
        .action-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
            margin-right: 6px;
            font-family: var(--poppins);
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-danger {
            color: var(--danger);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            background: rgba(231, 76, 60, 0.1);
            border-color: var(--danger);
        }

        .btn-success {
            color: var(--success);
            border-color: rgba(46, 204, 113, 0.3);
        }

        .btn-success:hover {
            background: rgba(46, 204, 113, 0.1);
            border-color: var(--success);
        }

        /* Back Button logic */
        .back-nav {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-main);
            gap: 8px;
            font-weight: 600;
            transition: 0.3s;
        }

        .back-nav:hover {
            color: var(--accent);
            transform: translateX(-5px);
        }

        /* TOAST NOTIFICATION */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: var(--glass-strong);
            backdrop-filter: blur(15px);
            padding: 15px 25px;
            border-radius: 12px;
            border-left: 5px solid var(--accent);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            color: var(--text-main);
            font-family: var(--poppins);
            min-width: 250px;
            animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Theme Toggle */
        /*.theme-toggle-btn {*/
        /*    position: fixed;*/
        /*    bottom: 30px;*/
        /*    left: 30px;*/
        /*    width: 50px;*/
        /*    height: 50px;*/
        /*    border-radius: 50%;*/
        /*    background: var(--glass);*/
        /*    display: flex;*/
        /*    align-items: center;*/
        /*    justify-content: center;*/
        /*    cursor: pointer;*/
        /*    border: 1px solid var(--border-color);*/
        /*    box-shadow: var(--shadow);*/
        /*    z-index: 1000;*/
        /*    transition: 0.3s;*/
        /*}*/

        /*.theme-toggle-btn:hover {*/
        /*    transform: scale(1.1) rotate(10deg);*/
        /*    border-color: var(--accent);*/
        /*}*/
    </style>
</head>

<body>

    <!-- TOAST CONTAINER -->
    <div id="toast-container" class="toast-container"></div>

    <!-- THEME TOGGLE -->
    <div id="themeToggle" class="theme-toggle-btn"
        style="position: fixed; top: 20px; right: 20px; cursor: pointer; z-index: 10001; background: var(--glass-strong); padding: 5px; border-radius: 12px; border: 1px solid var(--border-color); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
        <img src="imgandgifs/sun.svg" alt="Theme" style="width: 30px; height: 30px;">
    </div>

    <div class="container">
        <!-- HEADER -->
        <header class="admin-header">
            <div class="header-left">
                <a href="profile.php" class="back-nav"
                    style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
                    <img src="imgandgifs/arrow-left-circle.svg" alt="Back" style="width: 22px; height: 22px;">
                    <span>Back to Profile</span>
                </a>

                <div class="admin-title">Admin Panel</div>
            </div>

            <div class="search-wrapper">
                <input type="text" id="searchInput" class="search-input" placeholder="Search user...">
            </div>
            <button id="customerServiceBtn" class="cs-btn">
                Customer Service
                <span id="csCountBadge" class="cs-badge">0</span>
            </button>
        </header>

        <div id="customerServicePanel" class="cs-panel">
            <div class="cs-panel-header">
                <h2>User Replies & Reports</h2>
                <button id="closePanelBtn" class="close-btn">✕</button>
            </div>

            <div class="cs-filters">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="pending">Pending</button>
            </div>

            <div id="reportsContainer">
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <!-- JS Injects Rows Here -->
                </tbody>
            </table>
        </div>
    </div>

    <div id="csOverlay" class="cs-overlay"></div>

    <script>
        const tableBody = document.getElementById("users-table-body");
        const searchInput = document.getElementById("searchInput");
        const currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;

        /* ---------- UTILS ---------- */
        function escapeHtml(text) {
            if (!text) return "";
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerText = message;

            // Animation out
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.4s forwards';
                setTimeout(() => toast.remove(), 400);
            }, 3000);

            container.appendChild(toast);
        }

        /* ---------- LOAD USERS ---------- */
        async function loadUsers() {
            if (document.hidden) return;

            try {
                const res = await fetch("admin.php?ajax=1&search=" + encodeURIComponent(searchInput.value));
                if (!res.ok) throw new Error("Network error");

                const users = await res.json();
                const fragment = document.createDocumentFragment();

                if (users.length === 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="4" style="text-align:center; padding: 40px; color: rgba(255,255,255,0.5);">No users found.</td>`;
                    fragment.appendChild(tr);
                } else {
                    users.forEach(u => {
                        const tr = document.createElement("tr");

                        // Role Badge
                        const roleBadge = u.role === 'admin'
                            ? `<span class="badge badge-admin">Admin</span>`
                            : `<span class="badge badge-user">User</span>`;

                        // Status Text
                        const statusText = u.is_banned
                            ? `<span class="status-banned">Banned</span>`
                            : `<span class="status-active">Active</span>`;

                        // Avatar
                        const avatar = u.profile_img || "/imgandgifs/login.png";

                        // Actions logic
                        let actionsHtml = '';
                        if (u.user_id != currentUserId) {
                            const banBtn = u.is_banned
                                ? `<button class="action-btn btn-success" onclick="doAction('unban', ${u.user_id})">Unban</button>`
                                : `<button class="action-btn btn-danger" onclick="doAction('ban', ${u.user_id})">Ban</button>`;

                            const roleBtn = u.role === 'admin'
                                ? `<button class="action-btn" onclick="doAction('user', ${u.user_id})">Demote</button>`
                                : `<button class="action-btn" onclick="doAction('admin', ${u.user_id})">Promote</button>`;

                            const delBtn = `<button class="action-btn btn-danger" onclick="if(confirm('Delete user comments?')) doAction('delete_comments', ${u.user_id})">Clear Comments</button>`;

                            actionsHtml = `${banBtn} ${roleBtn} ${delBtn}`;
                        } else {
                            actionsHtml = `<span style="opacity:0.5; font-size:0.8rem;">(You)</span>`;
                        }

                        tr.innerHTML = `
                            <td>
                                <div class="user-cell">
                                    <img src="${avatar}" class="user-avatar" alt="User">
                                    <span class="username">${escapeHtml(u.username)}</span>
                                </div>
                            </td>
                            <td>${roleBadge}</td>
                            <td>${statusText}</td>
                            <td>${actionsHtml}</td>
                        `;
                        fragment.appendChild(tr);
                    });
                }

                tableBody.innerHTML = "";
                tableBody.appendChild(fragment);

            } catch (e) {
                console.error("Load users failed", e);
                tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color: var(--danger);">Error: ${e.message}</td></tr>`;
            }
        }

        /* ---------- ACTION ---------- */
        async function doAction(action, uid) {
            try {
                const fd = new FormData();
                fd.append("action", action);
                fd.append("uid", uid);

                const res = await fetch("admin.php", { method: "POST", body: fd });
                const txt = await res.text();

                if (res.ok) {
                    showToast("Action successful!", "success");
                    loadUsers();
                } else {
                    showToast("Action failed.", "error");
                }
            } catch (e) {
                showToast("Connection error.", "error");
            }
        }

        /* ---------- AUTO UPDATE ---------- */
        setInterval(loadUsers, 5000); // Poll slower
        searchInput.oninput = debounce(loadUsers, 300);
        loadUsers();

        /* ---------- THEME LOGIC ---------- */
        const themeBtn = document.getElementById('themeToggle');
        const bodyValue = document.body;

        function updateThemeUI() {
            const isDark = bodyValue.classList.contains('dark');
            const img = themeBtn.querySelector('img');
            if (img) {
                img.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";
                img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.1)';
                img.style.filter = isDark ? 'drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))' : 'drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))';
            }
        }

        function applyTheme() {
            const t = localStorage.getItem('theme') || 'dark';
            bodyValue.classList.remove('dark', 'bright');
            bodyValue.classList.add(t);
            updateThemeUI();
        }

        themeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isDark = bodyValue.classList.contains('dark');
            const newTheme = isDark ? 'bright' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme();
        });

        applyTheme();

        /* ---------- CUSTOMER SERVICE PANEL ---------- */
        const csBtn = document.getElementById("customerServiceBtn");
        const csPanel = document.getElementById("customerServicePanel");
        const closeBtn = document.getElementById("closePanelBtn");
        const csOverlay = document.getElementById("csOverlay");
        const csCountBadge = document.getElementById("csCountBadge");

        let reportLastTs = '2000-01-01 00:00:00';
        let badgeLastTs = '2000-01-01 00:00:00';
        let isLoadingReports = false;
        let hasInitiallyLoaded = false;
        let currentReportFilter = 'all'; // 'all' or 'pending'

        // Canned responses definitions
        const CANNED_RESPONSES = [
            "Thank you for reporting. This is now under investigation.",
            "We have fixed the issue. Thank you for your patience!",
            "We couldn't reproduce this issue. Could you provide more details?",
            "Action has been taken against the reported content/user."
        ];

        async function loadReports() {
            const container = document.getElementById("reportsContainer");
            if (!container) return;
            
            if (isLoadingReports) return;
            isLoadingReports = true;

            try {
                const url = `admin.php?ajax_reports=1&last_updated=${encodeURIComponent(reportLastTs)}`;
                const res = await fetch(url);
                if (!res.ok) {
                    const errorText = await res.text();
                    console.error("Fetch reports failed:", errorText);
                    container.innerHTML = `<div class="cs-table-msg" style="color:var(--danger)">Error: Failed to load tickets (${res.status}). <br><small>${errorText.substring(0, 200)}</small></div>`;
                    return;
                }
                const data = await res.json();
                console.log("Report Data Fetched:", data);
                if (data.error) {
                    console.error("Backend Error:", data.error);
                    container.innerHTML = `<div class="cs-table-msg" style="color:var(--danger)">Error: ${data.error}</div>`;
                    return;
                }
                const reports = data.reports || [];
                
                // If this was our first fetch, we should reverse the results (DESC -> ASC for insertion)
                const isInitial = (reportLastTs === '2000-01-01 00:00:00');
                if (data.ts) reportLastTs = data.ts;

                // Update badge
                if (data.pending > 0) {
                    csCountBadge.innerText = data.pending;
                    csCountBadge.classList.add('visible');
                } else {
                    csCountBadge.classList.remove('visible');
                }

                if (reports.length === 0 && !container.querySelector('.report-item-admin')) {
                    container.innerHTML = '<div class="cs-table-msg">No support tickets found.</div>';
                    return;
                }

                const sortedReports = isInitial ? [...reports].reverse() : reports;
                
                // Remove loading placeholder if exists
                const placeholder = container.querySelector('.cs-table-msg');
                if (placeholder && reports.length > 0) placeholder.remove();


                sortedReports.forEach(r => {
                    const existingNode = container.querySelector(`.report-item-admin[data-id="${r.id}"]`);

                    const isAnsweredHtml = r.reply
                        ? '<span class="status-badge status-answered">Answered</span>'
                        : '<span class="status-badge status-pending">Pending</span>';

                    if (existingNode) {
                        // Patch status and reply if changed
                        const badge = existingNode.querySelector('.status-badge');
                        if (badge) {
                            badge.className = `status-badge status-${r.status}`;
                            badge.innerText = r.status.charAt(0).toUpperCase() + r.status.slice(1);
                        }
                        const textarea = existingNode.querySelector('textarea');
                        if (textarea && r.reply && textarea.value !== r.reply) {
                            textarea.value = r.reply;
                        }

                        // Handle filtering visibility
                        updateFilterVisibility(existingNode, r.status);
                        return;
                    }

                    // Build new card
                    const avatar = r.profile_img || '/imgandgifs/login.png';
                    let gameHtml = '';
                    if (r.game_title) {
                        const thumb = r.game_thumb || 'imgandgifs/default_game.jpg';
                        gameHtml = `<div class="report-game-info">
                            <img src="${thumb}" class="report-game-thumb" alt="">
                            <span class="report-game-title">Reported Game: ${escapeHtml(r.game_title)}</span>
                        </div>`;
                    }

                    const el = document.createElement('div');
                    el.className = 'report-item-admin new-item';
                    el.setAttribute('data-id', r.id);
                    el.setAttribute('data-status', r.status);

                    // Canned responses HTML
                    const cannedHtml = CANNED_RESPONSES.map(txt =>
                        `<button class="canned-btn" onclick="applyCanned(${r.id}, '${txt.replace(/'/g, "\\'")}')">${txt.split(' ')[0]}...</button>`
                    ).join('');

                    el.innerHTML = `
                        <div class="report-user-info">
                            <img src="${avatar}" class="report-avatar" alt="">
                            <span class="user">${escapeHtml(r.username)} ${isAnsweredHtml}</span>
                        </div>
                        <span class="headline" onclick="toggleReport(${r.id})">${escapeHtml(r.headline || 'No Headline')}</span>
                        <div id="msg-${r.id}" class="full-msg" style="display:none;">
                            ${gameHtml}
                            <p>${escapeHtml(r.report || '').replace(/\n/g, '<br>')}</p>
                            <hr style="opacity:0.1;margin:10px 0;">
                            <div class="reply-area">
                                <textarea id="reply-text-${r.id}" placeholder="Write a reply...">${r.reply ? escapeHtml(r.reply) : ''}</textarea>
                                <div class="canned-responses">${cannedHtml}</div>
                                <button onclick="sendReply(${r.id})">Send Reply</button>
                            </div>
                        </div>`;

                    // Insert at top for newest-first ordering
                    if (container.firstChild) container.insertBefore(el, container.firstChild);
                    else container.appendChild(el);

                    updateFilterVisibility(el, r.status);
                });

            } catch (e) {
                console.error('Load reports failed', e);
                container.innerHTML = `<div class="cs-table-msg" style="color:var(--danger)">Error: ${e.message}</div>`;
            } finally {
                isLoadingReports = false;
            }
        }

        function updateFilterVisibility(el, status) {
            if (currentReportFilter === 'pending' && status !== 'pending') {
                el.style.display = 'none';
            } else {
                el.style.display = 'block';
            }
        }

        window.applyCanned = (id, text) => {
            const area = document.getElementById(`reply-text-${id}`);
            if (area) {
                area.value = text;
                area.focus();
            }
        };

        window.toggleReport = (id) => {
            const msg = document.getElementById(`msg-${id}`);
            if (msg) msg.style.display = msg.style.display === "block" ? "none" : "block";
        };

        window.sendReply = async (id) => {
            const reply = document.getElementById(`reply-text-${id}`).value;
            if (!reply) return showToast("Reply cannot be empty", "error");
            try {
                const fd = new FormData();
                fd.append("action", "admin_reply");
                fd.append("report_id", id);
                fd.append("reply", reply);
                const res = await fetch("admin.php", { method: "POST", body: fd });
                if (res.ok) {
                    showToast("Reply sent!", "success");
                    loadReports();
                } else {
                    showToast("Failed to send reply", "error");
                }
            } catch (e) {
                showToast("Connection error", "error");
            }
        };

        csBtn.addEventListener("click", () => {
            csPanel.classList.add("open");
            csOverlay.classList.add("visible");
            // Do NOT wipe container or reset reportLastTs anymore — persistent loading!
            loadReports();
        });

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentReportFilter = btn.dataset.filter;

                // Update visibility of all items
                document.querySelectorAll('.report-item-admin').forEach(item => {
                    updateFilterVisibility(item, item.dataset.status);
                });
            });
        });

        const closeCS = () => {
            csPanel.classList.remove("open");
            csOverlay.classList.remove("visible");
        };

        closeBtn.addEventListener("click", closeCS);
        csOverlay.addEventListener("click", closeCS);

        setInterval(loadReports, 3000); // Smart polling
        loadReports(); // Initial load
    </script>

</body>

</html>
