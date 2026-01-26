<?php

session_start();

/* ================= REMEMBER ME AUTO LOGIN ================= */

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {

    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.profile_img
        FROM users u
        INNER JOIN user_tokens t ON u.user_id = t.user_id
        WHERE t.token = ? AND t.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Login user
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['profile_img'] = $user['profile_img'];

        // Rotate token (security)
        $newToken = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("
            UPDATE user_tokens
            SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
            WHERE user_id = ?
        ");
        $stmt->bind_param("si", $newToken, $user['user_id']);
        $stmt->execute();

        setcookie(
            "remember_token",
            $newToken,
            time() + (86400 * 30),
            "/",
            "",
            true,
            true
        );
    }
}


ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

/* ---------- DB ---------- */
$conn = new mysqli(
    "localhost",
    "skdneoaa",
    "t3YnVb0HN**40f",
    "skdneoaa_Felhasznalok"
);
if ($conn->connect_error) die("DB connection failed");

/* ---------- ALL GAMES ---------- */
$games = [];
$sql = "
SELECT g.*, GROUP_CONCAT(gc.category SEPARATOR ', ') AS categories
FROM games g
LEFT JOIN game_categories gc ON g.game_id = gc.game_id
GROUP BY g.game_id
";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) $games[] = $row;

/* ---------- POPULAR (random for now) ---------- */
$popular = array_slice($games, 0, 4);

/* ---------- TRENDING (next 3) ---------- */
$trending = array_slice($games, 4, 3);

/* ---------- NEW RELEASES ---------- */
usort($games, fn($a,$b)=>strtotime($b['created_at'])-strtotime($a['created_at']));
$new = array_slice($games, 0, 4);
?>
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

