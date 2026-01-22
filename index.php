<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ---------- DB ---------- */
$conn = new mysqli("localhost", "skdneoaa", "t3YnVb0HN**40f", "skdneoaa_Felhasznalok");
if ($conn->connect_error) die("DB error");

/* ---------- FETCH GAMES ---------- */
$games = [];
$sql = "SELECT g.*, GROUP_CONCAT(gc.category SEPARATOR ',') AS categories
        FROM games g
        LEFT JOIN game_categories gc ON g.game_id = gc.game_id
        GROUP BY g.game_id";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) $games[] = $row;

/* ---------- FETCH CATEGORIES ---------- */
$categories = [];
$cat = $conn->query("SELECT DISTINCT category FROM game_categories ORDER BY category");
while($c = $cat->fetch_assoc()) $categories[] = $c['category'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Game Showcase</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>
* { box-sizing:border-box; margin:0; padding:0; }

body {
    font-family:Poppins,sans-serif;
    background:#fff;
    color:#111;
}

/* ---------- NAV ---------- */
nav {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:20px 40px;
    border-bottom:1px solid #eee;
    position:sticky;
    top:0;
    background:#fff;
    z-index:100;
}

nav img { height:40px; }

.search input {
    padding:12px 20px;
    border-radius:30px;
    border:1px solid #ddd;
    min-width:280px;
}

/* ---------- HERO ---------- */
.hero {
    padding:80px 40px 40px;
    max-width:1400px;
    margin:auto;
}

.hero h1 {
    font-size:48px;
    margin-bottom:10px;
}

.hero p {
    color:#666;
    font-size:18px;
}

/* ---------- CATEGORY FILTER ---------- */
.categories {
    display:flex;
    gap:12px;
    padding:0 40px;
    overflow-x:auto;
    margin-bottom:40px;
}

.categories button {
    border:1px solid #ddd;
    background:#fff;
    padding:10px 18px;
    border-radius:30px;
    cursor:pointer;
    font-weight:500;
}

.categories button.active {
    background:#111;
    color:#fff;
}

/* ---------- GRID ---------- */
.grid {
    max-width:1400px;
    margin:auto;
    padding:0 40px 60px;
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:40px;
}

/* ---------- CARD ---------- */
.card {
    cursor:pointer;
    transition:.3s;
}

.card:hover {
    transform:translateY(-8px);
}

.card img {
    width:100%;
    aspect-ratio:3/4;
    object-fit:cover;
    border-radius:20px;
    box-shadow:0 20px 40px rgba(0,0,0,.12);
}

.card h3 {
    margin-top:14px;
    font-size:18px;
}

.card span {
    color:#777;
    font-size:14px;
}

/* ---------- MENU ---------- */
.menu-btn {
    position:absolute;
    top:14px;
    right:14px;
    background:rgba(255,255,255,.85);
    border:none;
    border-radius:8px;
    padding:4px 8px;
    cursor:pointer;
}

.menu {
    position:absolute;
    top:44px;
    right:14px;
    background:#fff;
    border:1px solid #eee;
    border-radius:12px;
    display:none;
}

.menu button {
    padding:10px 14px;
    background:none;
    border:none;
    width:100%;
    text-align:left;
}
/* ---------- AUTH UI ---------- */
.auth {
    display:flex;
    align-items:center;
    gap:14px;
}

.login-btn {
    padding:10px 20px;
    border-radius:30px;
    border:1px solid #ddd;
    text-decoration:none;
    color:#111;
    font-weight:500;
}

.login-btn:hover {
    background:#111;
    color:#fff;
}

/* Profile */
.profile-wrap {
    position:relative;
}

.profile-img {
    width:42px;
    height:42px;
    border-radius:50%;
    object-fit:cover;
    cursor:pointer;
    border:1px solid #eee;
}

.profile-menu {
    position:absolute;
    top:52px;
    right:0;
    background:#fff;
    border:1px solid #eee;
    border-radius:14px;
    min-width:180px;
    display:none;
    box-shadow:0 20px 40px rgba(0,0,0,.12);
    overflow:hidden;
}

.profile-menu a {
    display:block;
    padding:12px 16px;
    text-decoration:none;
    color:#111;
    font-size:14px;
}

.profile-menu a:hover {
    background:#f5f5f5;
}


</style>
</head>

<body>

<nav>
    <img src="imgandgifs/catlogo.png">

    <div class="search">
        <input id="searchInput" placeholder="Search games">
    </div>

    <div class="auth">
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="profile-wrap">
                <img
                    src="<?= htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/login.png') ?>"
                    class="profile-img"
                    onclick="toggleProfileMenu()"
                >
                <div class="profile-menu" id="profileMenu">
                    <a href="profile.php">Profile</a>
                    <a href="message.php">Messages</a>
                    <a href="?action=logout">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="auth.php" class="login-btn">Login</a>
        <?php endif; ?>
    </div>
</nav>


<section class="hero">
    <h1>Explore Top Games</h1>
    <p>Discover popular and newly added titles</p>
</section>

<div class="categories">
    <button class="active" data-cat="all">All</button>
    <?php foreach($categories as $c): ?>
        <button data-cat="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></button>
    <?php endforeach; ?>
</div>

<section class="grid" id="grid">
<?php foreach($games as $g):
    $cats = explode(',', $g['categories']);
?>
    <div class="card" data-cat="<?=htmlspecialchars(implode('|',$cats))?>">
        <div style="position:relative">
            <button class="menu-btn" onclick="toggleMenu(event,this)">⋯</button>
            <div class="menu">
                <button onclick="copyLink(event,<?=$g['game_id']?>)">📋 Copy link</button>
                <button style="color:red">🚫 Disable</button>
            </div>

            <a href="game.php?id=<?=$g['game_id']?>" style="text-decoration:none;color:inherit">
                <img src="<?=htmlspecialchars($g['main_image'])?>">
                <h3><?=htmlspecialchars($g['title'])?></h3>
                <span><?=htmlspecialchars($g['categories'])?></span>
            </a>
        </div>
    </div>
<?php endforeach; ?>
</section>

<script>
function toggleMenu(e,btn){
    e.stopPropagation();
    const m = btn.nextElementSibling;
    m.style.display = m.style.display==='block'?'none':'block';
}

function copyLink(e,id){
    e.stopPropagation();
    navigator.clipboard.writeText(`${location.origin}/game.php?id=${id}`);
}

/* SEARCH */
searchInput.addEventListener('input',()=>{
    document.querySelectorAll('.card').forEach(c=>{
        c.style.display = c.innerText.toLowerCase().includes(searchInput.value.toLowerCase()) ? 'block':'none';
    });
});

/* CATEGORY */
document.querySelectorAll('.categories button').forEach(btn=>{
    btn.onclick=()=>{
        document.querySelectorAll('.categories button').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');

        const cat = btn.dataset.cat;
        document.querySelectorAll('.card').forEach(c=>{
            const cats = c.dataset.cat.split('|');
            c.style.display = (cat==='all'||cats.includes(cat))?'block':'none';
        });
    };
});


function toggleProfileMenu(){
    const m = document.getElementById('profileMenu');
    m.style.display = m.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('click',e=>{
    const menu = document.getElementById('profileMenu');
    if(menu && !e.target.closest('.profile-wrap')){
        menu.style.display='none';
    }
});





</script>

</body>
</html>
