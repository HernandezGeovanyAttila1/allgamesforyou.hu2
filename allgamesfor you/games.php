<?php
// ------------------ DEBUGGING ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ START SESSION ------------------
session_start();

// ------------------ DB CONNECTION ------------------


$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$isLoggedIn = isset($_SESSION["user_id"]);

// ------------------ FETCH ALL CATEGORIES ------------------
$categories = [];
$cat_sql = "SELECT DISTINCT category FROM game_categories ORDER BY category ASC";
if ($cat_result = $conn->query($cat_sql)) {
    while ($cat_row = $cat_result->fetch_assoc()) {
        $categories[] = $cat_row['category'];
    }
}

// ------------------ PAGINATION ------------------
$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$bannedFilter = $isAdmin ? "" : " WHERE is_banned = 0";

// Total count for pagination
$totalGames = 0;
$count_result = $conn->query("SELECT COUNT(*) as total FROM games $bannedFilter");
if ($count_result) {
    $totalGames = (int) $count_result->fetch_assoc()['total'];
}
$totalPages = (int) ceil($totalGames / $perPage);

// ------------------ FETCH GAMES (paginated) ------------------
$games = [];
$bannedWhere = $isAdmin ? "" : " AND g.is_banned = 0";
$stmt = $conn->prepare("SELECT g.*, 
                 GROUP_CONCAT(DISTINCT gc.category SEPARATOR ',') AS categories,
                 (SELECT AVG(rating) FROM game_ratings WHERE game_id = g.game_id) as avg_rating,
                 (SELECT COUNT(*) FROM game_ratings WHERE game_id = g.game_id) as rating_count
                 FROM games g
                 LEFT JOIN game_categories gc ON g.game_id = gc.game_id
                 WHERE 1=1 $bannedWhere
                 GROUP BY g.game_id
                 ORDER BY g.game_id DESC
                 LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $games[] = $row;
}

// ------------------ HANDLE REPORT SUBMISSION ------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['report_submit']) && isset($_SESSION['user_id'])) {
    $game_report_id = (int)$_POST['game_id'];
    $headline = trim($_POST['headline']);
    $report_content = trim($_POST['report_content']);

    if (!empty($headline) && !empty($report_content) && $game_report_id > 0) {
        $stmt = $conn->prepare("INSERT INTO reports (user_id, game_id, headline, report, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $game_report_id, $headline, $report_content);
        if ($stmt->execute()) {
            $report_success = "Your report has been submitted to the admins.";
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('$report_success'); });</script>";
        } else {
            $report_error = "Failed to submit report: " . $conn->error;
            echo "<script>document.addEventListener('DOMContentLoaded', () => { if(typeof showToast === 'function') showToast('$report_error'); });</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="styles.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALL GAMES - The Vault</title>
    <link rel="icon" type="image/png" href="/imgandgifs/logo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Orbitron:wght@400;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --text-light: #ffffff;
            --border-color: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --border: 1px solid rgba(255, 255, 255, 0.08);
            --text-muted: #888888;
        }

        .rating-stars {
            color: #ff8e00;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
        }

        .rating-value {
            font-weight: 700;
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --text-light: #ffffff;
            --border-color: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: rgba(255, 255, 255, 0.05);
        }

        body.bright {
            --bg-mesh-1: #f2f2f2;
            --bg-mesh-2: #ffffff;
            --bg-mesh-3: #dddddd;
            --text-main: #333333;
            --text-light: #000000;
            --border-color: rgba(191, 50, 241, 0.15);
            --glass: rgba(255, 255, 255, 0.8);
            --glass-strong: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --glow: 0 0 15px rgba(191, 50, 241, 0.2);
            --input-bg: #eeeeee;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            transition: color 0.3s ease;
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

        /* Header Styles */
        header {
            display: flex;
            flex-direction: column;
            background: var(--glass-strong);
            backdrop-filter: blur(30px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 2005;
            border-bottom: 1px solid var(--border-color);
            height: 100px; /* Increased height for bigger feel */
            justify-content: center;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
            transition: all 0.4s ease;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 50px;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
            height: 100%;
        }

        @media (max-width: 768px) {
            header { height: 70px; }
            .header-top { padding: 0 20px; }
            .logo-icon { width: 50px !important; height: auto !important; }
            .minimal-nav { display: none !important; }
            .hamburger { display: block !important; cursor: pointer; font-size: 24px; color: var(--text-light); }
            .main-container { margin-top: 0 !important; padding: 20px !important; }
            .page-title { font-size: 1.8rem !important; text-align: center; }
            .page-header { flex-direction: column; align-items: center !important; gap: 15px; }
            .games-hero { padding: 120px 20px 60px !important; }
        }

        .hamburger {
            display: none;
        }

        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            right: -100%;
            top: 60px;
            width: 100%;
            height: calc(100vh - 60px);
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            z-index: 999;
            transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            padding: 40px 20px;
            gap: 15px;
            overflow-y: auto;
        }

        .mobile-menu.active {
            right: 0;
        }

        .mobile-menu a {
            padding: 15px;
            color: var(--text-main);
            text-decoration: none;
            font-family: var(--orbitron);
            font-size: 1.1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 100px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .minimal-nav {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .nav-btn,
        .explore-btn {
            padding: 8px 15px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 8px;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 1px;
        }

        .nav-btn:hover,
        .explore-btn:hover {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: var(--glow);
            filter: brightness(1.2);
        }

        .login-btn {
            background: var(--accent);
            color: #fff;
            border: none;
        }

        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .theme-toggle img {
            width: 24px;
            height: 24px;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Hero Section */
        .games-hero {
            padding: 180px 40px 100px;
            background: linear-gradient(180deg, transparent 0%, var(--bg-mesh-1) 100%),
                        radial-gradient(circle at 50% 50%, rgba(191, 50, 241, 0.15) 0%, transparent 70%);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .hero-badge {
            background: rgba(191, 50, 241, 0.1);
            color: var(--accent);
            padding: 8px 20px;
            border-radius: 50px;
            font-family: var(--orbitron);
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 2px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            text-transform: uppercase;
            box-shadow: var(--glow);
        }

        .hero-title {
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-family: var(--orbitron);
            font-weight: 900;
            color: var(--text-light);
            line-height: 1.1;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: -2px;
            background: linear-gradient(to bottom, var(--text-light) 30%, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            max-width: 600px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        /* Sidebar Side-drawer Style */
        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 450px;
            height: 100vh;
            background: var(--glass-strong);
            backdrop-filter: blur(50px) saturate(200%);
            padding: 60px 40px;
            transform: translateX(100%);
            transition: transform 0.6s cubic-bezier(0.19, 1, 0.22, 1);
            border-left: 1px solid var(--border-color);
            opacity: 1;
            visibility: visible;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            box-shadow: -20px 0 50px rgba(0,0,0,0.5);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            z-index: 2999;
            opacity: 0;
            visibility: hidden;
            transition: 0.4s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .cat-search-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0 15px;
            height: 44px;
            transition: all 0.3s;
        }

        .cat-search-wrapper:focus-within {
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 15px rgba(191, 50, 241, 0.2);
        }

        .cat-search-wrapper i {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .cat-search-wrapper input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            width: 100%;
            font-size: 0.9rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10002;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--glass-strong);
            margin: 10% auto;
            padding: 40px;
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 600px;
            border-radius: 32px;
            box-shadow: var(--shadow), var(--glow);
            position: relative;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .close-modal {
            position: absolute;
            right: 25px;
            top: 20px;
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
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
            margin: 10px 0;
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

        .sidebar h3 {
            font-family: var(--orbitron);
            color: var(--accent);
            margin-bottom: 10px;
            letter-spacing: 5px;
            text-transform: uppercase;
        }

        .sidebar-close-btn {
            position: absolute;
            top: 25px;
            right: 40px;
            font-size: 40px;
            color: var(--text-muted);
            cursor: pointer;
            transition: 0.3s;
        }

        .sidebar-close-btn:hover {
            color: #ff4d4d;
            transform: rotate(90deg);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            width: 100%;
            max-width: 1200px;
            margin-top: 30px;
        }

        .cat-link {
            text-decoration: none;
            color: var(--text-main);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            font-family: var(--orbitron);
            font-size: 0.8rem;
            transition: 0.3s;
            cursor: pointer;
        }

        .cat-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        .hidden-cat {
            display: none !important;
        }

        /* Selected (Light-Up) Button State */
        .cat-link.selected {
            background: var(--accent) !important;
            color: white !important;
            box-shadow: var(--glow);
            border-color: white;
            transform: scale(1.05);
        }

        #clearFiltersBtn:hover {
            background: var(--accent);
            color: white;
            box-shadow: var(--glow);
        }

        .sidebar-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        /* Combined Search & Filter Bar */
        .search-control-panel {
            display: flex;
            gap: 15px;
            width: 100%;
            max-width: 900px;
            background: var(--glass);
            padding: 10px;
            border-radius: 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .search-control-panel:focus-within {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .search-main {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 0 20px;
            border-right: 1px solid var(--border-color);
        }

        .search-main i { color: var(--accent); font-size: 1.1rem; }
        .search-main input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            width: 100%;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
        }

        .sort-control {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 15px;
        }

        .sort-select {
            background: transparent;
            border: none;
            color: var(--text-main);
            font-family: var(--orbitron);
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            outline: none;
            padding: 5px;
        }

        .sort-select option {
            background: var(--bg-mesh-1);
            color: var(--text-main);
        }

        .filter-trigger-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 0 25px;
            height: 50px;
            border-radius: 16px;
            font-family: var(--orbitron);
            font-weight: 800;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            box-shadow: var(--glow);
        }

        .filter-trigger-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.2);
        }

        /* Games Grid Container */
        .main-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 60px 40px;
            flex: 1;
            width: 95%;
            position: relative;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 20px;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 4px;
            font-family: 'Orbitron', sans-serif;
        }

        .upload-btn {
            background: var(--accent);
            padding: 12px 25px;
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            border-radius: 12px;
            box-shadow: var(--glow);
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.8rem;
        }

        .upload-btn:hover {
            transform: translateY(-3px) scale(1.05);
            filter: brightness(1.2);
        }

        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        @media (max-width: 600px) {
            .games-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .content-box h5 {
                font-size: 0.75rem !important;
            }
        }

        .game-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            transition: 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            cursor: pointer;
            border: var(--border);
            position: relative;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }

        .game-card:hover {
            transform: translateY(-10px);
            border-color: var(--accent);
            box-shadow: var(--shadow), var(--glow);
        }

        .img-wrapper {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            position: relative;
            background: #111;
        }

        .game-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.6s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .game-card:hover img {
            transform: scale(1.1);
        }

        .content-box {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 18px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 60%, transparent);
            z-index: 3;
        }

        .game-card h5 {
            font-family: 'Orbitron', sans-serif;
            font-size: 0.9rem;
            color: #ffffff;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: 1px;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8);
        }

        /* ⋯ Button (bottom-right) */
        .card-menu-btn {
            position: absolute;
            bottom: 8px;
            right: 8px;
            z-index: 5;
            background: var(--glass);
            border: none;
            color: var(--text-light);
            font-size: 20px;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background .2s;
        }

        .card-menu-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        /* Dropdown menu */
        .card-menu {
            position: absolute;
            bottom: 45px;
            right: 8px;
            background: var(--glass-strong);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 0;
            display: none;
            flex-direction: column;
            min-width: 140px;
            z-index: 10;
        }

        .card-menu.show {
            display: flex;
        }

        /* Menu buttons */
        .card-menu button {
            background: none;
            border: none;
            color: var(--text-main);
            padding: 8px 12px;
            text-align: left;
            width: 100%;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .card-menu button:hover {
            background: var(--input-bg);
        }

        .card-menu .danger {
            color: #ff5a5a;
        }

        .fav-btn.active {
            color: gold;
        }

        /* Search Bar & Filter Button */
        .search-row {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 30px;
        }

        .search-bar-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 0 25px;
            height: 64px;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            flex: 1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .search-bar-wrapper:focus-within {
            box-shadow: var(--glow), 0 0 0 1px var(--accent);
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .search-bar-wrapper i {
            color: var(--accent);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .search-bar-wrapper input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 500;
            width: 100%;
        }

        .search-bar-wrapper input::placeholder {
            color: var(--text-muted);
        }

        /* Styling for the Games Filter Button next to Search */
        #gamesMenuBtn {
            padding: 0 25px;
            height: 64px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-family: var(--orbitron);
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            letter-spacing: 1px;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        #gamesMenuBtn:hover {
            border-color: var(--accent);
            box-shadow: var(--glow);
            color: #fff;
        }

        @media (max-width: 600px) {
            .search-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            #gamesMenuBtn {
                height: 48px;
                justify-content: center;
            }
            .search-bar-wrapper {
                height: 48px;
            }
        }

        /* Category Ribbon Styling */
        .filter-ribbon {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding: 10px 5px 25px;
            margin-bottom: 20px;
            scrollbar-width: none; /* Hide scrollbar for Firefox */
            -ms-overflow-style: none; /* Hide scrollbar for IE/Edge */
            mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
            -webkit-mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
        }

        .filter-ribbon::-webkit-scrollbar {
            display: none; /* Hide scrollbar for Chrome/Safari */
        }

        .ribbon-pill {
            padding: 10px 22px;
            background: var(--glass);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 50px;
            font-family: var(--orbitron);
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            letter-spacing: 1px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .ribbon-pill:hover {
            border-color: var(--accent);
            background: rgba(191, 50, 241, 0.1);
            transform: translateY(-2px);
        }

        .ribbon-pill.active {
            background: var(--accent);
            color: #fff;
            border-color: #fff;
            box-shadow: var(--glow);
            transform: scale(1.05);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            font-size: 1rem;
            display: none;
            width: 100%;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 40px 0 20px;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 12px;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .pagination a {
            background: var(--glass);
            border: 1px solid var(--border-color);
            color: var(--accent);
        }

        .pagination a:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: var(--glow);
        }

        @media (max-width: 768px) {
            .games-hero {
                padding: 100px 20px 40px !important;
                text-align: center;
            }

            .hero-title {
                font-size: 2.2rem;
                letter-spacing: 2px;
            }

            .hero-subtitle {
                font-size: 0.9rem;
                line-height: 1.5;
            }

            .search-control-panel {
                flex-direction: column;
                gap: 12px;
                padding: 12px;
                border-radius: 16px;
            }

            .search-main {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 12px;
                width: 100%;
            }

            .sort-control {
                width: 100%;
                justify-content: center;
                padding: 5px 0;
            }

            .filter-trigger-btn {
                width: 100%;
                justify-content: center;
            }

            .games-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px;
                padding: 0;
            }

            .main-container {
                padding: 30px 15px;
                width: 100%;
            }

            .filter-ribbon {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 500px) {
            .games-grid {
                grid-template-columns: 1fr !important;
            }

            .pagination {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }

            .pagination a,
            .pagination span {
                width: 100%;
                justify-content: center;
            }

            /* ---------- BOTTOM NAV ---------- */
            .mobile-nav {
                display: flex;
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

            body {
                padding-bottom: 80px;
            }
        }

        /* Footer */
        footer {
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            padding: 40px;
            text-align: center;
            border-top: 1px solid var(--border-color);
            margin-top: 60px;
        }

        /* --- TOAST NOTIFICATIONS --- */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--glass-strong);
            color: var(--text-main);
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(-10px);
        }
    </style>
</head>

<body>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <span class="sidebar-close-btn" id="closeSidebarBtn">&times;</span>
        <h3>Filter by Category</h3>
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 5px;">Select one or more categories</p>
        
        <div class="cat-search-wrapper" style="width: 100%; max-width: 400px; margin: 20px 0;">
            <i class="fas fa-search"></i>
            <input type="text" id="catSearchInput" placeholder="Search categories..." autocomplete="off">
        </div>

        <div class="categories-grid" id="categoriesGrid">
            <?php 
            $count = 0;
            foreach ($categories as $cat): 
                $hiddenClass = ($count >= 12) ? 'hidden-cat' : '';
                ?>
                <div class="cat-link <?php echo $hiddenClass; ?>" data-category="<?php echo htmlspecialchars($cat); ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </div>
            <?php 
                $count++;
            endforeach; 
            ?>
        </div>
        <?php if (count($categories) > 12): ?>
            <button id="showMoreCatsBtn" class="sidebar-btn secondary" style="margin-top: 20px; width: auto; padding: 10px 30px;">
                SHOW ALL CATEGORIES (<?php echo count($categories); ?>)
            </button>
        <?php endif; ?>
        <div class="sidebar-actions">
            <button id="clearFiltersBtn" class="sidebar-btn secondary" style="border: 1px solid var(--accent); color: var(--accent); background: transparent; padding: 15px; border-radius: 12px; font-family: var(--orbitron); font-weight: 700; cursor: pointer; text-transform: uppercase;">Clear Filters</button>
            <button id="okFiltersBtn" class="sidebar-btn primary" style="background: var(--accent); color: #fff; border: none; padding: 15px; border-radius: 12px; font-family: var(--orbitron); font-weight: 700; cursor: pointer; text-transform: uppercase; box-shadow: var(--glow);">Apply Filters</button>
        </div>
    </div>

    <header>
        <div class="header-top">
            <a href="index.php" class="logo-container">
                <img src="imgandgifs/catlogo.png" class="logo-icon">
            </a>
            <div id="hamburger" class="hamburger">☰</div>
            <nav class="minimal-nav">
                <a href="index.php" class="explore-btn"><i class="fas fa-home"></i> BACK TO HOME</a>
                <a href="#" id="themeToggle" title="Toggle Theme" class="theme-toggle">
                    <img id="themeIcon" src="imgandgifs/sun.svg" alt="Toggle Theme">
                </a>
            </nav>
        </div>
    </header>

    <div id="mobileMenu" class="mobile-menu">
        <a href="index.php">HOME</a>
        <a href="about.php">ABOUT</a>
        <a href="games.php">GALLERY</a>
        <a href="custumersupport.php">HELP</a>
        <?php if ($isLoggedIn): ?>
            <a href="profile.php">PROFILE (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            <a href="?action=logout">LOGOUT</a>
        <?php else: ?>
            <a href="auth.php">LOGIN</a>
        <?php endif; ?>
    </div>

    <section class="games-hero">
        <div class="hero-badge">Premium Collection</div>
        <h1 class="hero-title">The Vault</h1>
        <p class="hero-subtitle">Step into a world of endless entertainment. Explore our curated selection of high-quality games.</p>
        
        <div class="search-control-panel">
            <div class="search-main">
                <i class="fas fa-search"></i>
                <input type="text" id="gamesSearch" placeholder="Search for games..." autocomplete="off">
            </div>
            <div class="sort-control">
                <i class="fas fa-sort-amount-down" style="color: var(--accent); font-size: 0.9rem;"></i>
                <select id="gameSort" class="sort-select">
                    <option value="newest">Newest First</option>
                    <option value="rating">Top Rated</option>
                    <option value="alpha">A - Z</option>
                </select>
            </div>
            <button id="gamesMenuBtn" class="filter-trigger-btn">
                <i class="fas fa-sliders-h"></i> FILTERS
            </button>
        </div>
    </section>

    <div class="main-container">
        <div class="page-header" style="border: none; margin-bottom: 20px;">
            <?php if ($isLoggedIn): ?>
                <a href="add_games.php" class="upload-btn"><i class="fas fa-plus-circle"></i> UPLOAD NEW GAME</a>
            <?php endif; ?>
        </div>

        <!-- Horizontal Filter Ribbon -->
        <div class="filter-ribbon" id="filterRibbon">
            <div class="ribbon-pill active" data-category="all">ALL GAMES</div>
            <?php 
            // Show top 15 categories in the ribbon
            $topCats = array_slice($categories, 0, 15);
            foreach ($topCats as $tcat): ?>
                <div class="ribbon-pill" data-category="<?php echo htmlspecialchars($tcat); ?>">
                    <?php echo htmlspecialchars($tcat); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="games-grid" id="gamesGrid">
            <?php foreach ($games as $dg): ?>

                <?php
                $gameId = $dg['game_id'] ?? $dg['id'];
                $title = htmlspecialchars($dg['title'] ?? 'Unknown');
                $img = htmlspecialchars($dg['main_image'] ?? 'imgandgifs/logo.png');
                $cats = htmlspecialchars($dg['categories'] ?? '');
                $isFav = in_array($gameId, $_SESSION['favourites'] ?? []);
                ?>

                <div class="game-card" data-game-id="<?php echo $gameId; ?>"
                    data-categories="<?php echo htmlspecialchars($dg['categories'] ?? ''); ?>"
                    onclick="location.href='game.php?id=<?php echo $gameId; ?>'">

                    <!-- ⋯ BUTTON -->
                    <button class="card-menu-btn" onclick="toggleCardMenu(event, '<?php echo $gameId; ?>')">⋯</button>

                    <!-- MENU (ID MUST MATCH JS) -->
                    <div class="card-menu" id="menu-<?php echo $gameId; ?>">

                        <button class="fav-btn <?= in_array($gameId, $_SESSION['favourites'] ?? []) ? 'active' : '' ?>"
                            onclick="toggleFavourite(event, <?php echo $gameId; ?>)">
                            ⭐ Favourite
                        </button>

                        <button onclick="copyGameLink(event, <?php echo $gameId; ?>)">
                            📋 Copy link
                        </button>

                        <button class="danger"
                            onclick="openReportModal(event, <?php echo $gameId; ?>, '<?php echo addslashes($title); ?>')">
                            🚩 Report / Flag
                        </button>



                    </div>

                    <div class="img-wrapper">
                        <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>">
                        <div class="content-box">
                            <h5>
                                <?php echo $title; ?>
                            </h5>
                            <div class="rating-stars">
                                <i class="fas fa-star"></i>
                                <span class="rating-value">
                                    <?php echo $dg['avg_rating'] ? round($dg['avg_rating'], 1) : '0.0'; ?>
                                </span>
                                <span style="opacity:0.6; font-size: 0.65rem;">(
                                    <?php echo $dg['rating_count']; ?>)
                                </span>
                            </div>
                        </div>
                    </div>

                </div>


            <?php endforeach; ?>
        </div>

        <p class="no-results" id="noResults">No games found matching your search.</p>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="games.php?page=<?php echo $currentPage - 1; ?>"><i class="fas fa-chevron-left"></i> PREV</a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> PREV</span>
                <?php endif; ?>

                <span class="page-info">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="games.php?page=<?php echo $currentPage + 1; ?>">NEXT <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled">NEXT <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <footer>
        <p>Games © 2026 • Privacy Policy</p>
    </footer>

    <!-- PREMIUM MOBILE NAV -->
    <!--<nav class="mobile-nav">-->
    <!--    <a href="index.php" class="mobile-nav-item">-->
    <!--        <img src="/imgandgifs/home.svg" alt="Home">-->
    <!--        <span>Home</span>-->
    <!--    </a>-->
    <!--    <a href="games.php" class="mobile-nav-item active">-->
    <!--        <img src="/imgandgifs/folder.svg" alt="Games">-->
    <!--        <span>Games</span>-->
    <!--    </a>-->
    <!--    <a href="profile.php" class="mobile-nav-item">-->
    <!--        <img src="/imgandgifs/user.svg" alt="Profile">-->
    <!--        <span>Profile</span>-->
    <!--    </a>-->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <!--        <a href="admin.php" class="mobile-nav-item">-->
    <!--            <img src="/imgandgifs/tool.svg" alt="Admin">-->
    <!--            <span>Admin</span>-->
    <!--        </a>-->
    <!--    <?php endif; ?>-->
    <!--</nav>-->

    <script>
        // --- Sidebar & Smart Filtering Logic ---
        const gamesSearch = document.getElementById('gamesSearch');
        const gameSort = document.getElementById('gameSort');
        const gamesGrid = document.getElementById('gamesGrid');
        const noResults = document.getElementById('noResults');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const gamesMenuBtn = document.getElementById('gamesMenuBtn');
        const closeSidebarBtn = document.getElementById('closeSidebarBtn');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const okFiltersBtn = document.getElementById('okFiltersBtn');
        const catLinks = document.querySelectorAll('.cat-link');
        const ribbonPills = document.querySelectorAll('.ribbon-pill');

        let selectedCategories = [];

        // Open Sidebar
        gamesMenuBtn.addEventListener('click', (e) => {
            e.preventDefault();
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        // Close Sidebar
        const closeSidebar = () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        closeSidebarBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);
        okFiltersBtn.addEventListener('click', closeSidebar);

        // Ribbon Logic
        ribbonPills.forEach(pill => {
            pill.addEventListener('click', () => {
                const category = pill.getAttribute('data-category');
                
                if (category === 'all') {
                    selectedCategories = [];
                    ribbonPills.forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    catLinks.forEach(cl => cl.classList.remove('selected'));
                } else {
                    document.querySelector('.ribbon-pill[data-category="all"]').classList.remove('active');
                    pill.classList.toggle('active');
                    
                    if (selectedCategories.includes(category)) {
                        selectedCategories = selectedCategories.filter(c => c !== category);
                    } else {
                        selectedCategories.push(category);
                    }
                    
                    catLinks.forEach(cl => {
                        if (cl.getAttribute('data-category') === category) {
                            cl.classList.toggle('selected');
                        }
                    });
                }
                
                if (selectedCategories.length === 0) {
                    document.querySelector('.ribbon-pill[data-category="all"]').classList.add('active');
                }
                filterGames();
            });
        });

        // Mobile Menu Toggling
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');

        if (hamburger && mobileMenu) {
            hamburger.addEventListener('click', (e) => {
                e.stopPropagation();
                mobileMenu.classList.toggle('active');
                hamburger.textContent = mobileMenu.classList.contains('active') ? '✕' : '☰';
            });

            document.addEventListener('click', (e) => {
                if (mobileMenu.classList.contains('active') && !mobileMenu.contains(e.target) && e.target !== hamburger) {
                    mobileMenu.classList.remove('active');
                    hamburger.textContent = '☰';
                }
            });
        }

        const catSearchInput = document.getElementById('catSearchInput');
        const showMoreCatsBtn = document.getElementById('showMoreCatsBtn');

        if (catSearchInput) {
            catSearchInput.addEventListener('input', () => {
                const query = catSearchInput.value.toLowerCase().trim();
                if (query.length > 0 && showMoreCatsBtn) showMoreCatsBtn.style.display = 'none';
                else if (query.length === 0 && showMoreCatsBtn && document.querySelectorAll('.hidden-cat').length > 0) showMoreCatsBtn.style.display = '';

                catLinks.forEach(link => {
                    const category = link.getAttribute('data-category').toLowerCase();
                    if (category.includes(query)) {
                        if (query.length > 0) link.classList.remove('hidden-cat');
                        link.style.display = '';
                    } else link.style.display = 'none';
                });
            });
        }

        if (showMoreCatsBtn) {
            showMoreCatsBtn.addEventListener('click', () => {
                document.querySelectorAll('.hidden-cat').forEach(cat => cat.classList.remove('hidden-cat'));
                showMoreCatsBtn.style.display = 'none';
            });
        }

        // Sidebar Category Selection
        catLinks.forEach(link => {
            link.addEventListener('click', () => {
                const category = link.getAttribute('data-category');
                link.classList.toggle('selected');

                if (selectedCategories.includes(category)) {
                    selectedCategories = selectedCategories.filter(c => c !== category);
                } else {
                    selectedCategories.push(category);
                }
                
                ribbonPills.forEach(rp => {
                    if (rp.getAttribute('data-category') === category) {
                        rp.classList.toggle('active');
                    }
                });
                
                const allPill = document.querySelector('.ribbon-pill[data-category="all"]');
                if (selectedCategories.length > 0) allPill.classList.remove('active');
                else allPill.classList.add('active');

                filterGames();
            });
        });

        // Clear All
        clearFiltersBtn.addEventListener('click', () => {
            selectedCategories = [];
            catLinks.forEach(l => { l.classList.remove('selected'); l.style.display = ''; });
            ribbonPills.forEach(rp => rp.classList.remove('active'));
            document.querySelector('.ribbon-pill[data-category="all"]').classList.add('active');
            if (document.getElementById('catSearchInput')) document.getElementById('catSearchInput').value = '';
            filterGames();
        });

        // Master Filter & Sort
        function filterGames() {
            const query = gamesSearch.value.toLowerCase().trim();
            const sortBy = gameSort.value;
            const cards = Array.from(gamesGrid.querySelectorAll('.game-card'));
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.querySelector('h5')?.textContent.toLowerCase() || '';
                const cardCats = (card.getAttribute('data-categories') || '').split(',');
                
                const matchesSearch = title.includes(query);
                const matchesCategory = selectedCategories.length === 0 || selectedCategories.some(cat => cardCats.includes(cat));

                if (matchesSearch && matchesCategory) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Sorting logic
            if (visibleCount > 1) {
                cards.sort((a, b) => {
                    if (sortBy === 'newest') {
                        return parseInt(b.getAttribute('data-game-id')) - parseInt(a.getAttribute('data-game-id'));
                    } else if (sortBy === 'rating') {
                        const ra = parseFloat(a.querySelector('.rating-value').textContent);
                        const rb = parseFloat(b.querySelector('.rating-value').textContent);
                        return rb - ra;
                    } else if (sortBy === 'alpha') {
                        return a.querySelector('h5').textContent.localeCompare(b.querySelector('h5').textContent);
                    }
                    return 0;
                });
                
                cards.forEach(card => gamesGrid.appendChild(card));
            }

            if (noResults) noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        gamesSearch.addEventListener('input', filterGames);
        gameSort.addEventListener('change', filterGames);
        // ================= CARD MENU =================
        function toggleCardMenu(event, id) {
            event.preventDefault();
            event.stopPropagation();

            const menu = document.getElementById("menu-" + id);
            const allMenus = document.querySelectorAll(".card-menu");

            // Close all other menus
            allMenus.forEach(m => {
                if (m !== menu) m.style.display = "none";
            });

            // Toggle current menu
            menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
        }

        // Close menus when clicking anywhere else
        document.addEventListener("click", () => {
            document.querySelectorAll(".card-menu").forEach(menu => {
                menu.style.display = "none";
            });
        });


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
            event.stopPropagation();

            const url = `${location.origin}/game.php?id=${gameId}`;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(url)
                    .then(() => showToast("Copied!"))
                    .catch(() => fallbackCopy(url));
            } else {
                fallbackCopy(url);
            }

            function fallbackCopy(text) {
                const textarea = document.createElement("textarea");
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand("copy");
                    showToast("Copied!");
                } catch {
                    showToast("Failed to copy");
                }
                textarea.remove();
            }
        }







        // ================= TOAST SYSTEM =================
        function showToast(message) {
            let toast = document.querySelector('.toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.className = 'toast';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function openReportModal(event, gameId, gameName) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // If not logged in redirect
            <?php if (!$isLoggedIn): ?>
                alert('Please login to report games');
                window.location.href = 'auth.php?redirect=games.php';
                return;
            <?php endif; ?>

            document.getElementById('reportGameId').value = gameId;
            document.getElementById('reportGameTitle').textContent = gameName;
            document.getElementById('reportModal').style.display = "block";
        }

        function closeReportModal() {
            document.getElementById('reportModal').style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('reportModal');
            if (event.target == modal) {
                closeReportModal();
            }
        }

        // ================= ADMIN ACTIONS =================
        function adminBanGame(event, gameId) {
            event.preventDefault();
            event.stopPropagation();
            if (!confirm("Are you sure you want to BAN this game? It will be hidden from users.")) return;

            const formData = new FormData();
            formData.append('action', 'ban_game');
            formData.append('game_id', gameId);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === 'ok') {
                    showToast("Game Banned");
                    location.reload();
                } else {
                    showToast("Error: " + data);
                }
            })
            .catch(err => console.error(err));
        }

        function adminUnbanGame(event, gameId) {
            event.preventDefault();
            event.stopPropagation();
            if (!confirm("Are you sure you want to UNBAN this game?")) return;

            const formData = new FormData();
            formData.append('action', 'unban_game');
            formData.append('game_id', gameId);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                if (data.trim() === 'ok') {
                    showToast("Game Unbanned");
                    location.reload();
                } else {
                    showToast("Error: " + data);
                }
            })
            .catch(err => console.error(err));
        }

        // ================= THEME TOGGLE =================
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.className = savedTheme;
        updateThemeUI();

        function updateThemeUI() {
            const isDark = document.body.classList.contains('dark');
            if (themeIcon) {
                themeIcon.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";
                themeIcon.style.transform = isDark ? 'rotate(180deg)' : 'rotate(0deg)';
                themeIcon.style.filter = isDark 
                    ? 'drop-shadow(0 0 8px rgba(191, 50, 241, 0.6))'
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
    </script>

    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeReportModal()">&times;</span>
            <h2 class="section-title">Report: <span id="reportGameTitle"></span></h2>
            <form method="POST" class="report-form">
                <input type="hidden" name="game_id" id="reportGameId" value="">
                <label>Headline</label>
                <input type="text" name="headline" placeholder="Brief summary of the issue..." required>
                <label>Report Content</label>
                <textarea name="report_content" rows="4" placeholder="Detailed description..." required></textarea>
                <button type="submit" name="report_submit">Submit Report</button>
            </form>
        </div>
    </div>
</body>

</html>
<?php
$conn->close();
?>
