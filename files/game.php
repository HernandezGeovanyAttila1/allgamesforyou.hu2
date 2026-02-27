<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'utils.php';

// AUTO-LOGIN LOGIC
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

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

// HANDLE COMMENT SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['comment_submit']) && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO comments (game_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $game_id, $_SESSION['user_id'], $content);
        if ($stmt->execute()) {
            header("Location: game.php?id=" . $game_id);
            exit();
        }
    }
}

// FETCH COMMENTS
$comments = [];
$stmt = $conn->prepare("SELECT c.*, u.username, u.profile_img FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.game_id = ? ORDER BY c.created_at DESC");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $comments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game['title']); ?> - Gaming Hub</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Orbitron:wght@400;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
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
            --input-bg: rgba(255, 255, 255, 0.05);
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
            --input-bg: rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            transition: color 0.5s ease;
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
            background: var(--glass);
            backdrop-filter: blur(40px);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            display: flex;
            min-height: 500px;
        }

        .game-banner {
            flex: 0 0 45%;
            position: relative;
            border-right: 1px solid var(--border-color);
        }

        .game-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .game-banner::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40%;
            background: linear-gradient(to top, rgba(15, 10, 21, 0.4), transparent);
        }

        .game-info {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .game-info h1 {
            font-family: var(--orbitron);
            font-size: 3rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin: 0 0 20px 0;
            text-shadow: var(--glow);
            color: var(--accent);
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
            padding-right:5px ;
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
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .comment-item:last-child {
            border: none;
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
            max-height: calc(1.6em * 4); /* height for 4 lines */ 
            transition: max-height 0.35s ease;
            
        }
        
        
        .comment-text.expanded { -webkit-line-clamp: unset; max-height: 200px;   overflow-y: auto;}
        
       @media (prefers-color-scheme: dark) { .toggle-btn { background: rgba(255, 255, 255, 0.08); color: #ddd; } .toggle-btn:hover { background: rgba(255, 255, 255, 0.15); } }

        .toggle-btn { border: none; 
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
        /*.theme-toggle-btn {*/
        /*    position: fixed;*/
        /*    top: 30px;*/
        /*    left: 30px;*/
        /*    background: var(--glass);*/
        /*    backdrop-filter: blur(20px);*/
        /*    padding: 0;*/
        /*    border-radius: 50%;*/
        /*    cursor: pointer;*/
        /*    width: 55px;*/
        /*    height: 55px;*/
        /*    display: flex;*/
        /*    align-items: center;*/
        /*    justify-content: center;*/
        /*    box-shadow: var(--shadow);*/
        /*    border: 1px solid var(--border-color);*/
        /*    transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);*/
        /*    z-index: 1000;*/
        /*    overflow: hidden;*/
            
        /*}*/

        /*.theme-toggle-btn img {*/
        /*    width: 35px;*/
        /*    height: 35px;*/
        /*    transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);*/
        /*}*/

        /*.theme-toggle-btn:hover {*/
        /*    transform: scale(1.1) rotate(10deg);*/
        /*    border-color: var(--accent);*/
        /*    box-shadow: var(--glow);*/
        /*}*/

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
        }

        .back-btn:hover {
            transform: scale(1.1);
        }

        @media (max-width: 1100px) {
            .container {
                flex-direction: column;
            }

            .game-sidebar {
                position: static;
                width: 100%;
            }
        }

        @media (max-width: 900px) {
            .game-header {
                flex-direction: column;
            }

            .game-banner {
                flex: 0 0 350px;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }

            .game-info h1 {
                font-size: 2.2rem;
            }

            .game-info {
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .game-banner {
                height: 300px;
            }

            .game-title-area h1 {
                font-size: 2rem;
            }

            .game-title-area {
                bottom: 20px;
                left: 20px;
            }

            .container {
                margin: 100px 0 40px;
            }
        }
    </style>
</head>

<body class="dark">

    <!--<div id="themeToggle" class="theme-toggle-btn">-->
    <!--    <img src="imgandgifs/darklightmode.webp" alt="Theme">-->
    <!--</div>-->

    <a href="index.php" class="back-btn">
        <img src="imgandgifs/arrow-left-circle.svg" alt="Back">
    </a>

    <div class="container">
        <div class="game-main">
            <div class="game-header">
                <div class="game-banner">
                    <img src="<?php echo htmlspecialchars($game['main_image']); ?>"
                        alt="<?php echo htmlspecialchars($game['title']); ?>">
                </div>
                <div class="game-info">
                    <h1><?php echo htmlspecialchars($game['title']); ?></h1>
                    <div class="game-meta">
                        <span>Uploaded by:
                            <strong><?php echo htmlspecialchars($game['creator_name']); ?></strong></span>
                        <span></span>
                        <span>Date: <?php echo date('M d, Y', strtotime($game['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="game-description">
                <h2
                    style="font-family: var(--orbitron); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; font-size: 1.4rem; color: var(--accent);">
                    Description</h2>
                <?php echo nl2br(htmlspecialchars($game['description'])); ?>
            </div>
        </div>

        <div class="game-sidebar">
            <div class="comments-section">
                <h2> Feedback</h2>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="comment-form">
                        <form method="POST">
                            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
                            <textarea name="content" placeholder="Share your thoughts..." required></textarea>
                            <button type="submit" name="comment_submit">Post</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="text-align:center; padding: 10px; opacity: 0.7; font-size: 0.9rem;">Please <a href="Auth.php"
                            style="color:var(--accent); text-decoration:none; font-weight:bold;">Login</a> to post.</p>
                <?php endif; ?>

                <div class="comments-list">
                    <?php if (empty($comments)): ?>
                        <p style="text-align:center; opacity: 0.5; font-size: 0.9rem;">No comments yet.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $c): ?>
                            <div class="comment-item">
                                <img src="<?php echo htmlspecialchars($c['profile_img'] ?? 'imgandgifs/login.png'); ?>"
                                    alt="User" class="user-pfp" style="width: 40px; height: 40px;">
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <span class="comment-user"
                                            style="font-size: 0.9rem;"><?php echo htmlspecialchars($c['username']); ?></span>
                                        <span class="comment-date"
                                            style="font-size: 0.7rem;"><?php echo date('M d', strtotime($c['created_at'])); ?></span>
                                    </div>
                                    <div class="comment-text" style="font-size: 0.9rem;">
                                        <?php echo nl2br(htmlspecialchars($c['content'])); ?>
                                    </div>
                                    <button class="toggle-btn">...</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Logic
        const themeBtn = document.getElementById('themeToggle');
        const bodyValue = document.body;

        function updateThemeUI() {
            const isDark = bodyValue.classList.contains('dark');
            const img = themeBtn.querySelector('img');
            if (img) {
                img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.2)';
            }
        }

        function applyTheme() {
            const t = localStorage.getItem('theme') || 'dark';
            bodyValue.className = t;
            updateThemeUI();
        }
        
        
        

        themeBtn.addEventListener('click', () => {
            const isDark = bodyValue.classList.contains('dark');
            const newTheme = isDark ? 'bright' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme();
        });
        applyTheme();
        
        
        document.querySelectorAll('.comment-item').forEach(item => {
    const text = item.querySelector('.comment-text');
    const btn = item.querySelector('.toggle-btn');

    const lineHeight = parseFloat(getComputedStyle(text).lineHeight);
    const maxLines = 5;
    const maxHeight = lineHeight * maxLines;

    // If comment is longer than 5 lines → show button
    if (text.scrollHeight > maxHeight) {
        btn.style.display = "inline-block";
    } else {
        btn.style.display = "none";
    }

    // Toggle expand
    btn.addEventListener('click', () => {
        text.classList.toggle('expanded');
    });
});


        
        
    </script>

</body>

</html>