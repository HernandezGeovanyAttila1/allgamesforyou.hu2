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
function set_remember_cookie_index($value) {
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>GameHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Poppins,sans-serif;background:#fff;color:#111}

/* NAV */
.navbar{
    display:flex;justify-content:space-between;align-items:center;
    padding:18px 40px;border-bottom:1px solid #eee
}
.nav-left{display:flex;align-items:center;gap:40px}
.nav-left img{height:34px}
.nav-menu{display:flex;gap:22px;font-weight:500}
.nav-menu a{text-decoration:none;color:#222}
.login-btn{
    background:#111;color:#fff;padding:8px 18px;border-radius:20px;
    text-decoration:none;font-size:14px
}
.profile-img{width:36px;height:36px;border-radius:50%;object-fit:cover}

/* HERO */
.hero{
    display:grid;grid-template-columns:1fr 1.2fr;
    gap:40px;padding:60px 40px;align-items:center
}
.hero h1{font-size:52px;font-weight:700;line-height:1.1}
.hero-search{margin-top:30px;display:flex;gap:14px}
.hero-search input{
    padding:14px 22px;border-radius:30px;border:1px solid #ddd;width:320px
}
.hero-search button{
    padding:14px 26px;border-radius:30px;
    background:#0f3d3e;color:#fff;border:none
}
.hero img{width:100%;border-radius:30px}

/* SECTIONS */
.section{padding:40px}
.section-header{
    display:flex;justify-content:space-between;
    align-items:center;margin-bottom:20px
}

/* GAME CARDS */
.game-row{display:flex;gap:20px}
.game-card{width:200px}
.game-card img{
    width:100%;border-radius:16px;
    box-shadow:0 14px 30px rgba(0,0,0,.15)
}
.game-card h4{margin-top:10px;font-size:15px}

/* TRENDING */
.trending{display:flex;flex-direction:column;gap:12px}
.trending img{width:100%;border-radius:12px}

/* GRID */
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:30px}

/* BANNER */
.banner{margin:40px}
.banner img{width:100%;border-radius:30px}
</style>
</head>

<body>

<!-- NAV -->
<div class="navbar">
    <div class="nav-left">
        <img src="imgandgifs/catlogo.png">
        <div class="nav-menu">
            <a href="#">Home</a>
            <a href="#">Games</a>
            <a href="#">Reviews</a>
            <a href="#">News</a>
            <a href="#">Community</a>
        </div>
    </div>
    <div>
        <?php if(isset($_SESSION['user_id'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/login.png') ?>" class="profile-img">
        <?php else: ?>
            <a href="auth.php" class="login-btn">Sign In</a>
        <?php endif; ?>
    </div>
</div>

<!-- HERO -->
<section class="hero">
    <div>
        <h1>Explore<br>Top Games</h1>
        <div class="hero-search">
            <input placeholder="Search games...">
            <button>Discover</button>
        </div>
    </div>
    <img src="<?= htmlspecialchars($popular[0]['main_image'] ?? 'images/hero.jpg') ?>">
</section>

<!-- POPULAR -->
<section class="section">
<div class="section-header">
    <h2>Popular Games</h2>
    <span>Trending Now</span>
</div>

<div style="display:grid;grid-template-columns:4fr 1fr;gap:30px">

    <div class="game-row">
        <?php foreach($popular as $g): ?>
        <div class="game-card">
            <a href="game.php?id=<?= $g['game_id'] ?>" style="text-decoration:none;color:inherit">
                <img src="<?= htmlspecialchars($g['main_image']) ?>">
                <h4><?= htmlspecialchars($g['title']) ?></h4>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="trending">
        <?php foreach($trending as $t): ?>
            <img src="<?= htmlspecialchars($t['main_image']) ?>">
        <?php endforeach; ?>
    </div>

</div>
</section>

<!-- NEW RELEASES -->
<section class="section">
<div class="section-header">
    <h2>New Releases</h2>
</div>

<div class="grid">
<?php foreach($new as $n): ?>
    <div class="game-card">
        <a href="game.php?id=<?= $n['game_id'] ?>" style="text-decoration:none;color:inherit">
            <img src="<?= htmlspecialchars($n['main_image']) ?>">
            <h4><?= htmlspecialchars($n['title']) ?></h4>
        </a>
    </div>
<?php endforeach; ?>
</div>
</section>

<!-- BANNER -->
<div class="banner">
    <img src="<?= htmlspecialchars($new[0]['main_image'] ?? 'images/banner.jpg') ?>">
</div>

</body>
</html>


