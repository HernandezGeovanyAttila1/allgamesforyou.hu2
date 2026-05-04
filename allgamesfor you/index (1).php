<?php
// ------------------ DEBUGGING ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ START SESSION ------------------
session_start();

// Patch default profile image if it's the old one
if (isset($_SESSION['profile_img']) && ($_SESSION['profile_img'] === '/imgandgifs/login.png' || $_SESSION['profile_img'] === 'imgandgifs/login.png' || $_SESSION['profile_img'] === 'imgandgifs/profile.pnp')) {
    $_SESSION['profile_img'] = 'imgandgifs/moving_login.gif';
}

// ------------------ AUTO-LOGIN (remember-me) ------------------
require_once 'db.php';

// -----------------------------
// CONFIG
// -----------------------------
define('SECRET_KEY', 'my_secret_key_123'); // change to a random string
$baseUrl = 'http://yourwebsite.com/short.php'; // replace with your actual domain and file name

// -----------------------------
// FUNCTIONS
// -----------------------------
function encryptUrl($url)
{
    return rtrim(strtr(base64_encode($url ^ SECRET_KEY), '+/', '-_'), '=');
}

function decryptUrl($encoded)
{
    $decoded = base64_decode(strtr($encoded, '-_', '+/'));
    return $decoded ^ SECRET_KEY;
}

function generateRandomLink($targetUrl)
{
    $randomPrefix = bin2hex(random_bytes(3)); // 6-character random prefix
    $encoded = rtrim(strtr(base64_encode($targetUrl), '+/', '-_'), '=');
    return $GLOBALS['baseUrl'] . '?code=' . $randomPrefix . '-' . $encoded;
}

// -----------------------------
// HANDLE REDIRECT
// -----------------------------
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    // Extract the encoded part after the prefix
    $parts = explode('-', $code, 2);
    $encoded = $parts[1] ?? '';
    if ($encoded) {
        $target = base64_decode(strtr($encoded, '-_', '+/'));
        if ($target) {
            header("Location: $target");
            exit;
        }
    }
    echo "Invalid or broken link!";
    exit;
}

// -----------------------------
// EXAMPLE USAGE
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['url'])) {
    $originalUrl = $_GET['url'];
    $shortLink = generateRandomLink($originalUrl);
    echo "Original URL: " . htmlspecialchars($originalUrl) . "<br>";
    echo "Random short link: <a href='" . $shortLink . "'>" . $shortLink . "</a>";
    exit;
}

// DISABLE FAVOURITE IF NOT LOGGED IN


$isLoggedIn = isset($_SESSION["user_id"]);



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
                $_SESSION['profile_img'] = $row['profile_img'] ?? "imgandgifs/moving_login.gif";
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
    header("Location: /");
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

// ------------------ FETCH GAMES WITH CATEGORIES AND RATINGS ------------------
$userId = $_SESSION['user_id'] ?? 0;
$guestId = getGuestId();

$games = [];
$games_sql = "SELECT g.*, 
                 GROUP_CONCAT(DISTINCT gc.category SEPARATOR ',') AS categories,
                 (SELECT AVG(rating) FROM game_ratings WHERE game_id = g.game_id) as avg_rating,
                 (SELECT COUNT(*) FROM game_ratings WHERE game_id = g.game_id) as rating_count,
                 (SELECT COUNT(*) FROM favorites WHERE (user_id = $userId OR guest_id = '$guestId') AND game_id = g.game_id) as is_favorite,
                 (SELECT COUNT(*) FROM comments WHERE game_id = g.game_id) as comment_count
                 FROM games g
                 LEFT JOIN game_categories gc ON g.game_id = gc.game_id
                 WHERE g.is_banned = 0
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
    <link rel="icon" type="image/x-icon" href="/imgandgifs/logo.svg">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Orbitron:wght@400;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /*H2 STYLE DONT DELETE*/
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');

        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --text-light: #ffffff;
            --text-muted: #a099a6;
            --border-color: rgba(191, 50, 241, 0.2);
            --border: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: rgba(255, 255, 255, 0.05);
            --box-bg: rgba(255, 255, 255, 0.05);
            --card-bg: rgba(255, 255, 255, 0.03);
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --text-light: #ffffff;
            --text-muted: #a099a6;
            --border-color: rgba(191, 50, 241, 0.2);
            --border: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: rgba(255, 255, 255, 0.05);
            --box-bg: rgba(255, 255, 255, 0.08);
            --card-bg: rgba(255, 255, 255, 0.03);
        }

        body.bright {
            --bg-mesh-1: #f2f2f2;
            --bg-mesh-2: #ffffff;
            --bg-mesh-3: #dddddd;
            --text-main: #333333;
            --text-light: #000000;
            --text-muted: #666666;
            --border-color: rgba(191, 50, 241, 0.2);
            --border: rgba(191, 50, 241, 0.2);
            --glass: rgba(255, 255, 255, 0.8);
            --glass-strong: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --glow: 0 0 15px rgba(191, 50, 241, 0.2);
            --input-bg: rgba(0, 0, 0, 0.05);
            --box-bg: rgba(0, 0, 0, 0.03);
            --card-bg: rgba(0, 0, 0, 0.02);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .rating-stars {
            color: #ff8e00;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .rating-value {
            font-weight: 700;
            margin-right: 2px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            background-attachment: fixed;
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

        /* --- Global Typography --- */
        h1,
        h2,
        h3,
        h4,
        .menu-items li a {
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
        }

        /* --- Custom Scrollbar --- */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b79dc2;
        }

        header {
            display: flex;
            flex-direction: column;
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            width: 100%;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 40px;
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
        }

        @media (max-width: 768px) {
            .primary-nav {
                height: 0;
                overflow: visible;
                background: transparent;
                border: none;
            }

            .nav-container {
                display: block;
            }

            .header-top {
                padding: 10px 20px;
            }

            .top-links a:not(#themeToggle) {
                display: none;
                /* Hide most links in header on mobile */
            }

            .logo-icon {
                width: 60px !important;
                height: auto !important;
            }
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .logo-icon {
            width: 100px;
            height: 70px;
            align-items: center;
            justify-content: center;
        }



        .top-links {
            display: flex;
            gap: 20px;
            font-size: 0.90rem;
            color: var(--text-main);
            opacity: 0.8;
            font-weight: 700;
            align-items: center;
            /* Align all links vertically in the center */
        }

        .top-links a {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s;
            margin-top: 2px;
            /* adjust this value as needed */
        }



        .top-links a:hover {
            color: var(--accent);
        }

        .primary-nav {
            background: var(--glass);
            width: 100%;
            height: 80px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;

            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .primary-nav.nav-hidden {
            opacity: 0;
            transform: translateY(-100%);
            pointer-events: none;
        }




        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1300px;
            padding: 0 40px;
            height: 50%;



        }

        .menu-items {
            list-style: none;
            display: flex;
            height: 100%;
            gap: 5px;
        }

        .menu-items li {
            height: 100%;
        }

        .menu-items li a {
            color: var(--text-main);
            text-decoration: none;
            padding: 0 25px;
            display: flex;
            align-items: center;
            height: 100%;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.3s;
            border-radius: 4px;
        }

        .menu-items li a:hover,
        .menu-items li a.active-link {
            background: var(--accent);
            color: #ffffff;
            box-shadow: inset 0 0 10px rgba(191, 50, 241, 0.1);
        }

        .search-container {
            position: relative;
            background: rgba(255, 255, 255, 0.05);
            height: 34px;
            padding: 0 15px;
            display: flex;
            align-items: center;
            border-radius: 17px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            width: 250px;
            /* Expanded width */
        }

        .search-container:focus-within {
            border-color: var(--accent);
            box-shadow: var(--glow);
            background: rgba(255, 255, 255, 0.08);
            width: 300px;
        }

        .search-container input {
            background: transparent;
            border: none;
            color: var(--text-main);
            font-size: 0.85rem;
            width: 100%;
            outline: none;
        }

        /* Responsive: collapse menu on small screens */
        @media (max-width: 992px) {
            .hamburger {
                display: block !important;
                cursor: pointer;
            }

            .menu-items {
                position: fixed;
                right: -100%;
                top: 60px;
                /* Match header height */
                flex-direction: column;
                background: var(--glass-strong);
                backdrop-filter: blur(15px);
                width: 100%;
                height: calc(100vh - 60px);
                text-align: center;
                transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                padding: 40px 20px;
                gap: 15px !important;
                /* Reduced gap */
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                overflow-y: auto;
                /* Ensure scrollable menu */
            }

            .menu-items li {
                height: auto;
                /* Stretch li height on mobile */
                width: 100%;
            }

            .menu-items li a {
                padding: 15px;
                justify-content: center;
                font-size: 1.1rem;
                /* Slightly larger targets */
            }

            .menu-items.active {
                right: 0;
            }
        }

        header img.logo {
            max-width: 130px;
            height: auto;
            flex-shrink: 0;
        }

        /* Hamburger Styles */
        .hamburger {
            display: none;
            font-size: 28px;
            color: var(--text-light);
            margin-left: 15px;
        }


        .profile img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            cursor: pointer;
        }

        .profile img:hover {
            border-color: var(--accent);
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(149, 87, 161, 0.3);
        }

        .theme-toggle-btn {

            width: 35px;
            height: 35px;
            padding: 5px;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
        }

        .theme-toggle-btn img {
            width: 30px;
            height: 30px;
            pointer-events: none;
            transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .msg-btn {
            background: linear-gradient(135deg, #774280, #5d2a66) !important;
            padding: 10px 20px !important;
            border-radius: 12px !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem !important;
            box-shadow: 0 4px 15px rgba(119, 66, 128, 0.3);
        }

        .msg-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(119, 66, 128, 0.5);
        }

        .mobile-only {
            display: none;
        }

        @media (max-width: 768px) {
            .mobile-only {
                display: block;
            }
        }

        .container {
            display: flex;
            flex-direction: column;
            gap: 40px;
            padding: 40px;
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Removed .container.expanded logic as it's no longer a side-column layout */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: auto;
            background: var(--glass-strong);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            padding: 160px 40px 60px;
            /* Increased top padding to show text better */
            /*z-index: 995;*/
            display: flex;
            flex-direction: column;
            gap: 25px;
            transform: translateY(-100%);
            transition: transform 0.6s cubic-bezier(0.19, 1, 0.22, 1);
            border-bottom: 2px solid var(--border);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
            opacity: 0;
            overflow-y: auto;
            max-height: 90vh;
            /* Increased max height */
            visibility: hidden;
            /* Prevent interaction when hidden */
        }

        .sidebar.active {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }

        .sidebar h3 {
            margin-bottom: 5px;
            text-align: center;
            font-weight: 800;
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 6px;
            color: var(--text-light);
            opacity: 0.9;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 10px;
        }

        .sidebar a {
            text-decoration: none;
            color: #d9cddb;
            /* Brighter visibility by default */
            font-size: 0.9rem;
            font-weight: 600;
            padding: 14px 20px;
            border-radius: 16px;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            text-align: center;
            cursor: pointer;
            user-select: none;
            position: relative;
            overflow: hidden;
        }

        body.bright .sidebar a {
            color: #2c2433;
            /* Darker text for bright mode */
            background: rgba(0, 0, 0, 0.05);
            border-color: rgba(0, 0, 0, 0.1);
        }

        .sidebar a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .sidebar a:hover::before {
            left: 100%;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            transform: translateY(-4px) scale(1.02);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .sidebar a.active {
            background: linear-gradient(135deg, var(--accent), #774280);
            color: #fff !important;
            font-weight: 700;
            box-shadow: 0 0 25px rgba(191, 50, 241, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .sidebar a.active::after {
            content: '✓';
            margin-left: 8px;
            font-size: 1.1rem;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .sidebar-close-btn {
            position: absolute;
            top: 25px;
            right: 40px;
            font-size: 36px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid transparent;
        }

        .sidebar-close-btn:hover {
            background: rgba(255, 0, 0, 0.15);
            color: #ff4d4d;
            transform: rotate(180deg) scale(1.1);
            border-color: rgba(255, 77, 77, 0.3);
        }

        .cat-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto 10px;
            flex-wrap: wrap;
        }

        .clear-filters-btn {
            padding: 10px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .clear-filters-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: var(--accent);
        }

        .cat-search-container {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .cat-search-input {
            width: 100%;
            padding: 14px 25px;
            border-radius: 25px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .cat-search-input:focus {
            background: rgba(0, 0, 0, 0.4);
            border-color: var(--accent);
            box-shadow: var(--glow), inset 0 2px 10px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .main {
            background: var(--glass);
            backdrop-filter: blur(25px) saturate(110%);
            -webkit-backdrop-filter: blur(25px) saturate(110%);
            border: 1px solid var(--border);
            border-radius: 32px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 40px;
            padding: 30px;
            box-shadow: var(--shadow);
            animation: floatIn 1s cubic-bezier(0.19, 1, 0.22, 1);
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

        /*NEW CATEGORY*/
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: block;
            height: auto;
            overflow: visible;
        }

        .main {
            background: none;
            backdrop-filter: none;
            border: none;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .hero-container {
            display: flex;
            gap: 1px;
            height: 580px;
            margin-bottom: 40px;
            background: var(--glass);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .hero-main {
            flex: 2.5;
            position: relative;
            overflow: hidden;
        }

        .hero-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /*opacity: 0.6;*/
            transition: transform 0.5s;
        }

        .hero-main:hover img {
            transform: scale(1.05);
        }

        .hero-content {
            position: absolute;
            bottom: 50px;
            left: 50px;
            max-width: 60%;
            z-index: 2;
        }

        .hero-content h2 {
            font-size: 3rem;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 20px;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.8);
            font-family: var(--orbitron);
        }

        .hero-content p {
            color: var(--text-light);
            font-size: 1rem;
            margin-bottom: 25px;
            line-height: 1.5;
            text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.8);
        }

        .read-more-btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: #fff;
            padding: 12px 30px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 8px;
            box-shadow: var(--glow);
            transition: all 0.3s;
        }

        .read-more-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .hero-sidebar {
            flex: 1;
            background: var(--box-bg, rgba(255, 255, 255, 0.05));
            display: flex;
            flex-direction: column;
            border-left: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .hero-container {
                flex-direction: column;
                height: auto;
                border-radius: 12px;
            }

            .hero-main {
                height: 250px;
            }

            .hero-sidebar {
                border-left: none;
                border-top: 1px solid var(--border-color);
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }

            .sidebar-item {
                border-right: 1px solid rgba(255, 255, 255, 0.05);
                padding: 12px;
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            .sidebar-item:nth-child(2n) { border-right: none; }
            .sidebar-item img { width: 50px; height: 50px; mx-auto; }
            .sidebar-item .info h4 { font-size: 0.75rem; }
            .sidebar-item .info p { display: none; }

            .hero-content {
                bottom: 15px;
                left: 15px;
                max-width: 95%;
            }

            .hero-content h2 {
                font-size: 1.5rem;
                margin-bottom: 10px;
            }
            .hero-content p { font-size: 0.8rem; margin-bottom: 15px; }
        }

        .hero-sidebar h3 {
            background: rgba(255, 255, 255, 0.05);
            color: var(--accent);
            padding: 15px 25px;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border-color);
            font-family: var(--orbitron);
        }
        @media (max-width: 768px) {
            .hero-sidebar h3 {
                grid-column: span 2;
                padding: 12px;
                font-size: 0.8rem;
                text-align: center;
            }
        }

        .sidebar-item {
            display: flex;
            gap: 15px;
            padding: 15px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background 0.3s;
            cursor: pointer;
            align-items: center;
        }

        .sidebar-item:hover {
            background: rgba(191, 50, 241, 0.1);
        }

        .sidebar-item img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .sidebar-item .info h4 {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 5px;
            font-family: var(--orbitron);
        }

        .sidebar-item .info p {
            font-size: 0.7rem;
            color: var(--text-main);
            opacity: 0.8;
            line-height: 1.2;
        }

        /* --- Game Grid & Cards --- */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        @media (max-width: 768px) {
            .game-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }

        .game-card {
            background: var(--glass);
            border: 1px solid var(--border-color);
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .game-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .game-card .date-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--accent);
            color: #fff;
            padding: 8px 12px;
            text-align: center;
            z-index: 5;
            line-height: 1;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .game-card .date-badge .day {
            display: block;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .game-card .date-badge .month {
            display: block;
            font-size: 0.6rem;
            font-weight: 700;
        }

        .game-card .img-wrapper {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            position: relative;
            background: #000;
        }

        .game-card .img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .game-card:hover .img-wrapper img {
            transform: scale(1.1);
        }

        .game-card .content-box {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 60%, transparent);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            z-index: 3;
        }

        .game-card h3 {
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 6px;
            color: #ffffff;
            line-height: 1.2;
            font-family: var(--orbitron);
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8);
        }

        .game-card p {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.5;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* --- Gallery Button --- */
        .gallery-button-container {
            display: flex;
            justify-content: center;
            margin-bottom: 60px;
        }

        .vault-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 40px;
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            color: var(--accent);
            text-decoration: none;
            font-family: var(--orbitron);
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--shadow);
        }

        .vault-btn i {
            font-size: 1.2rem;
            transition: transform 0.4s;
        }

        .vault-btn:hover {
            transform: translateY(-5px) scale(1.05);
            border-color: var(--accent);
            box-shadow: var(--glow);
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #774280);
        }

        .vault-btn:hover i {
            transform: rotate(90deg);
        }

        body.bright .vault-btn {
            background: #ffffff;
            color: var(--accent);
        }

        body.bright .vault-btn:hover {
            color: #fff;
        }

        /* --- Mid Banner --- */
        .mid-banner {
            width: 100%;
            height: 250px;
            position: relative;
            overflow: hidden;
            margin-bottom: 50px;
            background: #000;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .mid-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.5;
        }

        .mid-banner .banner-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #fff;
            width: 90%;
            z-index: 2;
        }

        .mid-banner h2 {
            font-size: 2.5rem;
            margin-bottom: 25px;
            line-height: 1.1;
            text-shadow: 2px 2px 15px rgba(0, 0, 0, 1);
            font-family: var(--orbitron);
            color: #fff;
        }

        /* --- Bottom Sections --- */
        .bottom-sections {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 60px;
            margin-right: 0px;
        }

        @media (max-width: 992px) {
            .bottom-sections {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        .bottom-col h3 {
            font-size: 1.1rem;
            color: var(--accent);
            margin-bottom: 25px;
            font-family: var(--orbitron);
            border-bottom: 2px solid var(--accent);
            display: inline-block;
            padding-bottom: 5px;
        }

        .bottom-item {
            display: flex;
            position: relative;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid transparent;
            margin-right: 10px;
        }

        .bottom-item:hover {
            background: rgba(191, 50, 241, 0.08);
            border-color: var(--border-color);
            transform: translateX(5px);
        }

        .bottom-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .bottom-item-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .bottom-item-content .date {
            font-size: 0.65rem;
            color: var(--accent);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .bottom-item-content h4 {
            font-size: 0.85rem;
            color: var(--text-main);
            margin-bottom: 5px;
            font-weight: 600;
        }





        /* --- Social Bar --- */
        .social-bar {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin: 50px 0;
            width: 100%;
        }

        @media (max-width: 600px) {
            .social-bar {
                flex-direction: column;
            }
        }

        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            padding: 25px;
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.95rem;
            transition: all 0.3s;
            border-radius: 15px;
            font-family: var(--orbitron);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .social-btn:hover {
            transform: translateY(-5px);
            filter: brightness(1.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .social-btn i {
            font-size: 1.5rem;
        }

        .social-fb {
            background: #3b5998;
        }

        .social-tw {
            background: #00acee;
        }

        .social-yt {
            background: #c4302b;
        }

        footer {
            background: var(--glass);
            color: var(--text-main);
            padding: 40px 20px;
            text-align: center;
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
            backdrop-filter: blur(10px);
        }



        @media(max-width: 1024px) {
            .container {
                grid-template-columns: 180px 1fr;
            }
        }

        @media(max-width: 768px) {
            header {
                position: fixed !important;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1100 !important;
                padding: 0 15px;
                height: 70px;
                justify-content: space-between;
                background: var(--glass-strong);
                backdrop-filter: blur(30px);
                border-bottom: 1px solid var(--border-color);
            }

            .container {
                padding: 15px;
                margin-top: 70px;
            }

            .main {
                gap: 25px;
            }

            .game-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .game-card {
                border-radius: 16px;
            }

            .featured-card {
                min-width: 140px;
                max-width: 140px;
                height: 120px;
                background: var(--card-bg);
                border: 1px solid var(--border-color);
            }
            .featured-card img { height: 60px !important; }
            .featured-card h4 { font-size: 0.65rem !important; color: var(--text-main); }

            .social-bar { gap: 10px; }
            .social-btn { padding: 15px; font-size: 0.8rem; }
        }

        /* Mobile-Specific Refinements (Global) */
        @media(max-width: 768px) {
            :root {
                --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            }

            .game-card {
                box-shadow: var(--card-shadow);
            }

            /* Touch Feedback */
            .game-card:active {
                transform: scale(0.98);
            }

            .sidebar a:active {
                transform: scale(0.95);
                background: rgba(255, 255, 255, 0.1);
            }

            /* Better Scrolling */
            .sidebar,
            .main,
            body {
                -webkit-overflow-scrolling: touch;
            }

            /* Compact Scrollbars for Mobile */
            ::-webkit-scrollbar {
                width: 4px;
            }
        }

        /* ----------- három pontos gomb ----------- */
        .card-menu-btn {
            position: absolute;
            top: 40px;
            right: 8px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            z-index: 5;
            color: var(--text-main);
        }

        @media (max-width: 768px) {
            .card-menu-btn {
                bottom: 8px;
                right: 8px;
                padding: 10px 14px;
                font-size: 24px;
            }
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
            color: #f5b301;
            font-weight: 600;
        }

        .fav-btn:hover {
            background: rgba(255, 200, 0, 0.1);
        }

        /* lenyíló menü */
        .card-menu {
            position: absolute;
            top: 32px;
            right: 8px;
            display: none;
            flex-direction: column;
            gap: 4px;

            background: var(--glass);
            backdrop-filter: blur(10px);

            border: 1px solid var(--border);
            border-radius: 10px;

            box-shadow: var(--shadow);

            padding: 6px;
            min-width: 140px;
            z-index: 50;
        }

        .card-menu.show {
            display: flex;
        }


        .card-menu button {
            background: transparent;
            border: none;
            text-align: left;

            padding: 8px 10px;
            border-radius: 6px;

            color: var(--text-main);
            font-size: 0.8rem;

            cursor: pointer;
            transition: all 0.2s ease;
        }

        .card-menu button:hover {
            background: var(--input-bg);
            color: var(--text-light);
        }

        .card-menu .danger {
            color: #ff4d4d;
        }

        .card-menu .danger:hover {
            background: rgba(255, 0, 0, 0.1);
            color: #ff2a2a;
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
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 12px 18px;
            border-radius: 6px;
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 9999;
        }

        .toast.show {
            opacity: 1;
        }

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
            padding: 10px 0;
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
            min-width: 200px;
            max-width: 200px;
            height: 170px;
            background: var(--glass);
            border: 1px solid var(--border);
            backdrop-filter: blur(30px);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
            position: relative;
            display: block;
            text-decoration: none;
            color: var(--text-light);
            flex-shrink: 0;
            user-drag: none;
            box-shadow: var(--shadow);
        }

        @media (max-width: 768px) {
            .featured-card {
                min-width: 150px;
                max-width: 150px;
                height: 130px;
                border-radius: 16px;
            }

            .featured-card img {
                height: 60px !important;
            }

            .featured-card h4 {
                font-size: 0.7rem !important;
                padding: 4px !important;
            }
        }

        .featured-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: var(--shadow), var(--glow);
            border-color: var(--accent);
            z-index: 10;
        }

        .featured-card img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            user-drag: none;
            -webkit-user-drag: none;
            user-select: none;
            -moz-user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.6s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .featured-card:hover img {
            transform: scale(1.1);
        }

        .featured-card h4 {
            padding: 8px;
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* --- GAMING NEWS OVERLAY PANEL --- */
        .news-section {
            position: fixed;
            top: 0px;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: var(--bg-mesh-1);
            /* Use theme variable */
            backdrop-filter: blur(20px);
            z-index: 2000;
            /* Below header (1000) */
            overflow-y: auto;
            transform: translateY(-100%);
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 100px 20px 40px;
            /* Top padding to clear header */
            margin: 0;
            border: none;
            box-shadow: none;
            opacity: 1;
            /* Always opaque when animating in */
            display: block;
            /* No more display:none/max-height logic */
        }

        .news-section.active {
            transform: translateY(0);
            max-height: 100vh;
            /* Reset from previous logic */
        }

        /* Body Scroll Lock */
        body.news-open {
            overflow: hidden;
        }

        .news-section h2 {
            font-family: "Orbitron", sans-serif;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-light);
            /* Changed to variable */
            letter-spacing: 0.3em;
            text-shadow: 0 0 20px rgba(128, 128, 128, 0.1);
            margin-bottom: 50px;
            font-size: 3rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 25px;
            animation: fadeInDown 0.8s cubic-bezier(0.19, 1, 0.22, 1);
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .news-close-btn {
            position: fixed;
            top: 30px;
            right: 40px;
            font-size: 32px;
            color: var(--text-light);
            background: var(--glass);
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 995;
            /* Above news content but below header */
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            display: flex;
            /* Visible on all devices */
        }

        .news-close-btn:hover {
            background: rgba(255, 0, 0, 0.2);
            color: #ff3e3e;
            /* Visible red */
            border-color: rgba(255, 0, 0, 0.5);
            transform: scale(1.1) rotate(90deg);
        }

        @media (max-width: 768px) {
            .news-section h2 {
                font-size: 1.8rem;
                letter-spacing: 0.1em;
            }

            .news-close-btn {
                display: flex;
                /* Ensure it is visible on mobile */
                width: 50px;
                height: 50px;
                font-size: 30px;
                top: 20px;
                right: 20px;
                background: var(--glass-strong);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
                border: 1px solid var(--accent);
                z-index: 1001;
                /* Above mobile header if needed */
            }
        }

        /* --- Live Pulsator --- */
        .live-pulse {
            width: 12px;
            height: 12px;
            background: #ff3e3e;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 rgba(255, 62, 62, 0.4);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(255, 62, 62, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(255, 62, 62, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(255, 62, 62, 0);
            }
        }

        .news-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .news-card {
            background: var(--glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
            text-decoration: none;
            color: var(--text-light);
            transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
            display: flex;
            flex-direction: column;
            gap: 15px;
            opacity: 0;
            transform: translateY(30px);
            overflow: hidden;
            position: relative;
        }

        .news-card.animate {
            opacity: 1;
            transform: translateY(0);
        }

        .news-card:hover {
            background: var(--glass-strong);
            transform: translateY(-10px) scale(1.02);
            border-color: var(--accent);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .news-card .img-wrapper {
            width: 100%;
            height: 200px;
            border-radius: 12px;
            overflow: hidden;
            background: #111;
        }

        .news-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .news-card:hover img {
            transform: scale(1.1);
        }

        .news-card .source {
            font-size: 0.7rem;
            color: #fff;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: linear-gradient(90deg, #ff9d00, #ff4e00);
            padding: 4px 12px;
            border-radius: 20px;
            align-self: flex-start;
            box-shadow: 0 4px 10px rgba(255, 157, 0, 0.3);
        }

        .news-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.25;
            margin: 0;
            color: var(--text-light);
            transition: color 0.3s ease;
        }

        .news-card:hover h3 {
            color: #ff9d00;
        }

        .news-card p.description {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin: 0;
            font-weight: 400;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* --- Skeleton Loading  --- */
        .skeleton {
            background: #eee;
            border-radius: 4px;
        }

        .skeleton-card {
            height: 300px;
            background: #fff;
            border: 1px solid #ddd;
        }



        .skeleton-img {
            height: 200px;
            width: 100%;
        }

        .skeleton-text {
            height: 18px;
            width: 40%;
            border-radius: 20px;
        }

        .skeleton-title {
            height: 30px;
            width: 95%;
        }

        .skeleton-desc {
            height: 100px;
            width: 100%;
        }

        @media (max-width: 768px) {
            .news-container {
                grid-template-columns: 1fr;
            }
        }

        /* --- TOAST NOTIFICATIONS --- */
        #toast-container {
            position: fixed;
            bottom: 20px;
            /* mindig a képernyő alján */
            left: 50%;
            /* középre */
            transform: translateX(-50%);
            /* középre igazítás */
            z-index: 999999;

            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            /* ne blokkolja az egér eseményeket */
        }

        .toast {
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            backdrop-filter: blur(6px);

            pointer-events: auto;
            animation: toastIn 0.3s ease forwards;
        }

        .toast.success {
            border-left: 4px solid #4CAF50;
        }

        .toast.error {
            border-left: 4px solid #ff4d4d;
        }

        /* animáció: csak Y irányban mozogjon */
        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes toastOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }
    </style>
</head>

<body class="dark">
    <header>
        <div id="toast-container" class="toast-container"></div>
        <div class="header-top">
            <a href="index.php" class="logo-container">
                <img src="imgandgifs/catlogo.png" class="logo-icon">
            </a>
            <div class="top-links">
                <div id="hamburger" class="hamburger">☰</div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" style="display: flex; align-items: center; gap: 5px;">
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/moving_login.gif'); ?>"
                            alt="profile" style="width: 16px; height: 16px; border-radius: 50%;">
                        <span>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </a>
                    <a href="?action=logout">LOGOUT</a>
                <?php else: ?>
                    <a href="auth.php">LOGIN</a>
                <?php endif; ?>

                <!--<a href="message.php">MESSAGE</a>-->
                <a href="custumersupport.php">HELP</a>
                <a href="workinggame.php">BORED?</a>
                <a href="#" id="themeToggle" class="theme-toggle-btn" title="Toggle Theme">
                    <img src="imgandgifs/sun.svg" alt="Toggle">
                </a>
            </div>
        </div>
    </header>
    <nav class="primary-nav">
        <div class="nav-container">
            <ul class="menu-items">
                <li><a href="index.php" class="active-link">HOME</a></li>
                <li><a href="about.php">ABOUT</a></li>
                <li><a href="games.php">GALLERY</a></li>
                <li><a href="#" id="blogLink" onclick="openNews()">NEWS</a></li>

                <li class="mobile-only"><a href="custumersupport.php">HELP</a></li>
                <li class="mobile-only"><a href="workinggame.php">BORED?</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="mobile-only"><a href="profile.php">PROFILE
                            (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    <li class="mobile-only"><a href="?action=logout">LOGOUT</a></li>
                <?php else: ?>
                    <li class="mobile-only"><a href="auth.php">LOGIN</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <div class="container">
        <nav class="sidebar">
            <div class="sidebar-close-btn" onclick="toggleSidebar()">×</div>
            <h3>Categories</h3>
            <div class="cat-controls">
                <div class="cat-search-container">
                    <input type="text" id="catSearchInput" class="cat-search-input" placeholder="Search categories...">
                </div>
                <button id="clearFiltersBtn" class="clear-filters-btn">Clear All</button>
            </div>
            <div class="categories-grid">
                <a data-category="all" class="active">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a data-category="<?php echo htmlspecialchars($cat); ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
        <main class="main">
            <!-- Hero Section -->
            <section class="hero-container">
                <div class="hero-main">
                    <?php
                    // Display latest game or a featured one as hero
                    $heroGame = !empty($games) ? $games[count($games) - 1] : null;
                    if ($heroGame):
                        $heroImg = !empty($heroGame['main_image']) ? $heroGame['main_image'] : 'imgandgifs/logo.png';
                        ?>
                        <img src="<?php echo htmlspecialchars($heroImg); ?>" alt="Hero Game">
                        <div class="hero-content">
                            <h2>
                                <?php echo htmlspecialchars($heroGame['title']); ?>
                            </h2>
                            <p>
                                <?php echo htmlspecialchars(mb_strimwidth($heroGame['description'], 0, 150, "...")); ?>
                            </p>
                            <div class="rating-stars" style="margin-bottom: 10px;">
                                <i class="fas fa-star"></i>
                                <span class="rating-value">
                                    <?php echo $heroGame['avg_rating'] ? round($heroGame['avg_rating'], 1) : '0.0'; ?>
                                </span>
                                <span style="opacity:0.6; font-size: 0.7rem;">(
                                    <?php echo $heroGame['rating_count']; ?>)
                                </span>
                            </div>
                            <a href="game.php?id=<?php echo $heroGame['game_id']; ?>" class="read-more-btn">READ MORE</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hero-sidebar">
                    <h3>TOP GAMES</h3>
                    <?php
                    // Show 4 popular/latest games in sidebar
                    $sidebarGames = array_slice($games, 0, 4);
                    foreach ($sidebarGames as $sg):
                        $sgImg = !empty($sg['main_image']) ? $sg['main_image'] : 'imgandgifs/logo.png';
                        ?>
                        <div class="sidebar-item" onclick="location.href='game.php?id=<?php echo $sg['game_id']; ?>'">
                            <img src="<?php echo htmlspecialchars($sgImg); ?>" alt="Top Game">
                            <div class="info">
                                <h4>
                                    <?php echo htmlspecialchars($sg['title']); ?>
                                </h4>
                                <div class="rating-stars" style="font-size: 0.7rem; margin-bottom: 5px;">
                                    <i class="fas fa-star"></i>
                                    <span class="rating-value">
                                        <?php echo $sg['avg_rating'] ? round($sg['avg_rating'], 1) : '0.0'; ?>
                                    </span>
                                </div>
                                <p>
                                    <?php echo htmlspecialchars(mb_strimwidth($sg['description'], 0, 80, "...")); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>



            <!-- Infinite Scroll Carousel "Moving Belt" -->
            <section class="carousel-container">
                <div class="carousel-track" id="carouselTrack">
                    <?php
                    // Display games in the belt (duplicated for infinite effect)
                    $beltGames = array_merge($games, $games);
                    foreach ($beltGames as $bg):
                        $bgImg = !empty($bg['main_image']) ? $bg['main_image'] : 'imgandgifs/logo.png';
                        ?>
                        <a href="game.php?id=<?php echo $bg['game_id']; ?>" class="featured-card">
                            <img src="<?php echo htmlspecialchars($bgImg); ?>" alt="Featured">
                            <h4><?php echo htmlspecialchars($bg['title']); ?></h4>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Mid Banner removed, Slider (Treadmill) is above -->


            <!-- ---- ... BUTTON ----->

            <!-- Bottom Sections -->
            <section class="bottom-sections">

                <!-- Latest -->
                <div class="bottom-col">
                    <h3>LATEST</h3>

                    <?php
                    $latestGames = array_slice($games, -3);
                    $latestGames = array_reverse($latestGames);

                    foreach ($latestGames as $lg): ?>

                        <div class="bottom-item" data-game-id="<?php echo $lg['game_id']; ?>"
                            onclick="location.href='game.php?id=<?php echo $lg['game_id']; ?>'">

                            <button class="card-menu-btn"
                                onclick="toggleCardMenu(event, 'latest-<?php echo $lg['game_id']; ?>')">⋯</button>

                            <div class="card-menu" id="menu-latest-<?php echo $lg['game_id']; ?>">

                                <button class="fav-btn <?= $lg['is_favorite'] ? 'active' : '' ?>"
                                    onclick="toggleFavourite(event, <?= $lg['game_id'] ?>)">
                                    ⭐ Favourite
                                </button>

                                <button onclick="copyGameLink(event, <?php echo $lg['game_id']; ?>)">
                                    📋 Copy link
                                </button>

                                <button class="danger"
                                    onclick="disableGame(event, <?php echo $lg['game_id']; ?>, '<?php echo addslashes(htmlspecialchars($lg['title'])); ?>')">
                                    🚫 Ban
                                </button>

                            </div>

                            <img src="<?php echo htmlspecialchars($lg['main_image']); ?>" alt="Latest">

                            <div class="bottom-item-content">
                                <span class="date"><?php echo date("j M, Y", strtotime($lg['created_at'])); ?></span>
                                <h4>
                                    <?php echo htmlspecialchars($lg['title']); ?>
                                </h4>
                                <div class="rating-stars" style="font-size: 0.65rem;">
                                    <i class="fas fa-star"></i>
                                    <span class="rating-value">
                                        <?php echo $lg['avg_rating'] ? round($lg['avg_rating'], 1) : '0.0'; ?>
                                    </span>
                                    <span style="opacity:0.6;">(
                                        <?php echo $lg['rating_count']; ?>)
                                    </span>
                                </div>
                                <p style="font-size:0.65rem;color:#999;"><?php echo $lg['comment_count']; ?> COMMENTS</p>
                            </div>

                        </div>

                    <?php endforeach; ?>
                </div>


                <!-- Featured -->
                <div class="bottom-col">
                    <h3>FEATURED</h3>

                    <?php
                    $featDisplay = array_slice($games, 0, 3);

                    foreach ($featDisplay as $fg): ?>

                        <div class="bottom-item" data-game-id="<?php echo $fg['game_id']; ?>"
                            onclick="location.href='game.php?id=<?php echo $fg['game_id']; ?>'">

                            <button class="card-menu-btn"
                                onclick="toggleCardMenu(event, 'featured-<?php echo $fg['game_id']; ?>')">⋯</button>

                            <div class="card-menu" id="menu-featured-<?php echo $fg['game_id']; ?>">

                                <button class="fav-btn <?= $fg['is_favorite'] ? 'active' : '' ?>"
                                    onclick="toggleFavourite(event, <?= $fg['game_id'] ?>)">
                                    ⭐ Favourite
                                </button>

                                <button onclick="copyGameLink(event, <?php echo $fg['game_id']; ?>)">
                                    📋 Copy link
                                </button>

                                <button class="danger"
                                    onclick="disableGame(event, <?php echo $fg['game_id']; ?>, '<?php echo addslashes(htmlspecialchars($fg['title'])); ?>')">
                                    🚫 Ban
                                </button>

                            </div>

                            <img src="<?php echo htmlspecialchars($fg['main_image']); ?>" alt="Featured">

                            <div class="bottom-item-content">
                                <span class="date"><?php echo date("j M, Y", strtotime($fg['created_at'])); ?></span>
                                <h4>
                                    <?php echo htmlspecialchars($fg['title']); ?>
                                </h4>
                                <div class="rating-stars" style="font-size: 0.65rem;">
                                    <i class="fas fa-star"></i>
                                    <span class="rating-value">
                                        <?php echo $fg['avg_rating'] ? round($fg['avg_rating'], 1) : '0.0'; ?>
                                    </span>
                                    <span style="opacity:0.6;">(
                                        <?php echo $fg['rating_count']; ?>)
                                    </span>
                                </div>
                                <p style="font-size:0.65rem;color:#999;"><?php echo $fg['comment_count']; ?> COMMENTS</p>
                            </div>

                        </div>

                    <?php endforeach; ?>
                </div>


                <!-- Popular -->
                <div class="bottom-col">
                    <h3>POPULAR</h3>

                    <?php
                    $popDisplay = array_slice($games, 2, 3);

                    foreach ($popDisplay as $pg): ?>

                        <div class="bottom-item" data-game-id="<?php echo $pg['game_id']; ?>"
                            onclick="location.href='game.php?id=<?php echo $pg['game_id']; ?>'">

                            <button class="card-menu-btn"
                                onclick="toggleCardMenu(event, 'popular-<?php echo $pg['game_id']; ?>')">⋯</button>

                            <div class="card-menu" id="menu-popular-<?php echo $pg['game_id']; ?>">

                                <button class="fav-btn <?= $pg['is_favorite'] ? 'active' : '' ?>"
                                    onclick="toggleFavourite(event, <?= $pg['game_id'] ?>)">
                                    ⭐ Favourite
                                </button>

                                <button onclick="copyGameLink(event, <?php echo $pg['game_id']; ?>)">
                                    📋 Copy link
                                </button>

                                <button class="danger"
                                    onclick="disableGame(event, <?php echo $pg['game_id']; ?>, '<?php echo addslashes(htmlspecialchars($pg['title'])); ?>')">
                                    🚫 Ban
                                </button>

                            </div>

                            <img src="<?php echo htmlspecialchars($pg['main_image']); ?>" alt="Popular">

                            <div class="bottom-item-content">
                                <span class="date"><?php echo date("j M, Y", strtotime($pg['created_at'])); ?></span>
                                <h4>
                                    <?php echo htmlspecialchars($pg['title']); ?>
                                </h4>
                                <div class="rating-stars" style="font-size: 0.65rem;">
                                    <i class="fas fa-star"></i>
                                    <span class="rating-value">
                                        <?php echo $pg['avg_rating'] ? round($pg['avg_rating'], 1) : '0.0'; ?>
                                    </span>
                                    <span style="opacity:0.6;">(
                                        <?php echo $pg['rating_count']; ?>)
                                    </span>
                                </div>
                                <p style="font-size:0.65rem;color:#999;"><?php echo $pg['comment_count']; ?> COMMENTS</p>
                            </div>

                        </div>

                    <?php endforeach; ?>
                </div>

            </section>


        </main>
    </div>

    <footer>
        <p>Games © 2026 • Privacy Policy</p>
    </footer>
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

    <!-- Gaming News Overlay Section -->
    <section class="news-section">
        <div class="news-close-btn" id="closeNewsBtn">×</div>
        <h2><span class="live-pulse"></span> PULSE: LIVE GAMING NEWS</h2>
        <div class="news-container" id="news-container">
            <!-- News items loaded via JS -->
        </div>
    </section>
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


        // ================= TOAST SYSTEM =================
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;

            // direkt a body-ba
            document.body.appendChild(toast);

            // pozicionálás inline (biztos fixed alul)
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.left = '50%';
            toast.style.transform = 'translateX(-50%)';
            toast.style.zIndex = '999999';

            toast.style.animation = 'toastIn 0.3s forwards';

            setTimeout(() => {
                toast.style.animation = 'toastOut 0.5s forwards';
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        // ================= FAVOURITE =================
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





        // ================= BAN GAME =================
        function disableGame(event, gameId, gameName) {
            event.preventDefault();
            if (!confirm(`Are you sure you want to ban "${gameName}"? It will move to your profile list.`)) return;

            fetch("profile.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ toggle_ban: String(gameId) })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === "ban_added") {
                        const card = document.querySelector(`.game-card[data-game-id='${gameId}']`);
                        if (card) {
                            card.style.opacity = '0';
                            setTimeout(() => card.remove(), 400);
                        }
                        showToast("Game banned");
                    }
                })
                .catch(err => {
                    console.error("BAN ERROR:", err);
                    showToast("Failed to ban game");
                });
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
                // Filter Featured - DISABLED as per request
                // document.querySelectorAll('.featured-card').forEach(card => {
                //     const title = card.querySelector('h4').textContent.toLowerCase();
                //     card.style.display = title.includes(filter) ? 'block' : 'none';
                // });
            });
        }

        // ================= CATEGORY FILTERING (MULTI-SELECT) =================
        const categoryLinks = document.querySelectorAll('.sidebar .categories-grid a[data-category]');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        let selectedCats = new Set();

        function updateGridFilter() {
            const cards = document.querySelectorAll('.game-card');

            cards.forEach(card => {
                const cardCatStr = card.getAttribute('data-category');
                if (!cardCatStr) return;

                const cardCats = cardCatStr.split('|');

                // If nothing selected or "all", show everything
                if (selectedCats.size === 0 || selectedCats.has('all')) {
                    card.style.display = 'flex';
                } else {
                    // Match ANY selected category (OR logic)
                    const matches = cardCats.some(cat => selectedCats.has(cat));
                    card.style.display = matches ? 'flex' : 'none';
                }
            });
        }

        categoryLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const cat = this.getAttribute('data-category');

                if (cat === 'all') {
                    selectedCats.clear();
                    selectedCats.add('all');
                } else {
                    // If "all" was selected, remove it first
                    selectedCats.delete('all');

                    if (selectedCats.has(cat)) {
                        selectedCats.delete(cat);
                    } else {
                        selectedCats.add(cat);
                    }
                }

                // If empty, fall back to "all"
                if (selectedCats.size === 0) {
                    selectedCats.add('all');
                }

                // Update UI classes
                categoryLinks.forEach(l => {
                    const lCat = l.getAttribute('data-category');
                    if (selectedCats.has(lCat)) {
                        l.classList.add('active');
                    } else {
                        l.classList.remove('active');
                    }
                });

                updateGridFilter();

                // Scroll to top of grid
                document.querySelector('.main').scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                selectedCats.clear();
                selectedCats.add('all');

                categoryLinks.forEach(l => {
                    if (l.getAttribute('data-category') === 'all') l.classList.add('active');
                    else l.classList.remove('active');
                });

                updateGridFilter();
                showToast("Filters cleared");
            });
        }

        // ================= CATEGORY SEARCH =================
        const catSearchInput = document.getElementById('catSearchInput');
        if (catSearchInput) {
            catSearchInput.addEventListener('input', function () {
                const filter = this.value.toLowerCase();
                const catLinks = document.querySelectorAll('.sidebar .categories-grid a[data-category]');

                catLinks.forEach(link => {
                    const text = link.textContent.toLowerCase();
                    // Don't filter out "All" button or keep it visible
                    if (link.getAttribute('data-category') === 'all' || text.includes(filter)) {
                        link.style.display = 'flex';
                    } else {
                        link.style.display = 'none';
                    }
                });
            });
        }

        // ================= GAMES MENU & SIDEBAR =================
        const gamesMenuBtn = document.getElementById('gamesMenuBtn');

        if (gamesMenuBtn) {
            gamesMenuBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleSidebar();
            });
        }

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const isActive = sidebar.classList.contains('active');

            if (isActive) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        function openSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const gamesMenuBtn = document.getElementById('gamesMenuBtn');
            const hamburger = document.getElementById('hamburger');

            // Close News if open
            if (typeof closeNews === 'function') closeNews();

            sidebar.classList.add('active');
            if (gamesMenuBtn) gamesMenuBtn.textContent = '✕';
            if (hamburger) hamburger.textContent = '✕';
            // document.body.style.overflow = 'hidden'; 
        }

        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const gamesMenuBtn = document.getElementById('gamesMenuBtn');
            const hamburger = document.getElementById('hamburger');

            sidebar.classList.remove('active');
            if (gamesMenuBtn) gamesMenuBtn.textContent = 'Games';
            if (hamburger) hamburger.textContent = '☰';
            // document.body.style.overflow = '';
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

        // --- THEME TOGGLE LOGIC ---
        const themeToggleBtn = document.getElementById('themeToggle');

        // Load saved theme or default to dark
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.className = savedTheme;

        function updateThemeUI() {
            const isDark = document.body.classList.contains('dark');
            const img = themeToggleBtn ? themeToggleBtn.querySelector('img') : null;

            if (img) {
                // Swap icons (Opposite as per user request: Dark -> Moon, Bright -> Sun)
                img.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";

                // Animation + glow (Purple for Moon/Dark, Orange for Sun/Bright)
                img.style.transform = isDark
                    ? 'rotate(180deg) scale(1)'
                    : 'rotate(0deg) scale(1.1)';

                img.style.filter = isDark
                    ? 'drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))'
                    : 'drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))';
            }
        }

        if (themeToggleBtn) {
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
        }

        // Initialize UI on load
        updateThemeUI();







    </script>
    <script>
        // ================= MOVING BELT CAROUSEL (DRAGGABLE) =================
        const track = document.getElementById('carouselTrack');
        let isDragging = false;
        let startX;
        let scrollLeft;
        let animationId;
        let currentTranslate = 0;
        let prevTranslate = 0;

        if (track) {
            const startDrag = (e) => {
                isDragging = true;
                track.classList.add('dragging');
                startX = (e.pageX || e.touches[0].pageX) - track.offsetLeft;
                scrollLeft = currentTranslate;
                cancelAnimationFrame(animationId);
            };

            const moveDrag = (e) => {
                if (!isDragging) return;
                e.preventDefault();
                const x = (e.pageX || e.touches[0].pageX) - track.offsetLeft;
                const walk = (x - startX);
                currentTranslate = scrollLeft + walk;
                track.style.transform = `translateX(${currentTranslate}px)`;
            };

            const endDrag = () => {
                isDragging = false;
                track.classList.remove('dragging');

                // Infinite reset logic
                const trackWidth = track.scrollWidth / 2;
                if (Math.abs(currentTranslate) >= trackWidth) {
                    currentTranslate = currentTranslate % trackWidth;
                    track.style.transform = `translateX(${currentTranslate}px)`;
                }

                cancelAnimationFrame(animationId); // Prevent multiple loops
                startAutoScroll();
            };

            track.addEventListener('mousedown', startDrag);
            track.addEventListener('mousemove', moveDrag);
            track.addEventListener('mouseup', endDrag);
            track.addEventListener('mouseleave', endDrag);

            track.addEventListener('touchstart', startDrag);
            track.addEventListener('touchmove', moveDrag);
            track.addEventListener('touchend', endDrag);

            function startAutoScroll() {
                if (isDragging) return;
                currentTranslate -= 1.5; // Slightly faster for premium feel
                const trackWidth = track.scrollWidth / 2;
                if (Math.abs(currentTranslate) >= trackWidth) {
                    currentTranslate = 0;
                }
                track.style.transform = `translateX(${currentTranslate}px)`;
                animationId = requestAnimationFrame(startAutoScroll);
            }

            // Optimization: Only animate if visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        cancelAnimationFrame(animationId); // Safety first
                        startAutoScroll();
                    } else {
                        cancelAnimationFrame(animationId);
                    }
                });
            }, { threshold: 0.1 });

            observer.observe(track);
            // Start instantly
            startAutoScroll();
        }

        // --- REFINED DRAGGABLE JS BELT RESTORED ---

        // --- GAMING NEWS LOADER ---
        let newsLoaded = false;

        function showSkeletons() {
            const newsContainer = document.getElementById('news-container');
            newsContainer.innerHTML = '';
            // Show 12 skeletons for a more substantial loading state
            for (let i = 0; i < 12; i++) {
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton-card';
                skeleton.innerHTML = `
                     <div class="skeleton-img skeleton"></div>
                     <div class="skeleton-text skeleton"></div>
                     <div class="skeleton-title skeleton"></div>
                     <div class="skeleton-desc skeleton" style="height: 100px;"></div>
                 `;
                newsContainer.appendChild(skeleton);
            }
        }

        async function fetchGamingNews() {
            const newsContainer = document.getElementById('news-container');
            if (newsLoaded) return;

            showSkeletons();

            try {
                const response = await fetch('news_proxy.php');
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const data = await response.json();

                if (data && Array.isArray(data) && data.length > 0) {
                    newsContainer.innerHTML = '';

                    data.forEach((item, index) => {
                        const newsCard = document.createElement('a');
                        newsCard.className = 'news-card';
                        newsCard.href = item.url;
                        newsCard.target = '_blank';

                        const imgUrl = item.image || 'imgandgifs/logo.png';
                        const desc = item.description || 'Click to read the full story on ' + item.source;

                        newsCard.innerHTML = `
                             <div class="img-wrapper">
                                 <img src="${imgUrl}" alt="${item.title}" onerror="this.src='imgandgifs/logo.png'">
                             </div>
                             <span class="source">${item.source || 'Gaming News'}</span>
                             <h3>${item.title}</h3>
                             <p class="description">${desc}</p>
                         `;
                        newsContainer.appendChild(newsCard);

                        // Staggered Animation
                        setTimeout(() => {
                            newsCard.classList.add('animate');
                        }, 100 * index);
                    });
                    newsLoaded = true;
                } else {
                    newsContainer.innerHTML = `<p style="text-align:center; color:#ccc; width:100%;">No news available at the moment.</p>`;
                }
            } catch (error) {
                console.error('Error fetching news:', error);
                newsContainer.innerHTML = `<p style="text-align:center; color:#ccc; width:100%;">Failed to sync with the pulse. Please try again later.</p>`;
            }
        }

        // Toggle News Overlay
        const toggleNewsBtn = document.getElementById('toggleNewsBtn');
        const closeNewsBtn = document.getElementById('closeNewsBtn');
        const newsSection = document.querySelector('.news-section');

        function openNews() {
            const hamburger = document.getElementById('hamburger');
            const nav = document.querySelector('.primary-nav');

            if (typeof closeSidebar === 'function') closeSidebar();

            newsSection.classList.add('active');

            if (nav) nav.classList.add('nav-hidden'); // hide nav

            if (hamburger) hamburger.textContent = '✕';

            document.body.classList.add('news-open');

            fetchGamingNews();
        }

        function closeNews() {
            const hamburger = document.getElementById('hamburger');
            const nav = document.querySelector('.primary-nav'); // added

            newsSection.classList.remove('active');
            document.body.classList.remove('news-open');

            if (nav) nav.classList.remove('nav-hidden'); // show navbar again

            // Ensure Games button resets if closed via News
            const gamesMenuBtn = document.getElementById('gamesMenuBtn');
            if (gamesMenuBtn) gamesMenuBtn.textContent = 'Games';

            if (hamburger) hamburger.textContent = '☰';
        }

        if (toggleNewsBtn && newsSection) {
            toggleNewsBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (newsSection.classList.contains('active')) {
                    closeNews();
                } else {
                    openNews();
                }
            });
        }

        if (closeNewsBtn) {
            closeNewsBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeNews();
            });
        }

        // Close news on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && newsSection.classList.contains('active')) {
                closeNews();
            }
        });

        // --- MOBILE MENU TOGGLE ---
        const hamburger = document.getElementById('hamburger');
        const menuItems = document.querySelector('.menu-items');
        const sidebar = document.querySelector('.sidebar');

        if (hamburger && menuItems) {
            hamburger.addEventListener('click', () => {
                // If an overlay is active, hamburger acts as a global CLOSE button
                const isSidebarActive = sidebar && sidebar.classList.contains('active');
                const isNewsActive = newsSection && newsSection.classList.contains('active');

                if (isSidebarActive) {
                    closeSidebar();
                    return;
                }
                if (isNewsActive) {
                    closeNews();
                    return;
                }

                // Normal menu behavior
                menuItems.classList.toggle('active');
                hamburger.textContent = menuItems.classList.contains('active') ? '✕' : '☰';
            });

            // Close menu when clicking a link
            menuItems.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function () {
                    // Only reset hamburger to ☰ if we aren't opening an overlay
                    if (this.id === 'gamesMenuBtn' || this.id === 'toggleNewsBtn') {
                        menuItems.classList.remove('active');
                        // Stay as ✕ because Sidebar/News will be opened
                        hamburger.textContent = '✕';
                    } else {
                        menuItems.classList.remove('active');
                        hamburger.textContent = '☰';
                    }
                });
            });
        }

    </script>
</body>


</html>