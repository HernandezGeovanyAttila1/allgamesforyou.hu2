<?php
// ------------------ DEBUGGING ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ START SESSION ------------------
session_start();

// ------------------ AUTO-LOGIN (remember-me) ------------------
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// helper
function set_remember_cookie_index($value)
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    setcookie("rememberme", $value, time() + 86400 * 30, "/", "", $secure, true);
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
                $_SESSION['profile_img'] = $row['profile_img'] ?? "/imgandgifs/login.png";
                // rotate token
                $new_selector = bin2hex(random_bytes(9));
                $new_token = bin2hex(random_bytes(33));
                $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                $stmt2->execute();
                set_remember_cookie_index($new_selector . ":" . $new_token);
            }
        }
    }
}

// ------------------ HANDLE LOGOUT ------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    setcookie("rememberme", "", time() - 3600, "/", "", false, true);
    header("Location: index.php");
    exit();
}

// ------------------ HANDLE COMMENT SUBMISSION ------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['comment_submit']) && isset($_SESSION['user_id'])) {
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content']);

    if ($game_id > 0 && !empty($content)) {
        $stmt = $conn->prepare("INSERT INTO comments (game_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iis", $game_id, $user_id, $content);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ------------------ FETCH GAMES WITH CATEGORIES ------------------
$games = [];
$games_sql = "SELECT g.*, GROUP_CONCAT(gc.category SEPARATOR ',') AS categories
              FROM games g
              LEFT JOIN game_categories gc ON g.game_id = gc.game_id
              GROUP BY g.game_id";
if ($result = $conn->query($games_sql)) {
    while ($row = $result->fetch_assoc()) {
        $games[] = $row;
    }
} else {
    die("Error fetching games: " . $conn->error);
}

// ------------------ FETCH ALL CATEGORIES ------------------
$categories = [];
$cat_sql = "SELECT DISTINCT category FROM game_categories ORDER BY category ASC";
if ($cat_result = $conn->query($cat_sql)) {
    while ($cat = $cat_result->fetch_assoc()) {
        $categories[] = $cat['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Games For You</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

    <style>
        /*H2 STYLE DONT DELETE*/
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
            --primary: #1b171e;
            --secondary: #361d3b;
            --accent: #9557a1;
            --bg: #000000;
            --card-bg: rgba(142, 35, 193, 0.1);
            --hover-bg: #3f0c48;
            --text-light: #d9cddb;
        }

        body.bright {
            --primary: #faf0ff;
            --secondary: #ffffff;
            --accent: #ddc9ef;
            --bg: #fffbe6;
            --card-bg: rgb(207 169 241 / 15%);
            --hover-bg: #efdcf3;
            --text-light: #222;
        }


        /* ----------- THEME TOGGLE BUTTON ----------- */
        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .theme-icon {
            width: 24px;
            /* adjust size */
            height: 24px;
            pointer-events: none;
            /* prevent image blocking the click */
            transition: transform 0.3s ease, filter 0.3s ease;
        }


        .theme-toggle:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }






        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: var(--text-light);
        }

        header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            /*background-image:url("imgandgifs/new_bg.png");*/
            background-size: cover;
            background-position: top center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }


        .menu-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            border-bottom: 1px solid #ddd;
            position: sticky;
            top: 0;
            z-index: 1000;
            font-family: Arial, 'Inter', sans-serif;
        }

        .menu-bar .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }

        .menu-bar .menu-items {
            list-style: none;
            display: flex;
            gap: 25px;
            margin: 0;
            padding: 0;
            margin-left: 20px;
        }

        .menu-bar .menu-items li a {
            text-decoration: none;
            color: #a462e2;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: bold;
            font-size: 1.26rem;
        }


        .menu-bar .menu-items li a:hover {
            background-color: #b79dc2;
            /* blue highlight */
            color: #ffffff;
            /* text turns white */
            transform: translateY(-2px);
            /* subtle lift effect */
            box-shadow: 0 4px 6px rgba(0, 123, 255, 0.3);
            /* soft shadow */
        }

        /* Responsive: collapse menu on small screens */
        @media (max-width: 768px) {
            .menu-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .menu-bar .menu-items {
                flex-direction: column;
                width: 100%;
                gap: 10px;
                margin-top: 10px;
            }
        }




        header img.logo {
            max-width: 150px;
            height: auto;
        }

        .search-box {
            margin-left: 42px;
            flex: 1 1 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            max-width: 300px;
            min-width: 120px;
            padding: 10px 15px;
            border-radius: 4px;
            border: 2px solid #f0a6ff;
            outline: none;
            transition: 0.3s;
            font-size: 1em;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 0 10px rgba(240, 166, 255, 0.5), inset 0 0 5px rgba(240, 166, 255, 0.2);
        }

        .search-box input::placeholder {
            color: rgba(240, 166, 255, 0.8);
            font-size: 0.85em;
            text-shadow: 0 0 5px rgba(240, 166, 255, 0.5);
        }

        .search-box input:focus {
            transform: scale(1.02);
            box-shadow: 0 0 20px #f0a6ff, inset 0 0 10px #f0a6ff;
            border-color: #ffffff;
        }

        .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.3s;
            aspect-ratio: 1/1;
            object-fit: cover;
        }

        .profile img:hover {
            transform: scale(1.1);
        }

        .container {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 15px;
            padding: 15px;
            height: calc(100vh - 90px);
        }

        .sidebar {
            background: var(--hover-bg);
            padding: 20px;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            max-height: calc(100vh - 120px);
        }

        .sidebar h3 {
            margin-bottom: 10px;
            text-align: center;
            font-weight: 600;
        }

        .sidebar a {
            text-decoration: none;
            color: var(--text-light);
            padding: 8px 12px;
            border-radius: 8px;
            display: block;
            transition: 0.3s;
            cursor: pointer;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--accent);
            font-weight: bold;
        }

        .main {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .add-game-btn {
            padding: 10px 15px;
            background: #db8e1d;
            color: #ebe6ed;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            align-self: flex-start;
            margin-right: 20px;
        }

        .add-game-btn:hover {
            background: #fb8c00;
        }

        .featured {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .featured h2 {
            font-family: "Orbitron", system-ui;
            text-transform: uppercase;
            color: #e170ff;
            letter-spacing: 0.15em;
            text-shadow: 0 0 6px #eba1ff;
        }

        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .game-card {
            background: var(--hover-bg);
            border-radius: 12px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: 0.3s;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            position: relative;
            /* fontos a menühöz */
        }

        .game-card:hover {
            transform: translateY(-5px);
            background: var(--accent);
        }

        .game-card img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .game-card h3 {
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .game-card p {
            font-size: 0.9em;
            color: #b397b8;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media(max-width: 1024px) {
            .container {
                grid-template-columns: 180px 1fr;
            }
        }

        @media(max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                height: auto;
            }

            .sidebar {
                flex-direction: row;
                overflow-x: auto;
                padding: 10px;
                gap: 8px;
                max-height: unset;
            }

            .game-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media(max-width: 480px) {
            .game-grid {
                grid-template-columns: 1fr;
            }

            header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box input {
                width: 100%;
            }
        }

        /* ----------- három pontos gomb ----------- */
        .card-menu-btn {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.4);
            border: none;
            color: white;
            font-size: 20px;
            border-radius: 8px;
            padding: 4px 8px;
            cursor: pointer;
            z-index: 100;
            /* biztosan a link fölött */
        }
        
        .fav-btn {
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            color: white;
            transition: 0.2s;
        }
        
        .fav-btn.active {
            color: gold;
            transform: scale(1.2);
        }

        
        
        

        /* lenyíló menü */
        .card-menu {
            position: absolute;
            bottom: 48px;
            right: 12px;
            background: #1f1f1f;
            border-radius: 10px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
            display: none;
            flex-direction: column;
            min-width: 160px;
            z-index: 101;
            /* mindig a gomb fölött */
        }

        .card-menu button {
            background: none;
            border: none;
            color: white;
            padding: 10px 12px;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
        }

        .card-menu button:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .card-menu .danger {
            color: #ff6b6b;
        }

        /* --- FIX a kattintásra --- */
        .game-card>a {
            pointer-events: none;
            /* link alól elvesszük a kattintást */
        }

        .game-card>a img,
        .game-card>a h3,
        .game-card>a p {
            pointer-events: auto;
            /* a link belseje továbbra is kattintható */
        }

        .card-menu,
        .card-menu button {
            pointer-events: auto;
        }
        
        /*FAVOURITE GAME*/
        .toast { position: fixed; bottom: 20px; right: 20px; background: #333; color: white; padding: 12px 18px; border-radius: 6px; opacity: 0; transition: opacity 0.4s ease; z-index: 9999; } .toast.show { opacity: 1; }

        /* ----------- Games Modal ----------- */
        .games-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #1f1f1f, #2d1b33);
            border: 1px solid #9557a1;
            border-radius: 15px;
            padding: 20px;
            z-index: 2000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .games-modal h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #fff;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }

        .games-modal-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }

        .games-modal-item {
            background: rgba(149, 87, 161, 0.2);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .games-modal-item:hover {
            background: var(--accent);
            transform: scale(1.05);
            border-color: #fff;
        }

        .games-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            color: #aaa;
            font-size: 24px;
            cursor: pointer;
        }

        .games-modal-close:hover {
            color: #fff;
        }

        /* Overlay back */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1999;
        }

        /* ----------- Infinite Scroll Carousel "Moving Belt" ----------- */
        .carousel-container {
            width: 100%;
            overflow: hidden;
            position: relative;
            padding: 20px 0;
            /* Mask for fade effect on sides */
            mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
            -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        }

        .carousel-track {
            display: flex;
            gap: 25px;
            width: max-content;
            /* animation: scroll 40s linear infinite; REMOVED FOR JS SMOOTHNESS */
            cursor: grab;
            user-select: none;
        }

        .carousel-track:active {
            cursor: grabbing;
        }

        .carousel-track.dragging {
            animation-play-state: paused !important;
        }

        .carousel-container {
            width: 100%;
            overflow: hidden;
            /* Hide scrollbar, use JS for drag */
            position: relative;
            padding: 20px 0;
            /* Mask for fade effect on sides */
            mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
            -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        }

        .carousel-track:hover {
            animation-play-state: paused;
        }

        @keyframes scroll {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        /* Enhanced Card Visuals */
        .featured-card {
            min-width: 240px;
            max-width: 240px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            display: block;
            text-decoration: none;
            color: var(--text-light);
            flex-shrink: 0;
            user-drag: none;
            -webkit-user-drag: none;
            user-select: none;
            -moz-user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
            
            /* Prevent shrinking in flex container */
        }

        .featured-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5), 0 0 15px var(--accent);
            border-color: rgba(255, 255, 255, 0.4);
            z-index: 10;
        }

        .featured-card img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            user-drag: none;
            -webkit-user-drag: none;
            user-select: none;
            -moz-user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            
        }

        .featured-card h4 {
            padding: 12px;
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>

<body class="dark">


    <header>
        <img src="imgandgifs/catlogo.png" alt="logo" class="logo">

        <!-- Menu Bar -->
        <nav class="menu-bar">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="add_games.php" class="add-game-btn">Add New Game</a>
            <?php endif; ?>

            <ul class="menu-items">
                <li><a href="#" id="gamesMenuBtn">Games</a></li>
                <li><a href="about">About</a></li>
            </ul>
        </nav>



        <div class="search-box">
            <input id="searchInput" type="text" placeholder="[ SEARCH_START ]">
        </div>

        <div class="profile" style="display:flex; align-items:center; gap:15px;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'message.php' : 'auth.php?redirect=message.php'; ?>"
                    class="msg-btn"
                    style="background: #774280; padding: 10px 14px; border-radius: 10px; font-weight: 600; color: white; text-decoration: none; transition: 0.3s;">Message</a>

                <a href="profile.php">
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/moving_login.gif'); ?>"
                        alt="profile">
                </a>
            <?php else: ?>
                <a href="auth.php"><img src="imgandgifs/moving_login.gif" alt="login"></a>
            <?php endif; ?>
        </div>

        <button id="themeToggle" class="theme-toggle">
            <img src="imgandgifs/bright_mode.webp" alt="Toggle theme" class="theme-icon">
        </button>





    </header>

    <div class="container">
        <nav class="sidebar">


            <h3>Categories</h3>
            <a data-category="all" class="active">All</a>
            <?php foreach ($categories as $cat): ?>
                <a data-category="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></a>
            <?php endforeach; ?>
        </nav>

        <main class="main">


            <section class="featured">
                <h2>Hidden Gems</h2>

                <div class="carousel-container">
                    <div class="carousel-track">
                        <?php
                        $apiKey = 'a96095e89e614dda9c35a44bfb14c63f';

                        // --- Expanded Pool of "Hidden Gems" (Underrated Gold Games) ---
                        $allGemIds = [
                            74528,
                            4432,
                            3543,
                            11931,
                            41494,
                            4062,
                            2191,
                            4353,
                            2953,
                            16181,
                            3012,
                            3042,
                            4160,
                            34220,
                            25097,
                            4235,
                            32,
                            44690,
                            25827,
                            2462,
                            1030,
                            21835,
                            23565,
                            3070,
                            3841,
                            4161,
                            4514,
                            50655,
                            12536,
                            10213
                        ];

                        // Shuffle and pick 10
                        shuffle($allGemIds);
                        $selectedIds = array_slice($allGemIds, 0, 10);
                        $targetIds = implode(',', $selectedIds);

                        $apiUrl = "https://api.rawg.io/api/games?key={$apiKey}&ids={$targetIds}&page_size=10";

                        // Update Section Title via PHP/JS if needed, but we changed the H2 above.
                        // echo "<script>document.querySelector('.featured h2').innerText = 'Hidden Gems';</script>";
                        

                        $featuredGames = [];
                        $apiSuccess = false;

                        // --- 1. Fetch from API ---
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $apiUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($response && $httpCode === 200) {
                            $data = json_decode($response, true);
                            if (isset($data['results']) && !empty($data['results'])) {
                                $featuredGames = $data['results'];
                                $apiSuccess = true;
                            }
                        }

                        // --- 3. Display Games in Loop (Duplicated for Seamless Scroll) ---
                        if (empty($featuredGames)) {
                            echo "<p style='text-align:center; width:100%; color:#ccc;'>No games found.</p>";
                        } else {
                            // DUPLICATE THE ARRAY to create seamless loop (belt effect)
                            $displayGames = array_merge($featuredGames, $featuredGames);

                            foreach ($displayGames as $game):
                                // Handle Categories/Genres
                                $g_cat_str = "General";
                                if (isset($game['genres']) && is_array($game['genres'])) {
                                    $g_names = array_map(function ($g) {
                                        return $g['name'];
                                    }, $game['genres']);
                                    if (!empty($g_names))
                                        $g_cat_str = htmlspecialchars(implode('|', $g_names));
                                }

                                $bg_image = !empty($game['background_image']) ? $game['background_image'] : 'imgandgifs/logo.png';
                                $game_name = $game['name'];

                                // Determine Link
                                if (isset($game['is_local']) && $game['is_local']) {
                                    // Local link
                                    $game_link = "game.php?id=" . $game['id'];
                                    $target = "_self";
                                } else {
                                    // RAWG link
                                    $slug = $game['slug'];
                                    $game_link = "https://rawg.io/games/" . $slug;
                                    $target = "_blank";
                                }
                                ?>
                                <a href="<?php echo $game_link; ?>" target="<?php echo $target; ?>" class="featured-card"
                                    data-category="<?php echo $g_cat_str; ?>">
                                    <img draggable="false"
                                    src="<?php echo htmlspecialchars($bg_image); ?>"
                                        alt="<?php echo htmlspecialchars($game_name); ?>">
                                    <h4><?php echo htmlspecialchars($game_name); ?></h4>
                                </a>
                                <?php
                            endforeach;
                        }
                        ?>
                    </div>
                </div>
            </section>




            <section class="game-grid" id="gameGrid">
                <?php foreach ($games as $game):
                    $categories_str = $game['categories'] ?? '';
                    $categories_array = explode(',', $categories_str);
                    $data_category = htmlspecialchars(implode('|', $categories_array));
                    ?>
                    <div class="game-card" data-category="<?php echo $data_category; ?>"
                        data-game-id="<?php echo $game['game_id']; ?>">

                        <!-- három pontos gomb -->
                        <button class="card-menu-btn"
                            onclick="toggleCardMenu(event, <?php echo $game['game_id']; ?>)">⋯</button>

                        <!-- lenyíló menü -->
                        <div class="card-menu" id="menu-<?php echo $game['game_id']; ?>">
                            <!--<button onclick="shareGame(event, <?php echo $game['game_id']; ?>)">🔗 Share</button>-->
                            
                           <button 
                            class="fav-btn <?= in_array($game['game_id'], $_SESSION['favourites'] ?? []) ? 'active' : '' ?>"
                            onclick="toggleFavourite(event, <?= $game['game_id'] ?>)"
                        >
                            ⭐Favourite
                        </button>

                            
                            <button onclick="copyGameLink(event, <?php echo $game['game_id']; ?>)">📋 Copy link</button>
                            
                            
                            <button class="danger" onclick="disableGame(event, <?php echo $game['game_id']; ?>)">🚫
                                Disable</button>
                        </div>

                        <!-- kattintható tartalom -->
                        <a href="game.php?id=<?php echo $game['game_id']; ?>" style="text-decoration:none;color:inherit;">
                            <img src="<?php echo htmlspecialchars($game['main_image']); ?>">
                            <h3><?php echo htmlspecialchars($game['title']); ?></h3>
                            <p><?php echo htmlspecialchars($game['description']); ?></p>
                        </a>
                    </div>

                <?php endforeach; ?>
            </section>
        </main>
    </div>

    <!-- Games Categories Modal -->
    <div class="modal-overlay" id="gamesModalOverlay"></div>
    <div class="games-modal" id="gamesModal">
        <button class="games-modal-close" onclick="closeGamesModal()">×</button>
        <h2>Browse Categories</h2>
        <div class="games-modal-grid">
            <a class="games-modal-item" onclick="selectModalCategory('all')">All Games</a>
            <?php foreach ($categories as $cat): ?>
                <a class="games-modal-item" onclick="selectModalCategory('<?php echo htmlspecialchars($cat); ?>')">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="toast" style="
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.8);
    color: #fff;
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 14px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    z-index: 1000;
">Copied!</div>



    <script>
    // favourite game
    
    //console.log("toggleFavourite FUT:", gameId);

    
    let favourites = JSON.parse(localStorage.getItem("favourites")) || [];



    
    
    
    // NEM CSUKÓDIK LE EGYBŐL FUNKCIÓ
        function toggleCardMenu(event, gameId) {
    event.preventDefault();
    event.stopPropagation();

    const menu = document.getElementById('menu-' + gameId);
    const allMenus = document.querySelectorAll('.card-menu');

    // Először csukjon be minden másik menüt
    allMenus.forEach(m => {
        if (m !== menu) {
            m.style.display = 'none';
        }
    });

    // Majd toggle az aktuálisra
    menu.style.display = (menu.style.display === 'flex') ? 'none' : 'flex';
}

// Ha bárhová kattintunk a dokumentumon, csukjon be minden menü
document.addEventListener('click', () => {
    document.querySelectorAll('.card-menu').forEach(menu => {
        menu.style.display = 'none';
    });
});


        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.opacity = 1;
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(() => {
                toast.style.opacity = 0;
            }, 1500);
        }


    // KEDVENC FUNKCIÓ
   function toggleFavourite(event, gameId) {
    event.stopPropagation();

    gameId = String(gameId);

    fetch("profile.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ toggle: gameId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "added") {
            event.target.classList.add("active");
            showToast("Added to favourites");
        } else {
            event.target.classList.remove("active");
            showToast("Removed from favourites");
        }
    })
    .catch(err => console.error("FAVOURITE ERROR:", err));
}



// FAVOURITE ÜZENET
function showToast(message) {
    const toast = document.createElement("div");
    toast.className = "toast";
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add("show"), 10);

    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 400);
    }, 2000);
}





        // ================= COPY LINK =================
        function copyGameLink(event, gameId) {
            event.preventDefault();
            const url = `${location.origin}/game.php?id=${gameId}`;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(() => showToast('Copied!'))
                    .catch(() => fallbackCopy(url));
            } else {
                fallbackCopy(url);
            }

            function fallbackCopy(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showToast('Copied!');
                } catch (err) {
                    showToast('Failed to copy');
                }
                document.body.removeChild(textarea);
            }
        }

        // THEME CHANGER
        const toggleBtn = document.getElementById("themeToggle");

        // Load saved theme
        const savedTheme = localStorage.getItem("theme") || "dark";
        document.body.classList.add(savedTheme);

        // Function to set icon based on current theme
        function setThemeIcon() {
            const isDark = document.body.classList.contains("dark");
            const iconPath = isDark ? "imgandgifs/bright_mode.webp" : "imgandgifs/dark_mode.webp";
            toggleBtn.innerHTML = `<img src="${iconPath}" alt="Toggle theme" class="theme-icon">`;
        }

        // Set icon on page load
        setThemeIcon();

        // Toggle on click
        toggleBtn.addEventListener("click", () => {
            if (document.body.classList.contains("dark")) {
                document.body.classList.replace("dark", "bright");
                localStorage.setItem("theme", "bright");
            } else {
                document.body.classList.replace("bright", "dark");
                localStorage.setItem("theme", "dark");
            }

            setThemeIcon(); // refresh icon
        });




        /*
        // ================= SHARE =================
        function shareGame(event, gameId) {
            event.preventDefault();
            const url = `${location.origin}/game.php?id=${gameId}`;
        
            if (navigator.share) {
                navigator.share({
                    title: 'Check out this game!',
                    text: 'I found this game and thought you might like it!',
                    url: url
                }).catch(() => copyGameLink(event, gameId)); // fallback: copy
            } else {
                copyGameLink(event, gameId); // fallback copy
            }
        }
        */
        // ================= DISABLE =================
        function disableGame(event, gameId) {
            event.preventDefault();
            if (!confirm('Disable this game?')) return;

            const card = document.querySelector(`.game-card[data-game-id='${gameId}']`);
            if (card) card.style.display = 'none';
        }

        // ================= SEARCH FUNCTIONALITY =================
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const filter = this.value.toLowerCase();
                const cards = document.querySelectorAll('.game-card');

                cards.forEach(card => {
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    if (title.includes(filter)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
                // Filter Featured
                document.querySelectorAll('.featured-card').forEach(card => {
                    const title = card.querySelector('h4').textContent.toLowerCase();
                    card.style.display = title.includes(filter) ? 'block' : 'none';
                });
            });
        }

        // ================= CATEGORY FILTERING =================
        const categoryLinks = document.querySelectorAll('.sidebar a[data-category]');

        categoryLinks.forEach(link => {
            link.addEventListener('click', function () {
                // Remove active class from all
                categoryLinks.forEach(l => l.classList.remove('active'));
                // Add to current
                this.classList.add('active');

                const selectedCat = this.getAttribute('data-category');

                // Helper
                const filterEl = (el, type) => {
                    const catStr = el.getAttribute('data-category');
                    if (!catStr) return;
                    const categories = catStr.split('|');

                    const match = (selectedCat === 'all' || categories.includes(selectedCat));

                    if (match) el.style.display = (type === 'grid') ? 'flex' : 'block';
                    else el.style.display = 'none';
                };

                // Filter Grid
                document.querySelectorAll('.game-card').forEach(c => filterEl(c, 'grid'));
                // Filter Featured
                document.querySelectorAll('.featured-card').forEach(c => filterEl(c, 'featured'));
            });
        });

        // ================= GAMES MODAL =================
        const gamesMenuBtn = document.getElementById('gamesMenuBtn');
        const gamesModal = document.getElementById('gamesModal');
        const gamesModalOverlay = document.getElementById('gamesModalOverlay');

        if (gamesMenuBtn) {
            gamesMenuBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openGamesModal();
            });
        }

        function openGamesModal() {
            gamesModal.style.display = 'block';
            gamesModalOverlay.style.display = 'block';
        }

        function closeGamesModal() {
            gamesModal.style.display = 'none';
            gamesModalOverlay.style.display = 'none';
        }

        if (gamesModalOverlay) {
            gamesModalOverlay.addEventListener('click', closeGamesModal);
        }

        function selectModalCategory(cat) {
            closeGamesModal();
            // Use existing logic
            const sidebarLink = document.querySelector(`.sidebar a[data-category='${cat}']`);
            if (sidebarLink) {
                sidebarLink.click();
                // Scroll to grid
                document.querySelector('.main').scrollIntoView({ behavior: 'smooth' });
            }
        }
    </script>

    <script>
        // --- REFINED DRAGGABLE JS BELT ---
        const track = document.querySelector('.carousel-track');
        const container = document.querySelector('.carousel-container');

        let isDragging = false;
        let startX;
        let scrollLeft;
        let positionX = 0;
        let speed = 0.8; // Pixels per frame
        let isHovered = false;

        // Function to handle seamless loop reset
        function checkLoop() {
            const trackWidth = track.scrollWidth;
            const halfWidth = trackWidth / 2;

            // If we scroll past the first set of items (halfway), jump back
            if (Math.abs(positionX) >= halfWidth) {
                if (positionX < 0) {
                    positionX += halfWidth;
                } else {
                    positionX -= halfWidth;
                }
            }
        }

        function animate() {
            if (!isDragging && !isHovered) {
                positionX -= speed;
                checkLoop();
                track.style.transform = `translateX(${positionX}px)`;
            }
            requestAnimationFrame(animate);
        }

        // Start animation loop
        requestAnimationFrame(animate);

        // Hover pause
        container.addEventListener('mouseenter', () => isHovered = true);
        container.addEventListener('mouseleave', () => isHovered = false);

        // Mouse events
        track.addEventListener('mousedown', (e) => {
            isDragging = true;
            track.classList.add('dragging');
            startX = e.pageX;
            scrollLeft = positionX;
        });

        window.addEventListener('mouseup', () => {
            if (!isDragging) return;
            isDragging = false;
            track.classList.remove('dragging');
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            const x = e.pageX;
            const walk = (x - startX);
            positionX = scrollLeft + walk;

            checkLoop(); // Handle loop while dragging
            track.style.transform = `translateX(${positionX}px)`;
        });

        // Touch events
        track.addEventListener('touchstart', (e) => {
            isDragging = true;
            track.classList.add('dragging');
            startX = e.touches[0].pageX;
            scrollLeft = positionX;
        });

        window.addEventListener('touchend', () => {
            if (!isDragging) return;
            isDragging = false;
            track.classList.remove('dragging');
        });

        window.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            const x = e.touches[0].pageX;
            const walk = (x - startX);
            positionX = scrollLeft + walk;

            checkLoop();
            track.style.transform = `translateX(${positionX}px)`;
        });

    </script>
</body>

</html>