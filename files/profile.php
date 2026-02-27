<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* ===== FAVOURITE HANDLER ===== */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false
) {

    $data = json_decode(file_get_contents("php://input"), true);


    // HANDLER FOR FAVOURITES
    if (isset($data["toggle"])) {
        $gameId = (string) $data["toggle"];

        if (!isset($_SESSION["favourites"])) {
            $_SESSION["favourites"] = [];
        }

        if (in_array($gameId, $_SESSION["favourites"])) {
            // remove
            $_SESSION["favourites"] = array_filter(
                $_SESSION["favourites"],
                function ($id) use ($gameId) {
                    return $id !== $gameId;
                }
            );

            echo json_encode(["status" => "removed"]);
        } else {
            // add
            $_SESSION["favourites"][] = $gameId;
            echo json_encode(["status" => "added"]);
        }
        exit;
    }

    // HANDLER FOR BANNED GAMES (SESSION BASED)
    if (isset($data["toggle_ban"])) {
        $gameId = (string) $data["toggle_ban"];

        if (!isset($_SESSION["banned_games"])) {
            $_SESSION["banned_games"] = [];
        }

        if (in_array($gameId, $_SESSION["banned_games"])) {
            // remove
            $_SESSION["banned_games"] = array_filter(
                $_SESSION["banned_games"],
                function ($id) use ($gameId) {
                    return $id !== $gameId;
                }
            );

            echo json_encode(["status" => "ban_removed"]);
        } else {
            // add
            $_SESSION["banned_games"][] = $gameId;
            echo json_encode(["status" => "ban_added"]);
        }
        exit;
    }
}





/* ===== ADDED: ADMIN + BAN DEFAULTS ===== */
if (!isset($_SESSION['role']))
    $_SESSION['role'] = 'user';
if (!isset($_SESSION['is_banned']))
    $_SESSION['is_banned'] = 0;

/* ---- DATABASE CONNECTION ---- */
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error)
    die("Database connection failed: " . $conn->connect_error);

/* ---- AUTO LOGIN USING REMEMBER-ME ---- */
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    $token_raw = $_COOKIE['rememberme'];
    $stmt = $conn->prepare("SELECT user_id, username, role, is_banned, profile_img, remember_token FROM users WHERE remember_token IS NOT NULL");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['remember_token']) && password_verify($token_raw, $row['remember_token'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['is_banned'] = $row['is_banned'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';
            break;
        }
    }
}

/* ---- REDIRECT IF NOT LOGGED IN ---- */
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

/* ---- GET USER DATA ---- */
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* ===== ADDED ===== */
$is_admin = ($_SESSION['role'] === 'admin');

/* ---- Handle profile picture upload ---- */
/* ===== ADDED: BAN CHECK (WRAPS ORIGINAL LOGIC) ===== */
if ($_SESSION['is_banned'] == 0) {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/";
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = basename($_FILES['profile_pic']['name']);
        $target_path = $upload_dir . uniqid() . "_" . $file_name;

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($file_tmp, $target_path)) {
                $update = $conn->prepare("UPDATE users SET profile_img=? WHERE user_id=?");
                $update->bind_param("si", $target_path, $user_id);
                $update->execute();
                $_SESSION['profile_img'] = $target_path;
            }
        }
    }
}
/* ===== END ADDED ===== */

/* ---- Get updated user info ---- */
$user_query = $conn->prepare("SELECT username, role, is_banned, profile_img FROM users WHERE user_id=?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();
$profile_img = $user_data['profile_img'] ?? "/imgandgifs/login.png";

/* ===== ADDED ===== */
$_SESSION['role'] = $user_data['role'];
$_SESSION['is_banned'] = $user_data['is_banned'];
/* ===== END ADDED ===== */

/* ---- Get user's comments ---- */
$comments_query = $conn->prepare("SELECT c.content, c.created_at, g.title FROM comments c 
JOIN games g ON c.game_id=g.game_id 
WHERE c.user_id=? ORDER BY c.created_at DESC");
$comments_query->bind_param("i", $user_id);
$comments_query->execute();
$comments = $comments_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($username); ?>'s Profile</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- (YOUR ORIGINAL CSS â€“ UNCHANGED) -->
    <style>
        /* ---------- BASE ---------- */

        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');

        /* ----------- Alap CSS ------------- */
        :root {
            --primary: #1b171e;
            --secondary: #361d3b;
            --accent: #9557a1;
            --bg: #000000;
            --card-bg: rgba(142, 35, 193, 0.1);
            --hover-bg: #3f0c48;
            --text-light: #d9cddb;
            --text-dark: #222;
        }

        /* ----------- THEME STATES ----------- */
        body.dark {
            --primary: #0f0a15;
            --secondary: #1a1524;
            --accent: #bf32f1;
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --card-bg: rgba(191, 50, 241, 0.1);
            --hover-bg: rgba(191, 50, 241, 0.25);
            --text-light: #e6e0eb;
            --border: rgba(191, 50, 241, 0.15);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
        }

        body.bright {
            --primary: #f7f3e8;
            --secondary: #fcfaf2;
            --accent: #9b59b6;
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --card-bg: rgba(255, 255, 255, 0.4);
            --hover-bg: rgba(155, 89, 182, 0.12);
            --text-light: #2c2433;
            --border: rgba(155, 89, 182, 0.2);
            --glass: rgba(247, 243, 232, 0.8);
            --glass-strong: rgba(255, 255, 255, 0.95);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
        }

        /* ----------- THEME TOGGLE BUTTON ----------- */
        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            margin-left: 20px;
        }

        .theme-icon {
            width: 24px;
            height: 24px;
            pointer-events: none;
            transition: transform 0.3s ease, filter 0.3s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        /* ---------- BASE ---------- */
        body {
            font-family: "Poppins", sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-light);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background-attachment: fixed;
            transition: color 0.5s ease;
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

        /* ---------- HEADER ---------- */
        header {
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--glass);
            backdrop-filter: blur(30px);
            border-bottom: 1px solid var(--border);
            padding: 15px 40px;
            box-shadow: var(--shadow);
        }

        header a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        #logo {
            width: 130px;
            height: 90px;
            padding-top: 10px;
        }

        /* ---------- PROFILE CARD ---------- */
        /* ---------- PROFILE CARD ---------- */
        .profile-container {
            max-width: 1200px;
            margin: 40px auto;
            background: var(--glass);
            backdrop-filter: blur(25px);
            padding: 40px;
            border-radius: 32px;
            /* text-align: center; handled by children */
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.8s cubic-bezier(0.19, 1, 0.22, 1);
        }

        /* --- TOAST NOTIFICATIONS --- */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            background: var(--glass-strong);
            backdrop-filter: blur(15px);
            padding: 14px 24px;
            border-radius: 14px;
            border-left: 5px solid var(--accent);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            min-width: 280px;
            animation: toastIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 500;
        }

        .toast.success {
            border-left-color: #2ecc71;
        }

        .toast.error {
            border-left-color: #e74c3c;
        }

        @keyframes toastIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes toastOut {
            to {
                transform: translateX(120%);
                opacity: 0;
            }
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #profilePreview {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--border);
            transition: all 0.4s ease;
            cursor: pointer;
            box-shadow: var(--glow);
        }

        #profilePreview:hover {
            transform: scale(1.05);
            border-color: var(--accent);
        }

        .comment {
            background: var(--hover-bg);
            padding: 12px;
            margin: 15px 0;
            border-radius: 12px;
            color: var(--text-light);
            overflow-wrap: break-word;
        }



        .comment-item {
            background: var(--hover-bg);
            padding: 12px;
            margin: 15px 0;
            border-radius: 12px;
            color: var(--text-light);
            overflow-wrap: break-word;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Ensure comment text wraps */
        .comment-text {
            line-height: 1.6;
            max-height: calc(1.6em * 5);
            overflow: hidden;
            transition: max-height 0.35s ease;
            word-wrap: break-word;
            /* Ensure older browser support */
            overflow-wrap: break-word;
            word-break: break-word;
            /* Be aggressive with breaking to stay in box */
        }


        .comment-text.expanded {
            max-height: 200px;
            overflow-y: auto;
        }

        .toggle-btn {
            display: none;
            /* JS will show it if needed */
            align-self: flex-start;
            border: none;
            background: rgba(0, 0, 0, 0.08);
            padding: 4px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            color: var(--text-light);
            transition: background 0.25s ease, color 0.25s ease;
            margin: 0 auto;
        }

        .toggle-btn:hover {
            background: rgba(0, 0, 0, 0.15);
        }



        .ikonok {
            display: flex;
            gap: 30px;
        }

        .ikonok .menu-item img {
            transition: transform 0.3s ease;
        }

        .ikonok .menu-item:hover img {
            animation: bounce 0.4s ease;
        }

        @keyframes bounce {
            0% {
                transform: translateY(0);
            }

            30% {
                transform: translateY(-6px);
            }

            60% {
                transform: translateY(2px);
            }

            100% {
                transform: translateY(0);
            }
        }


        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-light);
            font-size: 14px;
        }

        .menu-item img {
            margin-bottom: 6px;
        }


        .favourites-list {
            text-align: center;

        }


        .fav-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin: 20px auto;
            max-width: 800px;
            display: flex;
            justify-content: center;
        }

        /* TABS */
        .tabs-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-light);
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent);
        }

        .tab-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .fav-card {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(5px);
            transition: 0.3s;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .fav-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glow);
            border-color: var(--accent);
        }

        /* Favorites with Image */
        .fav-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Simple Card (Banned) */
        .simple-card {
            justify-content: center;
            padding: 20px;
        }

        .fav-btn-open {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
            width: 100%;
            margin-top: auto;
        }

        .fav-btn-open:hover {
            filter: brightness(1.2);
            transform: scale(1.02);
        }

        /* LAYOUT */
        .profile-content-wrapper {
            display: flex;
            gap: 40px;
            text-align: left;
            margin-top: 40px;
        }

        .profile-main-column {
            flex: 1;
            /* Main content takes remaining space */
        }

        .profile-side-column {
            flex: 0 0 350px;
            /* Fixed width sidebar */
            border-left: 1px solid var(--border);
            padding-left: 30px;
        }

        .profile-container {
            max-width: 1200px;
            /* Widened from 800px */
            /* ... previous styles ... */
            margin: 40px auto;
            background: var(--glass);
            backdrop-filter: blur(25px);
            padding: 40px;
            border-radius: 32px;
            /* text-align: center; REMOVED to allow left align content */
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            animation: floatIn 1s cubic-bezier(0.19, 1, 0.22, 1);
        }

        /* Centered Header parts */
        .profile-container>h2,
        .profile-container>form,
        .profile-container>p,
        .profile-container>hr {
            text-align: center;
        }

        /* GRID OVERRIDES FOR SIDEBAR */
        .side-grid {
            grid-template-columns: 1fr;
            /* 1 column in sidebar */
            gap: 15px;
        }

        .side-card {
            flex-direction: row;
            /* Horizontal cards in sidebar */
            text-align: left;
            padding: 10px;
            /* Ensure content doesn't force width */
            min-width: 0;
        }

        .side-card img {
            width: 60px;
            height: 60px;
            margin-bottom: 0;
            flex-shrink: 0;
            /* Don't shrink image */
        }

        .side-card .fav-title {
            font-size: 0.9rem;
            margin: 0 10px;
            flex: 1;
            /* Handle text overflow */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            /* crucial for flexbox */
        }

        .side-card button {
            width: auto;
            padding: 5px 10px;
            font-size: 0.8rem;
            margin: 0;
            flex-shrink: 0;
            /* Don't shrink button */
        }

        /* Simple card in sidebar */
        .simple-card.side-card {
            padding: 15px;
            justify-content: space-between;
            align-items: center;
        }


        .ban-btn {
            background: #e74c3c;
        }

        .ban-btn:hover {
            background: #c0392b;
        }

        @media (max-width: 900px) {
            .profile-content-wrapper {
                flex-direction: column;
            }

            .profile-side-column {
                border-left: none;
                border-top: 1px solid var(--border);
                padding-left: 0;
                padding-top: 30px;
                flex: auto;
            }

            .side-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }

            .side-card {
                flex-direction: column;
                text-align: center;
            }

            .side-card img {
                width: 100%;
                height: 120px;
            }

            .side-card button {
                width: 100%;
            }
        }
    </style>
</head>

<body class="dark">

    <header class="navbar">
        <img src="/imgandgifs/catlogo.png" id="logo" width="150">
        <div class="ikonok">

            <a href="index.php" class="menu-item">
                <img src="/imgandgifs/home.svg" width="40">
                <span>Home</span>
            </a>

            <?php if ($is_admin): ?>
                <a href="admin.php" class="menu-item">
                    <img src="/imgandgifs/tool.svg" width="40">
                    <span>Admin</span>
                </a>
            <?php endif; ?>

            <a href="auth.php?action=logout" class="menu-item">
                <img src="/imgandgifs/log-out.svg" width="40">
                <span>Logout</span>
            </a>




            <button id="themeToggle" class="theme-toggle"
                style="background: none; border: none; cursor: pointer; padding: 0;">
                <img id="themeIcon" src="imgandgifs/sun.svg" alt="Toggle theme" class="theme-icon"
                    style="width: 35px; height: 35px; filter: drop-shadow(var(--glow)); transition: transform 0.4s ease;">
            </button>

        </div>


    </header>

    <!-- TOAST CONTAINER -->
    <div id="toast-container" class="toast-container"></div>

    <div class="profile-container">
        <h2><?php echo htmlspecialchars($username); ?>'s Profile</h2>

        <!-- ===== ADDED ===== -->
        <?php if ($_SESSION['is_banned']): ?>
            <p style="color:red;">ðŸš« You are banned</p>
        <?php endif; ?>
        <!-- ===== END ADDED ===== -->

        <form id="profileForm" method="POST" enctype="multipart/form-data">
            <input id="profilePicInput" type="file" name="profile_pic" accept="image/*" hidden>
            <img id="profilePreview" src="<?php echo htmlspecialchars($profile_img); ?>">
        </form>

        <hr>

        <div class="profile-content-wrapper">

            <!-- LEFT COLUMN: COMMENTS -->
            <div class="profile-main-column">
                <h3>Your Comments</h3>
                <?php if ($comments->num_rows > 0): ?>
                    <?php while ($row = $comments->fetch_assoc()): ?>
                        <div class="comment-item">
                            <div class="comment-text">
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <?php echo nl2br(htmlspecialchars($row['content'])); ?><br>
                                <small><?php echo htmlspecialchars($row['created_at']); ?></small>
                            </div>

                            <button class="toggle-btn">...</button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>You haven't commented yet.</p>
                <?php endif; ?>
            </div>

            <!-- RIGHT COLUMN: GAMES -->
            <div class="profile-side-column">
                <div class="favourites-list">

                    <!-- TABS FOR SWITCHING VIEWS -->
                    <div class="tabs-container">
                        <button class="tab-btn active" onclick="switchTab('favs')">Favorites</button>
                        <button class="tab-btn" onclick="switchTab('bans')">Banned</button>
                    </div>

                    <!-- FAVORITES SECTION -->
                    <div id="favs-section" class="tab-content active">
                        <?php
                        $favs = $_SESSION["favourites"] ?? [];
                        if (empty($favs)) {
                            echo "<p>No favorites.</p>";
                        } else {
                            echo '<div class="fav-grid side-grid">';
                            foreach ($favs as $id) {
                                // Fetch Title AND Image
                                $q = $conn->prepare("SELECT title, main_image FROM games WHERE game_id=?");
                                $q->bind_param("i", $id);
                                $q->execute();
                                $res = $q->get_result()->fetch_assoc();
                                if ($res) {
                                    $img = !empty($res["main_image"]) ? htmlspecialchars($res["main_image"]) : '/imgandgifs/game_placeholder.png';
                                    echo ' <div class="fav-card side-card">
                                            <img src="' . $img . '" class="fav-img" alt="Game Cover">
                                            <div class="fav-title">' . htmlspecialchars($res["title"]) . '</div>
                                            <button class="fav-btn-open" onclick="window.location.href=\'game.php?id=' . $id . '\'"> Open </button>
                                        </div>';
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>

                    <!-- BANNED GAMES SECTION -->
                    <div id="bans-section" class="tab-content" style="display: none;">
                        <?php
                        $bans = $_SESSION["banned_games"] ?? [];
                        if (empty($bans)) {
                            echo "<p style='text-align:center; padding: 20px; opacity: 0.6;'>No banned games.</p>";
                        } else {
                            echo '<div class="fav-grid side-grid">';
                            foreach ($bans as $id) {
                                // Fetch Title ONLY
                                $q = $conn->prepare("SELECT title FROM games WHERE game_id=?");
                                $q->bind_param("i", $id);
                                $q->execute();
                                $res = $q->get_result()->fetch_assoc();
                                if ($res) {
                                    $safeTitle = htmlspecialchars($res["title"]);
                                    $jsName = addslashes($safeTitle);
                                    echo ' <div class="fav-card simple-card side-card" id="ban-card-' . $id . '">
                                            <div class="fav-title" style="font-weight: 700; width: 100%; text-align: left;">' . $safeTitle . '</div>
                                            <div style="display:flex; gap:8px; width:100%;">
                                                <button class="fav-btn-open" onclick="window.location.href=\'game.php?id=' . $id . '\'" style="flex:1;">View</button>
                                                <button class="fav-btn-open" onclick="unbanGame(' . $id . ', \'' . $jsName . '\')" 
                                                    style="flex:0 0 45px; background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.2); display: flex; align-items: center; justify-content: center;">
                                                    <img src="/imgandgifs/rotate-ccw.svg" style="width: 18px; filter: invert(36%) sepia(84%) saturate(1637%) hue-rotate(336deg) brightness(97%) contrast(87%);">
                                                </button>
                                            </div>
                                        </div>';
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>

        </div>


        <script>
            document.getElementById("profilePreview").onclick = () =>
                document.getElementById("profilePicInput").click();

            document.getElementById("profilePicInput").onchange = () =>
                document.getElementById("profileForm").submit();

            // TOAST SYSTEM
            function showToast(message, type = 'success') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `<span>${message}</span>`;

                setTimeout(() => {
                    toast.style.animation = 'toastOut 0.5s forwards';
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                container.appendChild(toast);
            }

            // TAB SWITCHER
            function switchTab(tabName) {
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

                // Show selected
                if (tabName === 'favs') {
                    document.getElementById('favs-section').style.display = 'block';
                    document.querySelector('button[onclick="switchTab(\'favs\')"]').classList.add('active');
                } else {
                    document.getElementById('bans-section').style.display = 'block';
                    document.querySelector('button[onclick="switchTab(\'bans\')"]').classList.add('active');
                }
            }

            function unbanGame(gameId, gameName) {
                if (!confirm(`Are you sure you want to restore "${gameName}"?`)) return;

                fetch("profile.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ toggle_ban: String(gameId) })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === "ban_removed") {
                            const card = document.getElementById('ban-card-' + gameId);
                            if (card) {
                                card.style.transform = 'scale(0.9) opacity(0)';
                                card.style.pointerEvents = 'none';
                                setTimeout(() => {
                                    card.remove();
                                    const grid = document.querySelector('#bans-section .fav-grid');
                                    if (grid && grid.children.length === 0) {
                                        document.getElementById('bans-section').innerHTML = "<p style='text-align:center; padding: 20px; opacity: 0.6;'>No banned games left.</p>";
                                    }
                                }, 400);
                            }
                            showToast(`"${gameName}" restored!`);
                        }
                    })
                    .catch(err => {
                        console.error("UNBAN ERROR:", err);
                        showToast("Error restoring game", "error");
                    });
            }

            // THEME CHANGER
            const themeToggleBtn = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');

            // Load saved theme or default to dark
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.body.classList.add(savedTheme);

            function updateThemeUI() {
                const isDark = document.body.classList.contains('dark');

                // Swap icons
                themeIcon.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";

                // Animation + glow
                themeIcon.style.transform = isDark
                    ? "rotate(180deg) scale(1)"
                    : "rotate(0deg) scale(1.1)";

                themeIcon.style.filter = isDark
                    ? "drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))"
                    : "drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))";
            }

            themeToggleBtn.addEventListener('click', (e) => {
                e.preventDefault();

                if (document.body.classList.contains('dark')) {
                    document.body.classList.replace('dark', 'bright');
                    localStorage.setItem('theme', 'bright');
                } else {
                    document.body.classList.replace('bright', 'dark');
                    localStorage.setItem('theme', 'dark');
                }

                updateThemeUI();
            });

            // Initialize on load
            updateThemeUI();



            document.querySelectorAll('.comment-item').forEach(item => {
                const text = item.querySelector('.comment-text');
                const btn = item.querySelector('.toggle-btn');

                if (!text || !btn) return;

                const lineHeight = parseFloat(getComputedStyle(text).lineHeight);
                const maxLines = 5;
                const maxHeight = lineHeight * maxLines;

                // Show button only if comment is longer than 5 lines
                if (text.scrollHeight > maxHeight) {
                    btn.style.display = "inline-block";
                }

                // Toggle expand/collapse
                btn.addEventListener('click', () => {
                    text.classList.toggle('expanded');
                });
            });











        </script>

</body>

</html>