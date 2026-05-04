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
    while (ob_get_level())
        ob_end_clean();
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
        while (ob_get_level())
            ob_end_clean();
        echo $out;
    } catch (Exception $e) {
        while (ob_get_level())
            ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

/* ---------- AJAX: FETCH REPORTS ---------- */
if (isset($_GET['ajax_reports'])) {
    while (ob_get_level())
        ob_end_clean();
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

        while (ob_get_level())
            ob_end_clean();
        echo $out;
    } catch (Exception $e) {
        while (ob_get_level())
            ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

/* ---------- AJAX: FETCH GAMES ---------- */
if (isset($_GET['ajax_games'])) {
    while (ob_get_level())
        ob_end_clean();
    ob_start();
    header("Content-Type: application/json");
    try {
        $search = $_GET['search'] ?? '';
        $searchTerm = "%$search%";

        $stmt = $conn->prepare("
            SELECT game_id, title, is_banned, main_image
            FROM games
            WHERE title LIKE ?
            ORDER BY title
            LIMIT 100
        ");
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();

        $games = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($games, JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Exception $e) {
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

    // Ban Game Action
    if ($action === 'ban_game') {
        if (isset($_POST['game_id'])) {
            $gid = (int) $_POST['game_id'];
            try {
                $stmt = $conn->prepare("UPDATE games SET is_banned = 1 WHERE game_id = ?");
                $stmt->bind_param("i", $gid);
                $stmt->execute();
                echo "ok";
            } catch (Exception $e) {
                http_response_code(500);
                echo "Ban failed";
            }
        }
        exit;
    }

    // Unban Game Action
    if ($action === 'unban_game') {
        if (isset($_POST['game_id'])) {
            $gid = (int) $_POST['game_id'];
            try {
                $stmt = $conn->prepare("UPDATE games SET is_banned = 0 WHERE game_id = ?");
                $stmt->bind_param("i", $gid);
                $stmt->execute();
                echo "ok";
            } catch (Exception $e) {
                http_response_code(500);
                echo "Unban failed";
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
    <link rel="icon" type="image/x-icon" href="/imgandgifs/logo.svg">

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
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
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
            --card-shadow: 0 8px 32px rgba(155, 89, 182, 0.1);
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
            transition: margin 0.3s ease;
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
                padding: 0 15px;
            }
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
            transition: all 0.3s ease;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .admin-header {
                flex-direction: column;
                padding: 20px;
                text-align: center;
            }

            .header-left {
                flex-direction: column;
                gap: 10px;
            }

            .search-input {
                width: 100% !important;
                max-width: 400px;
            }

            .search-wrapper {
                width: 100%;
                display: flex;
                justify-content: center;
            }

            .cs-btn {
                width: 100%;
                max-width: 400px;
            }
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

        @media (max-width: 600px) {
            .admin-title {
                font-size: 1.4rem;
            }
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

        @media (max-width: 1100px) {
            .search-input:focus {
                width: 250px;
            }
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
            right: -110%;
            width: 420px;
            max-width: 100%;
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

        /* CS Panel Handle */
        .cs-panel-handle {
            width: 40px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin: 0 auto 15px;
            display: none;
        }

        @media (max-width: 768px) {
            .cs-panel {
                width: 100%;
                border-radius: 0;
            }

            .cs-panel-handle {
                display: block;
            }
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

        @media (max-width: 768px) {
            .table-container {
                background: transparent;
                box-shadow: none;
                border: none;
            }

            table,
            thead,
            tbody,
            th,
            td,
            tr {
                display: block;
            }

            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }

            tr {
                background: var(--glass);
                backdrop-filter: blur(20px);
                margin-bottom: 20px;
                border: 1px solid var(--border-color);
                border-radius: 20px;
                padding: 20px;
                box-shadow: var(--card-shadow);
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            td {
                border: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                position: relative;
                padding: 10px 0;
                padding-left: 40%;
                min-height: auto;
                display: flex;
                align-items: center;
                text-align: right;
                justify-content: flex-end;
                font-size: 0.9rem;
            }

            td[data-label="User"] {
                justify-content: center;
                padding-left: 0;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 15px;
                margin-bottom: 5px;
            }

            td[data-label="User"]:before {
                display: none;
            }

            td:last-child {
                border-bottom: none;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: stretch;
                padding-left: 0;
                text-align: center;
                flex-direction: row;
                margin-top: 10px;
            }

            td:before {
                position: absolute;
                top: 50%;
                left: 0;
                width: 35%;
                padding-right: 10px;
                white-space: nowrap;
                transform: translateY(-50%);
                text-align: left;
                font-family: var(--orbitron);
                font-size: 0.7rem;
                text-transform: uppercase;
                color: var(--accent);
                content: attr(data-label);
                font-weight: bold;
                opacity: 0.8;
            }

            td:last-child:before {
                display: none;
            }

            .user-cell {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .username {
                font-size: 1.5rem;
                font-weight: 700;
                letter-spacing: 0.5px;
                color: var(--accent);
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            }

            .action-btn {
                flex: 1;
                margin: 0;
                justify-content: center;
                padding: 12px;
                font-size: 0.85rem;
            }
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
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 0.3px;
            transition: color 0.3s ease;
        }

        tr:hover .username {
            color: var(--accent);
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

        @media (max-width: 480px) {
            .back-nav span {
                display: none;
            }

            .admin-header {
                padding: 15px !important;
            }
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
            transition: all 0.3s ease;
        }

        @media (max-width: 600px) {
            .toast-container {
                right: 20px;
                left: 20px;
                bottom: 20px;
            }
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
            transition: min-width 0.3s ease;
        }

        @media (max-width: 600px) {
            .toast {
                min-width: 0;
            }
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

        /* ---------- BOTTOM NAV ---------- */
        .mobile-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--glass-strong);
            backdrop-filter: blur(25px);
            border-top: 1px solid var(--border-color);
            z-index: 10002;
            padding: 10px 0;
            padding-bottom: calc(10px + env(safe-area-inset-bottom, 0px));
            justify-content: space-around;
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.4);
        }

        @media (max-width: 600px) {
            .mobile-nav {
                display: flex;
            }

            body {
                padding-bottom: 80px;
            }
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-main);
            font-size: 11px;
            gap: 4px;
            opacity: 0.7;
            transition: 0.3s;
        }

        .mobile-nav-item.active {
            opacity: 1;
            color: var(--accent);
        }

        .mobile-nav-item img {
            width: 24px;
            height: 24px;
            transition: 0.3s;
        }

        .mobile-nav-item.active img {
            filter: drop-shadow(0 0 8px var(--accent));
        }

        @media (max-width: 600px) {
            .admin-header #customerServiceBtn {
                display: none;
                /* Move to floating or nav if needed, but let's hide for clean header */
            }
        }
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
            <div class="cs-panel-handle"></div>
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

        <!-- USERS TABLE SECTION -->
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

        <!-- GAMES MANAGEMENT SECTION -->
        <header class="admin-header" style="margin-top: 60px;">
            <div class="header-left">
                <div class="admin-title">Games Management</div>
            </div>
            <div class="search-wrapper">
                <input type="text" id="gameSearchInput" class="search-input" placeholder="Search game...">
            </div>
        </header>


        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="games-table-body">
                    <!-- JS Injects Rows Here -->
                </tbody>
            </table>
        </div>
    </div>

    <div id="csOverlay" class="cs-overlay"></div>

    <!-- PREMIUM MOBILE NAV -->
    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-item">
            <img src="/imgandgifs/home.svg" alt="Home">
            <span>Home</span>
        </a>
        <a href="admin.php" class="mobile-nav-item active">
            <img src="/imgandgifs/tool.svg" alt="Admin" style="filter: invert(1);">
            <span>Admin</span>
        </a>
        <a href="profile.php" class="mobile-nav-item">
            <img src="/imgandgifs/user.svg" alt="Profile">
            <span>Profile</span>
        </a>
        <a href="auth.php?action=logout" class="mobile-nav-item">
            <img src="/imgandgifs/log-out.svg" alt="Logout">
            <span>Logout</span>
        </a>
    </nav>

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
                            <td data-label="User">
                                <div class="user-cell">
                                    <img src="${avatar}" class="user-avatar" alt="User">
                                    <span class="username">${escapeHtml(u.username)}</span>
                                </div>
                            </td>
                            <td data-label="Role">${roleBadge}</td>
                            <td data-label="Status">${statusText}</td>
                            <td data-label="Actions">${actionsHtml}</td>
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


        /* ---------- GAMES MANAGEMENT ---------- */
        const gamesTableBody = document.getElementById("games-table-body");
        const gameSearchInput = document.getElementById("gameSearchInput");

        async function loadGames() {
            if (document.hidden) return;
            try {
                const res = await fetch("admin.php?ajax_games=1&search=" + encodeURIComponent(gameSearchInput.value));
                if (!res.ok) throw new Error("Network error");
                const games = await res.json();
                const fragment = document.createDocumentFragment();

                if (games.length === 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="3" style="text-align:center; padding: 40px; color: rgba(255,255,255,0.5);">No games found.</td>`;
                    fragment.appendChild(tr);
                } else {
                    games.forEach(g => {
                        const tr = document.createElement("tr");
                        const statusBadge = g.is_banned == 1
                            ? `<span class="status-banned" style="color:var(--danger)">Banned</span>`
                            : `<span class="status-active" style="color:var(--success)">Active</span>`;

                        const actionBtn = g.is_banned == 1
                            ? `<button class="action-btn btn-success" onclick="toggleGameBan(${g.game_id}, 'unban_game')">Activate</button>`
                            : `<button class="action-btn btn-danger" onclick="toggleGameBan(${g.game_id}, 'ban_game')">Ban</button>`;

                        tr.innerHTML = `
                            <td>
                                <div class="user-cell">
                                    <img src="${g.main_image || 'imgandgifs/logo.png'}" class="user-avatar" style="border-radius: 8px;">
                                    <span class="username">${escapeHtml(g.title)}</span>
                                </div>
                            </td>
                            <td>${statusBadge}</td>
                            <td>${actionBtn}</td>
                        `;
                        fragment.appendChild(tr);
                    });
                }
                gamesTableBody.innerHTML = "";
                gamesTableBody.appendChild(fragment);
            } catch (e) {
                console.error("Load games failed", e);
            }
        }

        async function toggleGameBan(gameId, action) {
            if (!confirm(`Are you sure you want to ${action === 'ban_game' ? 'BAN' : 'ACTIVATE'} this game?`)) return;
            try {
                const fd = new FormData();
                fd.append("action", action);
                fd.append("game_id", gameId);
                const res = await fetch("admin.php", { method: "POST", body: fd });
                if (res.ok) {
                    showToast("Game updated successfully!");
                    loadGames();
                } else {
                    showToast("Failed to update game.", "error");
                }
            } catch (e) {
                showToast("Connection error.", "error");
            }
        }

        gameSearchInput.oninput = debounce(loadGames, 300);
        setInterval(loadGames, 10000);
        loadGames();

    </script>

</body>

</html>
