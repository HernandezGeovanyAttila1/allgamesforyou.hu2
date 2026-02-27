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
   
   // ------------------ FETCH GAMES WITH CATEGORIES ------------------
   $games = [];
   $games_sql = "SELECT g.*, GROUP_CONCAT(gc.category SEPARATOR ',') AS categories
                 FROM games g
                 LEFT JOIN game_categories gc ON g.game_id = gc.game_id
                 GROUP BY g.game_id";
   if ($result = $conn->query($games_sql)) {
       while ($row = $result->fetch_assoc()) {
           $row['is_api'] = false;
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

    // ------------------ FETCH GAMES FROM FREETOGAME API ------------------
    $apiUrl = "https://www.freetogame.com/api/games";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode === 200) {
        $apiGames = json_decode($response, true);
        if (is_array($apiGames)) {
            foreach ($apiGames as $apiGame) {
                // Determine Category
                $category = $apiGame['genre'] ?? 'Other';
                
                // Add to games array
                $games[] = [
                    'game_id' => $apiGame['id'], // Note: FreeToGame IDs are numeric
                    'title' => $apiGame['title'],
                    'main_image' => $apiGame['thumbnail'],
                    'description' => $apiGame['short_description'],
                    'categories' => $category,
                    'is_api' => true
                ];

                // Add to categories array if it doesn't exist
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }
        }
    }

    // Sort combined categories alphabetically
    sort($categories);
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
         --secondary: #f7f3e8;
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
         --accent: #bf32f1; /* More Vivid Electric Purple */
         --bg-mesh-1: #0b0712;
         --bg-mesh-2: #1e0b3c; /* Deeper Purple Node */
         --bg-mesh-3: #050308;
         --card-bg: rgba(191, 50, 241, 0.1); /* Tinted with accent */
         --hover-bg: rgba(191, 50, 241, 0.25);
         --hover-bg: rgba(191, 50, 241, 0.25);
         --text-light: #e6e0eb;
         --text-muted: rgba(230, 224, 235, 0.6); /* Muted for dark mode */
         --border: rgba(191, 50, 241, 0.15); /* Purple Border */
         --glass: rgba(15, 10, 21, 0.75);
         --glass-strong: rgba(10, 5, 20, 0.9);
         --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
         --glow: 0 0 30px rgba(191, 50, 241, 0.5); /* Stronger Glow */
         }
         body.bright {
         --primary: #f7f3e8; 
         --secondary: #fcfaf2; 
         --accent: #9b59b6; /* Richer Purple for light mode */
         --bg-mesh-1: #f7f3e8; 
         --bg-mesh-2: #fdf2ff; /* Purplish Cream */
         --bg-mesh-3: #e8dbf2; /* More Purple Beige */
         --card-bg: rgba(255, 255, 255, 0.4);
         --hover-bg: rgba(155, 89, 182, 0.12);
         --hover-bg: rgba(155, 89, 182, 0.12);
         --text-light: #2c2433; 
         --text-muted: rgba(44, 36, 51, 0.7); /* Muted for bright mode */
         --border: rgba(155, 89, 182, 0.2);
         --glass: rgba(247, 243, 232, 0.8);
         --glass-strong: rgba(255, 255, 255, 0.95);
         --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
         --glow: 0 0 20px rgba(155, 89, 182, 0.2);
         }
         * {
         box-sizing: border-box;
         margin: 0;
         padding: 0;
         }
         body {
         font-family: 'Poppins', sans-serif;
         background: var(--bg-mesh-1);
         color: var(--text-light);
         min-height: 100vh;
         overflow-x: hidden;
         letter-spacing: -0.01em;
         position: relative;
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
         transition: all 0.8s cubic-bezier(0.19, 1, 0.22, 1);
         }
         @keyframes meshFlow {
         0% { background-position: 0% 0%; }
         50% { background-position: 100% 100%; }
         100% { background-position: 0% 0%; }
         }
         /* --- Global Typography --- */
         h1, h2, h3, .menu-items li a {
         font-family: 'Orbitron', sans-serif;
         letter-spacing: 0.05em;
         }
         /* --- Custom Scrollbar --- */
         ::-webkit-scrollbar { width: 8px; }
         ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
         ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }
         ::-webkit-scrollbar-thumb:hover { background: #b79dc2; }
         header {
         display: flex;
         flex-wrap: wrap;
         justify-content: space-between;
         align-items: center;
         padding: 15px 40px;
         background: var(--glass);
         backdrop-filter: blur(30px) saturate(150%);
         -webkit-backdrop-filter: blur(30px) saturate(150%);
         gap: 15px;
         position: sticky;
         top: 0;
         z-index: 1000;
         border-bottom: 1px solid var(--border);
         box-shadow: var(--shadow);
         }
         .menu-bar {
         display: flex;
         justify-content: flex-end;
         align-items: center;
         flex-grow: 1;
         z-index: 1000;
         }
         .menu-bar .menu-items {
         list-style: none;
         display: flex;
         gap: 20px;
         margin: 0;
         padding: 0;
         }
         .menu-bar .menu-items li a {
         text-decoration: none;
         color: var(--text-light);  
         padding: 10px 20px;
         border-radius: 12px;
         transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
         font-weight: 600;
         font-size: 0.9rem;
         text-transform: uppercase;
         letter-spacing: 1.5px;
         border: 1px solid transparent;
         }
         .menu-bar .menu-items li a:hover {
         background: rgba(149, 87, 161, 0.1);
         color: var(--accent);
         border-color: var(--accent);
         transform: translateY(-2px);
         box-shadow: 0 5px 15px rgba(149, 87, 161, 0.2);
         text-shadow: 0 0 10px rgba(149, 87, 161, 0.2);
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
         top: 80px;
         flex-direction: column;
         background: rgba(27, 23, 30, 0.95);
         backdrop-filter: blur(15px);
         width: 100%;
         height: calc(100vh - 80px);
         text-align: center;
         transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
         padding: 40px 0;
         gap: 30px !important;
         border-top: 1px solid rgba(255,255,255,0.1);
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
         .search-box {
         margin: 0 25px;
         flex: 0 1 350px;
         position: relative;
         }
         .search-box input {
         width: 100%;
         padding: 12px 25px;
         border-radius: 30px;
         border: 1px solid rgba(255, 255, 255, 0.08);
         background: rgba(255, 255, 255, 0.04);
         color: #fff;
         transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
         font-size: 0.9rem;
         backdrop-filter: blur(20px);
         box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.2);
         }
         .search-box input:focus {
         background: var(--glass-strong);
         border-color: var(--accent);
         outline: none;
         box-shadow: var(--glow), inset 0 2px 10px rgba(0, 0, 0, 0.1);
         width: 105%;
         transform: translateX(-2.5%) translateY(-2px);
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
         .container {
         display: grid;
         grid-template-columns: 1fr; /* Full width now */
         gap: 15px;
         padding: 15px;
         height: calc(100vh - 90px);
         overflow-x: hidden;
         overflow-y: auto;
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
         padding: 160px 40px 60px; /* Increased top padding to show text better */
         z-index: 995;
         display: flex;
         flex-direction: column;
         gap: 25px;
         transform: translateY(-100%);
         transition: transform 0.6s cubic-bezier(0.19, 1, 0.22, 1);
         border-bottom: 2px solid var(--border);
         box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
         opacity: 0;
         overflow-y: auto;
         max-height: 90vh; /* Increased max height */
         visibility: hidden; /* Prevent interaction when hidden */
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
         color: #d9cddb; /* Brighter visibility by default */
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
         color: #2c2433; /* Darker text for bright mode */
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
         background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
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
         box-shadow: 0 10px 20px rgba(0,0,0,0.3);
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
         from { opacity: 0; transform: scale(0.5); }
         to { opacity: 1; transform: scale(1); }
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
         border: 1px solid rgba(255, 255, 255, 0.1);
         background: rgba(0, 0, 0, 0.2);
         color: #fff;
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
         from { opacity: 0; transform: translateY(20px); }
         to { opacity: 1; transform: translateY(0); }
         }
         .add-game-btn {
         padding: 12px 24px;
         background: linear-gradient(135deg, var(--accent), #774280);
         color: #fff;
         border-radius: 12px;
         text-decoration: none;
         font-weight: 700;
         text-transform: uppercase;
         letter-spacing: 1px;
         transition: 0.4s cubic-bezier(0.19, 1, 0.22, 1);
         align-self: flex-start;
         margin-right: 20px;
         box-shadow: 0 4px 15px rgba(191, 50, 241, 0.3);
         border: 1px solid rgba(255, 255, 255, 0.1);
         }
         .add-game-btn:hover {
         transform: translateY(-2px) scale(1.05);
         box-shadow: 0 8px 25px rgba(191, 50, 241, 0.5);
         filter: brightness(1.1);
         }
         .featured {
         /*background: #f7f3e8;*/
         border-radius: 15px;
         padding: 15px;
         text-align: center;
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
         }
         .featured h2 {
         font-family: "Orbitron", sans-serif;
         text-transform: uppercase;
         color: var(--accent);
         letter-spacing: 0.25em;
         text-shadow: var(--glow);
         margin-bottom: 20px;
         }
         .game-grid {
         display: grid;
         grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
         gap: 24px;
         padding: 10px;
         }
         .game-card {
         background: var(--glass);
         backdrop-filter: blur(25px);
         -webkit-backdrop-filter: blur(25px);
         border: 1px solid var(--border);
         border-radius: 30px;
         padding: 20px;
         display: flex;
         flex-direction: column;
         transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
         cursor: pointer;
         position: relative;
         overflow: hidden;
         box-shadow: var(--shadow);
         }
         .game-card::before {
         content: '';
         position: absolute;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent 50%);
         pointer-events: none;
         }
         .game-card:hover {
         transform: translateY(-10px) scale(1.02);
         background: rgba(255, 255, 255, 0.05);
         border-color: var(--accent);
         box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(149, 87, 161, 0.15);
         }
         .game-card .img-wrapper {
         width: 100%;
         height: 160px;
         border-radius: 12px;
         overflow: hidden;
         margin-bottom: 15px;
         background: #111;
         }
         .game-card img {
         width: 100%;
         height: 100%;
         object-fit: cover;
         transition: transform 0.6s cubic-bezier(0.19, 1, 0.22, 1);
         }
         .game-card:hover img {
         transform: scale(1.1);
         }
         .game-card h3 {
         font-size: 1.1rem;
         font-weight: 700;
         color: var(--text-light);
         margin: 0 0 10px 0;
         line-height: 1.3;
         transition: color 0.3s ease;
         }
         .game-card:hover h3 {
         color: #ff9d00;
         }
         .game-card p {
         font-size: 0.85rem;
         color: var(--text-muted);
         line-height: 1.5;
         margin: 0;
         display: -webkit-box;
         -webkit-line-clamp: 2;
         -webkit-box-orient: vertical;
         overflow: hidden;
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
         z-index: 1100 !important; /* Higher than sidebar (995) and news (990) */
         padding: 10px 15px;
         height: auto;
         flex-direction: row;
         justify-content: space-between;
         align-items: center;
         gap: 10px;
         background: rgba(10, 5, 15, 0.95); /* More opaque for contrast */
         backdrop-filter: blur(20px);
         border-bottom: 1px solid rgba(255, 255, 255, 0.1);
         }
         .logo-wrapper {
         scale: 0.85;
         transform-origin: left;
         }
         .search-box {
         order: 3;
         margin: 5px 0;
         flex: 0 0 100%;
         height: 40px;
         }
         .search-box input {
         height: 100%;
         font-size: 0.9rem;
         padding-left: 45px;
         }
         .search-box i {
         left: 15px;
         font-size: 14px;
         }
         .container {
         grid-template-columns: 1fr !important;
         height: auto;
         padding: 10px;
         margin-top: 5px;
         }
         .sidebar {
         padding: 70px 15px 30px;
         gap: 18px;
         border-bottom: 1px solid rgba(255, 255, 255, 0.1);
         }
         .sidebar h3 {
         font-size: 1rem;
         letter-spacing: 3px;
         margin-bottom: 0;
         }
         .cat-controls {
         gap: 8px;
         }
         .cat-search-input {
         padding: 10px 18px;
         font-size: 0.85rem;
         }
         .clear-filters-btn {
         padding: 8px 12px;
         font-size: 0.7rem;
         }
         .categories-grid {
         grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
         gap: 8px;
         padding: 5px;
         }
         .sidebar a {
         padding: 10px 8px;
         font-size: 0.7rem;
         letter-spacing: 0.5px;
         border-radius: 12px;
         }
         .sidebar-close-btn {
         display: none !important; /* Hide separate close button on mobile */
         }
         .game-grid {
         grid-template-columns: repeat(2, 1fr); /* Force 2 columns for pro look */
         gap: 10px;
         padding: 5px;
         }
         .game-card {
         padding: 10px;
         border-radius: 18px;
         }
         .game-card .img-wrapper {
         height: 110px;
         border-radius: 10px;
         margin-bottom: 10px;
         }
         .game-card h3 {
         font-size: 0.9rem;
         margin-bottom: 6px;
         }
         .game-card p {
         font-size: 0.75rem;
         -webkit-line-clamp: 1; /* More compact on mobile */
         }
         .featured-card {
         min-width: 160px;
         height: 220px;
         }
         .featured-info h4 {
         font-size: 0.85rem;
         }
         }
         /* Mobile-Specific Refinements (Global) */
         @media(max-width: 768px) {
         :root {
         --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
         }
         .game-card {
         box-shadow: var(--card-shadow);
         background: rgba(255, 255, 255, 0.03);
         }
         /* Touch Feedback */
         .game-card:active {
         transform: scale(0.98);
         background: rgba(255, 255, 255, 0.06);
         }
         .sidebar a:active {
         transform: scale(0.95);
         background: rgba(255, 255, 255, 0.1);
         }
         /* Better Scrolling */
         .sidebar, .main, body {
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
         top: 15px;
         right: 15px;
         background: rgba(15, 10, 21, 0.95);
         backdrop-filter: blur(15px);
         border: 1px solid rgba(255, 255, 255, 0.1);
         border-radius: 12px;
         display: none;
         flex-direction: column;
         width: 140px;
         z-index: 100;
         overflow: hidden;
         box-shadow: 0 10px 25px rgba(0,0,0,0.5);
         }
         .card-menu button {
         background: transparent;
         border: none;
         color: rgba(255, 255, 255, 0.7);
         padding: 10px 15px;
         text-align: left;
         cursor: pointer;
         font-size: 0.8rem;
         font-weight: 600;
         transition: 0.3s;
         text-transform: uppercase;
         letter-spacing: 0.5px;
         }
         .card-menu button:hover {
         background: rgba(255, 255, 255, 0.05);
         color: #fff;
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
         top: 0;
         left: 0;
         width: 100vw;
         height: 100vh;
         background: var(--bg-mesh-1); /* Use theme variable */
         backdrop-filter: blur(20px);
         z-index: 990; /* Below header (1000) */
         overflow-y: auto;
         transform: translateY(-100%);
         transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
         padding: 100px 20px 40px; /* Top padding to clear header */
         margin: 0;
         border: none;
         box-shadow: none;
         opacity: 1; /* Always opaque when animating in */
         display: block; /* No more display:none/max-height logic */
         }
         .news-section.active {
         transform: translateY(0);
         max-height: 100vh; /* Reset from previous logic */
         }
         /* Body Scroll Lock */
         body.news-open {
         overflow: hidden;
         }
         .news-section h2 {
         font-family: "Orbitron", sans-serif;
         font-weight: 800;
         text-transform: uppercase;
         color: var(--text-light); /* Changed to variable */
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
         from { opacity: 0; transform: translateY(-30px); }
         to { opacity: 1; transform: translateY(0); }
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
         z-index: 995; /* Above news content but below header */
         transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
         border: 1px solid rgba(255, 255, 255, 0.1);
         backdrop-filter: blur(15px);
         display: none; /* Hide close button on desktop if desired, but keep for mobile */
         }
         .news-close-btn:hover {
         background: rgba(255, 0, 0, 0.2);
         color: #ff3e3e; /* Visible red */
         border-color: rgba(255, 0, 0, 0.5);
         transform: scale(1.1) rotate(90deg);
         }
         @media (max-width: 768px) {
         .news-section h2 { font-size: 1.8rem; letter-spacing: 0.1em; }
         .news-close-btn { width: 50px; height: 50px; font-size: 30px; top: 15px; right: 15px; }
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
         0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 62, 62, 0.7); }
         70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(255, 62, 62, 0); }
         100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 62, 62, 0); }
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
         background: linear-gradient(90deg, #0a0a0a 25%, #151515 50%, #0a0a0a 75%);
         background-size: 200% 100%;
         animation: loading 2s infinite;
         border-radius: 10px;
         }
         @keyframes loading {
         0% { background-position: 200% 0; }
         100% { background-position: -200% 0; }
         }
         .skeleton-card {
         height: 450px;
         background: rgba(255, 255, 255, 0.01);
         border-radius: 20px;
         padding: 25px;
         display: flex;
         flex-direction: column;
         gap: 20px;
         border: 1px solid rgba(255, 255, 255, 0.02);
         }
         .skeleton-img { height: 200px; width: 100%; }
         .skeleton-text { height: 18px; width: 40%; border-radius: 20px; }
         .skeleton-title { height: 30px; width: 95%; }
         .skeleton-desc { height: 100px; width: 100%; }
         @media (max-width: 768px) {
         .news-container { grid-template-columns: 1fr; }
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
         .toast.success { border-left-color: #2ecc71; }
         .toast.error { border-left-color: #e74c3c; }
         @keyframes toastIn {
         from { transform: translateX(100%); opacity: 0; }
         to { transform: translateX(0); opacity: 1; }
         }
         @keyframes toastOut {
         to { transform: translateX(120%); opacity: 0; }
         }
      </style>
   </head>
   <body class="dark">
      <header>
         <!-- TOAST CONTAINER -->
         <div id="toast-container" class="toast-container"></div>
         <img src="imgandgifs/catlogo.png" alt="logo" class="logo">
         <!-- Menu Bar -->
         <nav class="menu-bar">
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="add_games.php" class="add-game-btn">Add New Game</a>
            <?php endif; ?>
            <ul class="menu-items">
               <li><a href="#" id="gamesMenuBtn">Games</a></li>
               <li><a href="#" id="toggleNewsBtn" style="color: var(--accent); font-weight: bold; text-shadow: var(--glow);">Gaming News</a></li>
               <li><a href="about.php">About</a></li>
            </ul>
         </nav>
         <div class="search-box">
            <input id="searchInput" type="text" placeholder="[ SEARCH_START ]">
         </div>
         <div class="profile" style="display:flex; align-items:center; gap:15px;">
            <a href="#" id="themeToggle" title="Toggle Theme" style="padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 40px; height: 40px; transition: all 0.3s ease; background: rgba(255,255,255,0.05);"> <img id="themeIcon" src="imgandgifs/sun.svg" alt="Toggle Theme" style="width: 24px; height: 24px; transition: transform 0.5s cubic-bezier(0.19, 1, 0.22, 1); pointer-events: none;"> </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo isset($_SESSION['user_id']) ? 'message.php' : 'auth.php?redirect=message.php'; ?>"
               class="msg-btn"
               style="background: #774280; padding: 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; transition: 0.3s;">
            <img src="imgandgifs/message-circle.svg" 
               alt="Message"
               style="width: 22px; height: 22px; pointer-events: none;">
            </a>
            <a href="profile.php">
            <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/moving_login.gif'); ?>"
               alt="profile">
            </a>
            <?php else: ?>
            <a href="auth.php"><img src="imgandgifs/moving_login.gif" alt="login"></a>
            <?php endif; ?>
            <div class="hamburger" id="hamburger">☰</div>
         </div>
      </header>
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
               <a data-category="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></a>
               <?php endforeach; ?>
            </div>
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
                            74528, 4432, 3543, 11931, 41494, 4062, 2191, 4353, 2953, 16181, 
                            3012, 3042, 4160, 34220, 25097, 4235, 32, 44690, 25827, 2462, 
                            1030, 21835, 23565, 3070, 3841, 4161, 4514, 50655, 12536, 10213,
                            7425,   // Titanfall 2
                            2139,   // Mad Max
                            321,    // The Saboteur
                            262,    // Remember Me
                            4013,   // Syndicate
                            4126    // Vanquish
                        ];
                        
                        // Shuffle and pick 20
                        shuffle($allGemIds);
                        $selectedIds = array_slice($allGemIds, 0, 20);
                        $targetIds = implode(',', $selectedIds);
                        
                        $apiUrl = "https://api.rawg.io/api/games?key={$apiKey}&ids={$targetIds}&page_size=20";
                        
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
                        <img draggable="false" src="<?php echo htmlspecialchars($bg_image); ?>"
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
            <section class="news-section">
               <div class="news-close-btn" id="closeNewsBtn">×</div>
               <h2><span class="live-pulse"></span> Gaming Pulse</h2>
               <div id="news-container" class="news-container">
                  <!-- News items will be injected here via JS -->
                  <p style="text-align:center; color:#ccc; width:100%;">Loading latest gaming pulse...</p>
               </div>
            </section>
            <section class="game-grid" id="gameGrid">
               <?php 
                  $banned_games = $_SESSION['banned_games'] ?? [];
                  foreach ($games as $game):
                      if (in_array((string)$game['game_id'], $banned_games)) continue; // Skip banned games
                      
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
                        <?= !$isLoggedIn ? 'disabled' : '' ?>
                        >
                     ⭐Favourite
                     </button>
                     <button onclick="copyGameLink(event, <?php echo $game['game_id']; ?>)">📋 Copy link</button>
                     <button class="danger" onclick="disableGame(event, <?php echo $game['game_id']; ?>, '<?php echo addslashes(htmlspecialchars($game['title'])); ?>')">🚫
                     Ban</button>
                  </div>
                  <!-- kattintható tartalom -->
                  <a href="game.php?id=<?php echo $game['game_id']; ?>" style="text-decoration:none;color:inherit;">
                     <div class="img-wrapper">
                        <img src="<?php echo htmlspecialchars($game['main_image']); ?>">
                     </div>
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
         
         
         // ================= TOAST SYSTEM =================
         function showToast(message, type = 'success') {
             const container = document.getElementById('toast-container');
             if (!container) return;
         
             const toast = document.createElement("div");
             toast.className = `toast ${type}`;
             toast.innerHTML = `<span>${message}</span>`;
             
             setTimeout(() => {
                 toast.style.animation = 'toastOut 0.5s forwards';
                 setTimeout(() => toast.remove(), 500);
             }, 3000);
             
             container.appendChild(toast);
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
         document.body.classList.add(savedTheme);
         
         function updateThemeUI() {
         const isDark = document.body.classList.contains('dark');
         const img = themeToggleBtn ? themeToggleBtn.querySelector('img') : null;
         
         if (img) {
         // Swap icons
         img.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";
         
         // Animation + glow
         img.style.transform = isDark
             ? 'rotate(180deg) scale(1)'
             : 'rotate(0deg) scale(1.1)';
         
         img.style.filter = isDark
             ? 'drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))'
             : 'drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))';
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
             checkLoop();
             track.style.transform = `translateX(${positionX}px)`;
         });
         
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
             // Close Categories if open
             if (typeof closeSidebar === 'function') closeSidebar();
             
             newsSection.classList.add('active');
             if (hamburger) hamburger.textContent = '✕';
             document.body.classList.add('news-open');
             fetchGamingNews();
         }
         
         function closeNews() {
             const hamburger = document.getElementById('hamburger');
             newsSection.classList.remove('active');
             document.body.classList.remove('news-open');
             // Ensure Games button resets if closed via News
             const gamesMenuBtn = document.getElementById('gamesMenuBtn');
             if (gamesMenuBtn) gamesMenuBtn.textContent = 'Games';
             if (hamburger) hamburger.textContent = '☰';
         }
         
         if (toggleNewsBtn && newsSection) {
             toggleNewsBtn.addEventListener('click', function(e) {
                 e.preventDefault();
                 if (newsSection.classList.contains('active')) {
                     closeNews();
                 } else {
                     openNews();
                 }
             });
         }
         
         if (closeNewsBtn) {
             closeNewsBtn.addEventListener('click', function(e) {
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
                 link.addEventListener('click', function() {
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