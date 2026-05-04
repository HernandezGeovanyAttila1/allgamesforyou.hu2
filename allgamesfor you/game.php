<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'db.php';
require_once 'utils.php';

// AUTO-LOGIN LOGIC
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    if (strpos($_COOKIE['rememberme'], ':') !== false) {
        list($selector, $token) = explode(':', $_COOKIE['rememberme']);
        $stmt = $conn->prepare("SELECT user_id, username, role, profile_img, token_validator FROM users WHERE token_selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            if (!empty($row['token_validator']) && password_verify($token, $row['token_validator'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_img'] = $row['profile_img'] ?? 'imgandgifs/login.png';
                // rotate
                $new_selector = bin2hex(random_bytes(9));
                $new_token = bin2hex(random_bytes(33));
                $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                $stmt2->execute();
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
                setcookie("rememberme", $new_selector . ":" . $new_token, time() + 86400 * 30, "/", "", $secure, true);
            }
        }
    }
}

$game_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($game_id <= 0) {
    header("Location: index.php");
    exit();
}

// FETCH GAME DETAILS
$stmt = $conn->prepare("SELECT g.*, u.username as creator_name FROM games g LEFT JOIN users u ON g.created_by = u.user_id WHERE g.game_id = ? LIMIT 1");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

if (!$game) {
    die("Game not found.");
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// ACCESS CONTROL: If game is banned, only admins can see it
if (($game['is_banned'] ?? 0) == 1 && !$isAdmin) {
    die("This game has been banned by an administrator.");
}

// FETCH FAVORITE STATUS
$userId = $_SESSION['user_id'] ?? 0;
$is_favorite = false;
if ($userId > 0) {
    $fav_stmt = $conn->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND game_id = ? LIMIT 1");
    $fav_stmt->bind_param("ii", $userId, $game_id);
    $fav_stmt->execute();
    if ($fav_stmt->get_result()->num_rows > 0) {
        $is_favorite = true;
    }
}

// HANDLE RATING SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['rating']) && isset($_SESSION['user_id'])) {
    $rating = (int) $_POST['rating'];
    if ($rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO game_ratings (game_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
        $stmt->bind_param("iiii", $game_id, $_SESSION['user_id'], $rating, $rating);
        if ($stmt->execute()) {
            // Success: reload to show updated rating
            header("Location: game.php?id=" . $game_id);
            exit();
        }
    }
}

// FETCH AVERAGE RATING
$avg_rating = 0;
$rating_count = 0;
$stmt = $conn->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as count_r FROM game_ratings WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$rating_res = $stmt->get_result()->fetch_assoc();
if ($rating_res) {
    if ($rating_res['avg_r'] !== null) {
        $avg_rating = round($rating_res['avg_r'], 1);
    } else {
        $avg_rating = 0;
    }
    $rating_count = $rating_res['count_r'];
}

// FETCH USER'S RATING
$user_rating = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT rating FROM game_ratings WHERE game_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $game_id, $_SESSION['user_id']);
    $stmt->execute();
    $user_rating_res = $stmt->get_result()->fetch_assoc();
    if ($user_rating_res) {
        $user_rating = $user_rating_res['rating'];
    }
}

// DATABASE SCHEMA IS ALREADY UPDATED (Includes parent_id and comment_id)

// HANDLE COMMENT SUBMISSION (Main and Replies)
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST['comment_submit']) || isset($_POST['reply_submit'])) && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO comments (game_id, user_id, content, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisi", $game_id, $_SESSION['user_id'], $content, $parent_id);
        if ($stmt->execute()) {
            // Success: reload to clear POST and show comment
            header("Location: game.php?id=" . $game_id . "#comment-" . $conn->insert_id);
            exit();
        } else {
            $error_msg = "Failed to post comment: " . $conn->error;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['report_submit']) && isset($_SESSION['user_id'])) {
    $headline = trim($_POST['headline']);
    $report_content = trim($_POST['report_content']);

    if (!empty($headline) && !empty($report_content)) {
        $stmt = $conn->prepare("INSERT INTO reports (user_id, game_id, headline, report, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $game_id, $headline, $report_content);
        if ($stmt->execute()) {
            $report_success = "Your report has been submitted to the admins.";
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('$report_success'); });</script>";
        } else {
            $report_error = "Failed to submit report: " . $conn->error;
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('$report_error'); });</script>";
        }
    }
}

// FETCH COMMENTS
$comments = [];
$stmt = $conn->prepare("SELECT c.*, u.username, u.profile_img FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.game_id = ? ORDER BY c.created_at ASC");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $comments[] = $row;
}

// Function to render comments recursively
function renderComments($allComments, $gameId, $parentId = null, $depth = 0)
{
    $html = "";
    foreach ($allComments as $c) {
        $cParentId = $c['parent_id'] ?? null;
        if ((string) $cParentId === (string) $parentId) { // Explicitly check for null vs 0
            $commentId = $c['id'] ?? $c['comment_id'] ?? null;

            // Limit depth visual to 5 levels, then keep it at 5
            $visualDepth = min($depth, 5);
            $marginLeft = $visualDepth * 20;

            $html .= '<div class="comment-item" id="comment-' . $commentId . '" style="margin-left: ' . $marginLeft . 'px; position: relative;">';

            // Visual Connector for nested comments
            if ($depth > 0) {
                $html .= '<div class="comment-connector"></div>';
            }

            $pfp = !empty($c['profile_img']) ? htmlspecialchars($c['profile_img']) : 'imgandgifs/login.png';
            $html .= '    <img src="' . $pfp . '" alt="User" class="user-pfp" style="width: 32px; height: 32px;">';
            $html .= '    <div class="comment-content">';
            $html .= '        <div class="comment-header">';
            $html .= '            <span class="comment-user" style="font-size: 0.8rem;">' . htmlspecialchars($c['username']) . '</span>';
            $html .= '            <span class="comment-date" style="font-size: 0.6rem;">' . date('M d, H:i', strtotime($c['created_at'])) . '</span>';
            $html .= '        </div>';
            $html .= '        <div class="comment-text" style="font-size: 0.85rem;">' . nl2br(htmlspecialchars($c['content'])) . '</div>';

            // --- Reply Section ---
            if (isset($_SESSION['user_id']) && $depth === 0) {
                $html .= '        <div class="comment-actions" style="margin-top: 4px; display:flex; gap:12px; align-items:center;">';
                $html .= '            <button class="reply-btn-compact" onclick="toggleReplyForm(' . $commentId . ')">Reply</button>';
                // Only show expand/collapse button
                $html .= '            <button class="toggle-btn" style="display:none; font-size: 10px; opacity:0.6;">More</button>';
                $html .= '        </div>';

                // Reply Form (hidden by default)
                $html .= '        <div id="reply-form-' . $commentId . '" class="reply-form-container">';
                $html .= '            <div class="reply-form-inner">';
                $html .= '                <div class="reply-box-small">';
                $html .= '                    <form method="POST">';
                $html .= '                        <input type="hidden" name="game_id" value="' . (int) $gameId . '">';
                $html .= '                        <input type="hidden" name="parent_id" value="' . $commentId . '">';
                $html .= '                        <textarea name="content" placeholder="Add a reply..." required></textarea>';
                $html .= '                        <div class="reply-actions">';
                $html .= '                            <button type="button" class="cancel-reply-btn" onclick="toggleReplyForm(' . $commentId . ')">Cancel</button>';
                $html .= '                            <button type="submit" name="reply_submit" class="post-reply-btn">Reply</button>';
                $html .= '                        </div>';
                $html .= '                    </form>';
                $html .= '                </div>';
                $html .= '            </div>';
                $html .= '        </div>';
            }

            // Recursive call for sub-comments
            $html .= '        <div class="replies-container">';
            $html .= renderComments($allComments, $gameId, $commentId, $depth + 1);
            $html .= '        </div>';

            $html .= '    </div>';
            $html .= '</div>';
        }
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($game['title']); ?> - Gaming Hub
    </title>
    <link rel="icon" type="image/x-icon" href="/imgandgifs/logo.svg">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Orbitron:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
        }

        .rating-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stars-display {
            display: flex;
            gap: 5px;
            color: #ff8e00;
            font-size: 1.2rem;
        }

        .rating-stats {
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 600;
        }

        .star-rating {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 5px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 1.8rem;
            color: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
        }

        .star-rating label:hover,
        .star-rating label:hover~label,
        .star-rating input:checked~label {
            color: #ffd700;
            transform: scale(1.1);
        }

        .star-rating label:active {
            transform: scale(0.9);
        }

        body.bright .star-rating label {
            color: rgba(0, 0, 0, 0.1);
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --box-bg: rgba(191, 50, 241, 0.08);
            --input-bg: rgba(255, 255, 255, 0.05);
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-hover: rgba(255, 255, 255, 0.07);
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.25);
            --glass: rgba(247, 243, 232, 0.85);
            --glass-strong: rgba(255, 255, 255, 0.95);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --box-bg: rgba(155, 89, 182, 0.06);
            --input-bg: rgba(0, 0, 0, 0.04);
            --card-bg: rgba(0, 0, 0, 0.02);
            --card-hover: rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            transition: background 0.8s ease, color 0.5s ease;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-attachment: fixed;
            overflow-x: hidden;
        }

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

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 80px 0;
            display: flex;
            gap: 40px;
            align-items: flex-start;
            animation: floatUp 0.8s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .game-main {
            flex: 1.8;
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .game-sidebar {
            flex: 1;
            position: sticky;
            top: 100px;
        }

        @keyframes floatUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .game-header {
            position: relative;
            width: 100%;
            height: 60vh;
            min-height: 400px;
            border-radius: 30px;
            overflow: hidden;
            background: #000;
            display: flex;
            align-items: flex-end;
            margin-bottom: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .header-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            filter: brightness(0.6);
            transition: transform 0.5s ease;
        }

        .game-header:hover .header-bg {
            transform: scale(1.05);
        }

        .header-content {
            position: relative;
            z-index: 10;
            width: 100%;
            padding: 60px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, transparent 100%);
        }

        .header-content h1 {
            font-family: var(--orbitron);
            font-size: 4.5rem;
            margin: 0;
            color: #fff;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 1);
            line-height: 1.1;
            overflow-wrap: break-word;
        }

        .play-btn-main {
            background: var(--accent);
            color: #fff;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-family: var(--orbitron);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            box-shadow: 0 10px 30px rgba(191, 50, 241, 0.5);
            transition: 0.3s;
        }

        .play-btn-main:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(191, 50, 241, 0.7);
        }

        .game-content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 40px;
            width: 100%;
        }

        .game-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid var(--border-color);
            padding: 40px;
        }

        .meta-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .meta-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .meta-item:last-child {
            border: none;
        }

        .label {
            opacity: 0.6;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }

        .action-bar {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.3s;
            font-family: var(--orbitron);
            font-size: 0.8rem;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent);
        }

        .buy-btn-game {
            font-family: var(--orbitron);
            font-size: 0.95rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
            margin-bottom: 20px;
            text-decoration: none;
            letter-spacing: 1px;
            box-shadow: 0 4px 20px rgba(29, 185, 84, 0.3);
        }

        .buy-btn-game:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 28px rgba(29, 185, 84, 0.5);
            filter: brightness(1.12);
        }

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

        footer {
            background: var(--glass);
            color: var(--text-main);
            padding: 40px 20px;
            text-align: center;
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
            margin-top: 60px;
            backdrop-filter: blur(10px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background: var(--glass-strong);
            margin: 10% auto;
            padding: 40px;
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 500px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-modal {
            color: var(--text-main);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .close-modal:hover {
            color: var(--accent);
        }

        .report-form label {
            display: block;
            margin-top: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .report-form input,
        .report-form textarea {
            width: 100%;
            padding: 15px;
            margin: 10px 0 10px 0;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-main);
            font-family: inherit;
            box-sizing: border-box;
            outline: none;
            transition: 0.3s;
        }

        .report-form input:focus,
        .report-form textarea:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .report-form button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: var(--orbitron);
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 20px;
            box-shadow: var(--glow);
        }

        .report-form button:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
        }

        .game-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .game-description {
            font-size: 1.15rem;
            line-height: 1.8;
            color: var(--text-main);
            opacity: 0.95;
            background: var(--glass);
            backdrop-filter: blur(30px);
            padding: 40px;
            border-radius: 32px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            transition: all 0.5s ease;
        }

        .section-title {
            font-family: var(--orbitron);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
            font-size: 1.4rem;
            color: var(--accent);
            text-shadow: var(--glow);
        }

        /* Comments Section */
        .comments-section {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            padding: 40px;
            box-shadow: var(--shadow);
            word-break: break-word;
            transition: all 0.5s ease;
        }

        .comments-section h2 {
            font-family: var(--orbitron);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 30px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .comment-form {
            margin-bottom: 40px;
        }

        .comment-form textarea {
            width: 100%;
            padding: 20px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            color: var(--text-main);
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            outline: none;
            transition: 0.3s;
            padding-right: 5px;
        }

        .comment-form textarea:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .comment-form button {
            margin-top: 15px;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: var(--orbitron);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: var(--glow);
        }

        .comment-form button:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .comment-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            position: relative;
        }

        .comment-connector {
            position: absolute;
            left: -15px;
            top: 0;
            bottom: 20px;
            width: 2px;
            background: var(--border-color);
            border-radius: 4px;
        }

        .replies-container {
            margin-top: 10px;
        }

        .reply-btn-compact {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            padding: 2px 0;
            opacity: 0.8;
            transition: 0.2s;
        }

        .reply-btn-compact:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .reply-form-container {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.25s ease, margin 0.25s ease;
        }

        .reply-form-container.active {
            grid-template-rows: 1fr;
            margin-top: 12px;
            margin-bottom: 12px;
        }

        .reply-form-inner {
            overflow: hidden;
        }

        .reply-box-small {
            min-height: 0;
            background: var(--input-bg);
            border-radius: 12px;
            padding: 12px;
            border: 1px solid var(--border-color);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .reply-box-small textarea {
            width: 100%;
            background: transparent;
            border: none;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.85rem;
            resize: none;
            min-height: 28px;
            outline: none;
            margin-bottom: 0;
            padding: 0;
        }

        /* buttons container */
        .reply-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 8px;

            opacity: 0;
            transform: translateY(-4px);
            pointer-events: none;
            transition: 0.2s;
        }

        /* show buttons when textarea focused */
        .reply-box-small:focus-within .reply-actions {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .post-reply-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .post-reply-btn:hover {
            filter: brightness(1.2);
            transform: translateY(-1px);
        }

        .cancel-reply-btn {
            background: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            opacity: 0.7;
        }

        .cancel-reply-btn:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.05);
        }

        .user-pfp {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent);
            box-shadow: var(--glow);
        }

        .comment-content {
            flex: 1;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .comment-user {
            font-weight: 600;
            color: var(--accent);
        }

        .comment-date {
            font-size: 0.8rem;
            opacity: 0.6;
        }

        .comment-text {
            line-height: 1.6;
            opacity: 0.9;
            color: var(--text-main);
            display:
                -webkit-box;
            -webkit-line-clamp:
                4;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: color 0.25s ease,
                max-height 0.3s ease;
            max-height: calc(1.6em * 4);
            /* height for 4 lines */
            transition: max-height 0.35s ease;

        }


        .comment-text.expanded {
            -webkit-line-clamp: unset;
            max-height: 200px;
            overflow-y: auto;
        }

        @media (prefers-color-scheme: dark) {
            .toggle-btn {
                background: rgba(255, 255, 255, 0.08);
                color: #ddd;
            }

            .toggle-btn:hover {
                background: rgba(255, 255, 255, 0.15);
            }
        }

        .toggle-btn {
            border: none;
            background: rgba(0, 0, 0, 0.06);
            padding: 0;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            line-height: 1;
            color: #444;
            transition: background 0.25s ease, color 0.25s ease;
        }


        .toggle-btn:focus {
            outline: none;

        }


        /* Floating Buttons */
        .theme-toggle-btn {
            position: fixed;
            top: 30px;
            left: 30px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            padding: 0;
            border-radius: 50%;
            cursor: pointer;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            z-index: 1000;
            overflow: hidden;
        }

        .theme-toggle-btn img {
            width: 35px;
            height: 35px;
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1) rotate(10deg);
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .back-btn {
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 1000;
            transition: 0.3s;
        }

        .back-btn img {
            width: 80px;
            filter: drop-shadow(var(--glow));
            transition: filter 0.3s;
        }

        body.bright .back-btn img {
            filter: drop-shadow(0 0 10px rgba(0, 0, 0, 0.1));
        }

        .back-btn:hover {
            transform: scale(1.1);
        }

        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            cursor: pointer;
            z-index: 10001;
            background: var(--glass-strong);
            padding: 5px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .theme-toggle-btn img {
            width: 30px;
            height: 30px;
            pointer-events: none;
            transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1);
            border-color: var(--accent);
        }

        @media (max-width: 1100px) {
            .container {
                flex-direction: column;
                width: 100%;
                padding: 0 15px;
                gap: 40px;
                margin: 40px 0;
            }

            .game-sidebar {
                position: static;
                width: 100%;
            }

            .game-content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 30px;
            }

            .header-content h1 {
                font-size: 2.5rem;
            }

            .game-header {
                height: auto;
                min-height: 350px;
                border-radius: 20px;
            }

            .play-btn-main {
                width: 100%;
                justify-content: center;
                padding: 18px;
                font-size: 1.1rem;
            }

            .action-bar {
                flex-direction: column;
                width: 100%;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
                display: flex;
            }

            .game-card {
                padding: 25px;
                border-radius: 20px;
            }

            .container {
                margin: 20px 0;
            }

            .back-btn,
            .theme-toggle-btn {
                top: 15px;
                width: 45px;
                height: 45px;
            }

            .back-btn {
                right: 15px;
            }

            .theme-toggle-btn {
                left: 15px;
            }

            .game-header {
                margin-top: 50px;
                height: auto;
                min-height: 300px;
            }

            .header-content {
                padding: 25px 20px;
            }

            .header-content h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>

<body class="dark">

    <div id="themeToggle" class="theme-toggle-btn" title="Toggle Theme">
        <img src="imgandgifs/sun.svg" alt="Theme">
    </div>

    <a href="games.php" class="back-btn">
        <img src="imgandgifs/arrow-left-circle.svg" alt="Back">
    </a>

    <div class="container">
        <div class="game-main">
            <div class="game-header">
                <div class="header-bg"
                    style="background-image: url('<?php echo htmlspecialchars($game['main_image'] ?? ''); ?>');"></div>
                <div class="header-content">
                    <h1><?php echo htmlspecialchars($game['title'] ?? ''); ?></h1>

                    <?php if (empty($game['buy_link'])): ?>
                        <a href="workinggame.php?id=<?php echo $game_id; ?>" class="play-btn-main">
                            <i class="fas fa-play-circle"></i> PLAY NOW
                        </a>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($game['buy_link'] ?? ''); ?>" target="_blank"
                            class="play-btn-main" style="background: #27ae60;">
                            <i class="fas fa-shopping-cart"></i> GET GAME
                        </a>
                    <?php endif; ?>

                    <div class="action-bar">
                        <?php if ($userId > 0): ?>
                            <button class="action-btn" onclick="toggleGameFavorite(<?php echo $game_id; ?>)">
                                <i class="<?php echo $is_favorite ? 'fas fa-star' : 'far fa-star'; ?>"></i> Favorited
                            </button>
                            <button class="action-btn" onclick="openReportModal()">
                                <i class="fas fa-flag"></i> Report
                            </button>
                            <?php if ($isAdmin): ?>
                                <button class="action-btn"
                                    onclick="adminToggleBan(<?php echo $game_id; ?>, <?php echo ($game['is_banned'] ?? 0) ? 0 : 1; ?>)">
                                    <i class="fas fa-ban"></i> <?php echo ($game['is_banned'] ?? 0) ? 'Unban' : 'Ban'; ?>
                                </button>
                            <?php endif; ?>
                            <?php if ((int) $userId === (int) $game['created_by']): ?>
                                <a href="edit_game.php?id=<?php echo $game_id; ?>" class="action-btn"
                                    style="text-decoration:none;">
                                    <i class="fas fa-edit"></i> Edit Game
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="game-content-grid">
                <div class="game-card">
                    <h2 class="section-title">Overview</h2>
                    <p style="line-height: 1.8; opacity: 0.9; font-size: 1.1rem;">
                        <?php echo nl2br(htmlspecialchars($game['description'] ?? '')); ?>
                    </p>
                </div>

                <div class="game-card">
                    <h2 class="section-title">Details</h2>
                    <div class="meta-list">
                        <div class="meta-item">
                            <span class="label">Created By</span>
                            <span><?php echo htmlspecialchars($game['creator_name'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Release Date</span>
                            <span><?php echo date('F d, Y', strtotime($game['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">Category</span>
                            <span><?php echo htmlspecialchars($game['category'] ?? 'General'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">User Rating</span>
                            <span><i class="fas fa-star" style="color:#ff8e00;"></i> <?php echo $avg_rating; ?> /
                                5</span>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div style="margin-top: 30px; text-align: center;">
                            <p class="label" style="margin-bottom: 10px;">Your Rating</p>
                            <form id="ratingForm" method="POST">
                                <div class="star-rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"
                                            <?php echo ($user_rating == $i) ? 'checked' : ''; ?>
                                            onclick="document.getElementById('ratingForm').submit();">
                                        <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="game-sidebar">
            <div class="comments-section">
                <h2>Feedback</h2>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="comment-form">
                        <form method="POST">
                            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                            <textarea name="content" placeholder="Share your thoughts..." required></textarea>
                            <button type="submit" name="comment_submit">Post</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding:10px; opacity:0.7; font-size:0.9rem;">
                        Please <a href="auth.php"
                            style="color:var(--accent); text-decoration:none; font-weight:bold;">Login</a> to post.
                    </p>
                <?php endif; ?>

                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <p style="text-align:center; opacity:0.5; font-size:0.9rem;">No comments yet.</p>
                    <?php else: ?>
                        <?php echo renderComments($comments, $game_id); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const themeBtn = document.getElementById('themeToggle');
        const body = document.body;

        function updateThemeUI() {
            const isDark = body.classList.contains('dark');
            const img = themeBtn ? themeBtn.querySelector('img') : null;
            if (img) {
                img.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";
                img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.1)';
                img.style.filter = isDark ? 'drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))' : 'drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))';
            }
        }

        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            body.classList.remove('dark', 'bright');
            body.classList.add(savedTheme);
            updateThemeUI();
        }

        if (themeBtn) {
            themeBtn.addEventListener('click', (e) => {
                const currentTheme = body.classList.contains('dark') ? 'dark' : 'bright';
                const newTheme = currentTheme === 'dark' ? 'bright' : 'dark';
                body.classList.remove('dark', 'bright');
                body.classList.add(newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeUI();
            });
        }
        initTheme();

        // --- Reply Toggle ---
        function toggleReplyForm(commentId) {
            const container = document.getElementById('reply-form-' + commentId);
            if (container) {
                const isActive = container.classList.toggle('active');
                if (isActive) {
                    const textarea = container.querySelector('textarea');
                    if (textarea) textarea.focus();
                }
            }
        }

        // --- Favorite Toggle ---
        function toggleGameFavorite(gameId) {
            fetch("profile.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ toggle: String(gameId) })
            })
                .then(res => res.json())
                .then(data => {
                    const btn = document.querySelector('.fav-btn-game');
                    const span = btn.querySelector('span');
                    if (data.status === "added") {
                        btn.classList.add("active");
                        span.textContent = "Favorited";
                        // If shared showToast function exists
                        if (typeof showToast === "function") showToast("Added to favorites");
                    } else {
                        btn.classList.remove("active");
                        span.textContent = "Favorite";
                        if (typeof showToast === "function") showToast("Removed from favorites");
                    }
                })
                .catch(err => console.error("FAV ERROR:", err));
        }



        // Add toast function if missing in this file
        function showToast(message) {
            let toast = document.getElementById('game-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'game-toast';
                toast.style.cssText = 'position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.8); color:white; padding:10px 20px; border-radius:8px; z-index:9999; opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.opacity = '1';
            setTimeout(() => { toast.style.opacity = '0'; }, 3000);
        }

        function openReportModal() {
            document.getElementById('reportModal').style.display = "block";
        }

        function closeReportModal() {
            document.getElementById('reportModal').style.display = "none";
        }

        // Show toast if updated
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('updated') === '1') {
                showToast("Game details updated successfully!");
            }
        });

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('reportModal');
            if (event.target == modal) {
                closeReportModal();
            }
        }

        function adminToggleBan(gameId, ban) {
            if (!confirm(ban ? "Ban this game? It will be hidden from everyone except admins." : "Unban this game?")) return;

            const formData = new FormData();
            formData.append('action', ban ? 'ban_game' : 'unban_game');
            formData.append('game_id', gameId);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.text())
                .then(data => {
                    if (data.trim() === 'ok') {
                        showToast(ban ? "Game Banned" : "Game Unbanned");
                        location.reload();
                    } else {
                        showToast("Error: " + data);
                    }
                })
                .catch(err => console.error(err));
        }

        initExpandButtons();
    </script>

    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeReportModal()">&times;</span>
            <h2 class="section-title">Report Game</h2>
            <form method="POST" class="report-form">
                <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                <label>Headline</label>
                <input type="text" name="headline" placeholder="Brief summary of the issue..." required>
                <label>Report Content</label>
                <textarea name="report_content" rows="4" placeholder="Detailed description..." required></textarea>
                <button type="submit" name="report_submit">Submit Report</button>
            </form>
        </div>
    </div>

    <footer>
        <p>Games © 2026 • Privacy Policy</p>
    </footer>

    <!-- PREMIUM MOBILE NAV -->
    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-item">
            <img src="/imgandgifs/home.svg" alt="Home">
            <span>Home</span>
        </a>
        <a href="games.php" class="mobile-nav-item">
            <img src="/imgandgifs/folder.svg" alt="Games">
            <span>Games</span>
        </a>
        <a href="profile.php" class="mobile-nav-item">
            <img src="/imgandgifs/user.svg" alt="Profile">
            <span>Profile</span>
        </a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" class="mobile-nav-item">
                <img src="/imgandgifs/tool.svg" alt="Admin">
                <span>Admin</span>
            </a>
        <?php endif; ?>
    </nav>

</body>

</html>