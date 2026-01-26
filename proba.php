<?php
session_start();
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
body{
    font-family:Poppins,sans-serif;
    background:#fff;
    color:#111;
}

/* ================= NAVBAR ================= */
.navbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 40px;
    border-bottom:1px solid #eee;
}
.nav-left{
    display:flex;
    align-items:center;
    gap:40px;
}
.nav-left img{height:34px}
.nav-menu{
    display:flex;
    gap:22px;
    font-weight:500;
}
.nav-menu a{
    text-decoration:none;
    color:#222;
}
.nav-right{
    display:flex;
    align-items:center;
    gap:18px;
}
.login-btn{
    padding:8px 18px;
    border-radius:20px;
    background:#111;
    color:#fff;
    text-decoration:none;
    font-size:14px;
}

/* ================= HERO ================= */
.hero{
    display:grid;
    grid-template-columns:1fr 1.2fr;
    gap:40px;
    padding:60px 40px;
    align-items:center;
}
.hero h1{
    font-size:52px;
    font-weight:700;
    line-height:1.1;
}
.hero-search{
    margin-top:30px;
    display:flex;
    gap:14px;
}
.hero-search input{
    padding:14px 22px;
    border-radius:30px;
    border:1px solid #ddd;
    width:320px;
}
.hero-search button{
    padding:14px 26px;
    border-radius:30px;
    background:#0f3d3e;
    color:#fff;
    border:none;
    cursor:pointer;
}
.hero img{
    width:100%;
    border-radius:30px;
}

/* ================= SECTIONS ================= */
.section{
    padding:40px;
}
.section-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.section-header h2{font-size:22px}

/* ================= GAME ROW ================= */
.game-row{
    display:flex;
    gap:20px;
}
.game-card{
    width:200px;
}
.game-card img{
    width:100%;
    border-radius:16px;
    box-shadow:0 14px 30px rgba(0,0,0,.15);
}
.game-card h4{
    margin-top:10px;
    font-size:15px;
}

/* ================= TRENDING ================= */
.trending{
    display:flex;
    flex-direction:column;
    gap:12px;
}
.trending img{
    width:100%;
    border-radius:12px;
}

/* ================= GRID ================= */
.grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:30px;
}
.grid .game-card{width:100%}

/* ================= FOOTER BANNER ================= */
.banner{
    margin:40px;
}
.banner img{
    width:100%;
    border-radius:30px;
}
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
    <div class="nav-right">
        <?php if(isset($_SESSION['user_id'])): ?>
            <img src="<?= $_SESSION['profile_img'] ?>" style="width:36px;height:36px;border-radius:50%">
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
            <input placeholder="Search">
            <button>Discover</button>
        </div>
    </div>
    <img src="images/hero.jpg">
</section>

<!-- POPULAR -->
<section class="section">
    <div class="section-header">
        <h2>Popular Games</h2>
        <span>Trending Now</span>
    </div>
    <div style="display:grid;grid-template-columns:4fr 1fr;gap:30px">
        <div class="game-row">
            <div class="game-card"><img src="images/game1.jpg"><h4>Elden Ring</h4></div>
            <div class="game-card"><img src="images/game2.jpg"><h4>Cyberpunk 2077</h4></div>
            <div class="game-card"><img src="images/game3.jpg"><h4>Hogwarts</h4></div>
            <div class="game-card"><img src="images/game4.jpg"><h4>Starfield</h4></div>
        </div>
        <div class="trending">
            <img src="images/t1.jpg">
            <img src="images/t2.jpg">
            <img src="images/t3.jpg">
        </div>
    </div>
</section>

<!-- NEW RELEASES -->
<section class="section">
    <div class="section-header">
        <h2>New Releases</h2>
    </div>
    <div class="grid">
        <div class="game-card"><img src="images/game5.jpg"><h4>Final Fantasy</h4></div>
        <div class="game-card"><img src="images/game6.jpg"><h4>Resident Evil 4</h4></div>
        <div class="game-card"><img src="images/game7.jpg"><h4>Forza</h4></div>
        <div class="game-card"><img src="images/game8.jpg"><h4>Star Wars</h4></div>
    </div>
</section>

<!-- BANNER -->
<div class="banner">
    <img src="images/banner.jpg">
</div>

</body>
</html>
