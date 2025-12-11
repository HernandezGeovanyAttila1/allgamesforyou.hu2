<?php
// ------------------ DEBUGGING ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ START SESSION ------------------
// Remove the custom session path — let PHP handle it
session_start();

// ------------------ DATABASE CONNECTION ------------------
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ------------------ HANDLE LOGOUT ------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
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
<title>ALL GAMES FOR YOU</title>
<link rel="icon" type="image/png" sizes="128x128" href="imgandgifs/logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>
:root {   
    --primary:  #25033d;
    --secondary: #6d0f7d;
    --accent: #9557a1;
    --bg: #1f0c33;
    --card-bg: rgba(255,255,255,0.05);
    --hover-bg: #50135b;
    --text-light: #d9cddb;
    --text-dark: #222;
}
* { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family: 'Poppins', sans-serif;
    //background: linear-gradient(135deg, #6e4975, #904abb);
    background: linear-gradient(180deg, var(--primary), var(--secondary));
    color: var(--text-light);
}

/* BETTER HEADER BACKGROUND FIT */
header {
    display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center;
    padding:15px 20px;
    background-image:url("imgandgifs/new_bg.png");
    background-size: cover;         /* was already good */
    background-position: top center; /* better alignment */
    background-repeat: no-repeat;
    background-attachment: fixed;    /* smoother effect */
    gap:10px; 
    position:sticky; top:0; 
    z-index:100; 
    box-shadow:0 2px 10px rgba(0,0,0,0.3);
}

header img.logo { max-width:150px; height:auto; }

.search-box { flex:1 1 200px; position:relative; }
.search-box input {
    width:100%; max-width:300px; min-width:120px; padding:10px 15px; border-radius:25px; border:none; outline:none; transition:0.3s; font-size:1em;
}
.search-box input:focus { transform:scale(1.02); box-shadow:0 0 10px var(--accent); }

.profile img { width:60px; border-radius:50%; cursor:pointer; transition:0.3s; }
.profile img:hover { transform:scale(1.1); }

.container { display:grid; grid-template-columns:220px 1fr; gap:15px; padding:15px; height:calc(100vh - 90px); }

/* --- rest of your CSS unchanged --- */
.sidebar {
    background:var(--hover-bg); padding:20px; border-radius:15px;
    display:flex; flex-direction:column; gap:10px; overflow-y:auto; max-height:calc(100vh - 120px);
}
.sidebar h3 { margin-bottom:10px; text-align:center; font-weight:600; }
.sidebar a { text-decoration:none; color:var(--text-light); padding:8px 12px; border-radius:8px; display:block; transition:0.3s; }
.sidebar a:hover, .sidebar a.active { background:var(--accent); font-weight:bold; }

.main { background:var(--card-bg); border-radius:15px; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:20px; }

.add-game-btn { padding:10px 15px; background:#db8e1d; color:#ebe6ed; border-radius:10px; text-decoration:none; font-weight:600; transition:0.3s; align-self:flex-start; }
.add-game-btn:hover { background:#fb8c00; }

/* FEATURED */
.featured {
   
    background:linear-gradient(135deg, var(--secondary), var(--primary)); border-radius:15px; padding:15px; text-align:center;
    box-shadow:0 4px 15px rgba(0,0,0,0.3);
}
.featured h2 { font-size:1.8em; margin-bottom:10px; }
.featured p { font-size:1em; color:#99899c; }

/* GAME CARDS */
.game-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px; }
.game-card {
    background:var(--hover-bg); border-radius:12px; padding:10px; display:flex; flex-direction:column; justify-content:space-between;
    transition:0.3s; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,0.3);
}
.game-card:hover { transform:translateY(-5px); background:var(--accent); }
.game-card img { width:100%; max-height:200px; object-fit:cover; border-radius:10px; margin-bottom:10px; }
.game-card h3 { margin-bottom:5px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.game-card p { font-size:0.9em; color:#b397b8; display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden; }

/* Responsive */
@media(max-width: 1024px) { .container { grid-template-columns:180px 1fr; } }
@media(max-width: 768px) {
    .container { grid-template-columns:1fr; height:auto; }
    .sidebar { flex-direction:row; overflow-x:auto; padding:10px; gap:8px; max-height:unset; }
    .sidebar a { flex:0 0 auto; text-align:center; }
    .game-grid { grid-template-columns:1fr 1fr; }
}
@media(max-width: 480px) {
    .game-grid { grid-template-columns:1fr; }
    header { flex-direction:column; align-items:flex-start; }
    .search-box input { width:100%; }
}
</style>
</head>
<body>

<header>
    <img src="imgandgifs/C4T.png" alt="logo" class="logo">

    <div class="search-box">
        <input id="searchInput" type="text" placeholder="Search games...">
    </div>

    <!-- NEW: PROFILE + MESSAGES BUTTON WRAPPER -->
    <div class="profile" style="display:flex; align-items:center; gap:15px;">

        <?php if(isset($_SESSION['user_id'])): ?>

            
            <!-- MESSAGES BUTTON -->
        <a href="<?php echo isset($_SESSION['user_id']) ? 'message.php' : 'auth.php?redirect=message.php'; ?>" class="msg-btn"style="
        background: #774280;
        padding: 10px 14px;
        border-radius: 10px;
        font-weight: 600;
        color: white;
        text-decoration: none;
        transition: 0.3s;
">
            Message
</a>


            <!-- PROFILE PIC -->
            <a href="profile.php">
                <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/moving_login.gif'); ?>" 
                     alt="profile">
            </a>

        <?php else: ?>

            <a href="auth.php"><img src="imgandgifs/moving_login.gif" alt="login"></a>

        <?php endif; ?>
    </div>

</header>

<div class="container">
    <nav class="sidebar">
        <h3>Categories</h3>
        <a data-category="all" class="active">All</a>
        <?php foreach($categories as $cat): ?>
            <a data-category="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></a>
        <?php endforeach; ?>
    </nav>

    <main class="main">
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="add_games.php" class="add-game-btn">Add New Game</a>
        <?php endif; ?>

        <section class="featured">
            <h2>ðŸ”¥ Featured Game: Galaxy Blaster</h2>
            <p>Fly through galaxies, battle alien fleets, and save humanity in this high-speed space shooter!</p>
        </section>

        <section class="game-grid" id="gameGrid">
            <?php foreach($games as $game): 
                $categories_str = $game['categories'] ?? '';
                $categories_array = explode(',', $categories_str);
                $data_category = htmlspecialchars(implode('|', $categories_array)); 
            ?>
            <div class="game-card" data-category="<?php echo $data_category; ?>" data-game-id="<?php echo $game['game_id']; ?>">
                <a href="game.php?id=<?php echo $game['game_id']; ?>" style="text-decoration:none;color:inherit;">
                    <img src="<?php echo htmlspecialchars($game['main_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>">
                    <h3><?php echo htmlspecialchars($game['title']); ?></h3>
                    <p><?php echo htmlspecialchars($game['description']); ?></p>
                </a>
            </div>
            <?php endforeach; ?>
        </section>
    </main>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const categoryLinks = document.querySelectorAll('.sidebar a');
let allGames = [];

document.querySelectorAll('.game-card').forEach(card => {
    const title = card.querySelector('h3').textContent.toLowerCase();
    const description = card.querySelector('p').textContent.toLowerCase();
    const categories = card.dataset.category.split('|');
    allGames.push({ element: card, title, description, categories });
});

let debounceTimer;
searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(filterGames, 150);
});

categoryLinks.forEach(link => link.addEventListener('click', () => {
    categoryLinks.forEach(l => l.classList.remove('active'));
    link.classList.add('active');
    filterGames();
}));

function filterGames() {
    const val = searchInput.value.toLowerCase();
    const activeCategory = document.querySelector('.sidebar a.active')?.dataset.category || 'all';

    allGames.forEach(game => {
        const matchesCategory = (activeCategory === 'all' || game.categories.includes(activeCategory));
        const matchesSearch = game.title.includes(val) || game.description.includes(val);
        game.element.style.display = (matchesCategory && matchesSearch) ? 'block' : 'none';
    });
}

async function refreshGames() {
    try {
        const res = await fetch('fetch_games.php?json=1');
        const games = await res.json();
        games.forEach(game => {
            let existing = allGames.find(g => g.element.dataset.gameId == game.game_id);
            if(!existing) {
                const card = document.createElement('div');
                card.className = 'game-card';
                card.dataset.gameId = game.game_id;
                card.dataset.category = game.categories ? game.categories.split(',').join('|') : '';
                card.innerHTML = `<a href="game.php?id=${game.game_id}" style="text-decoration:none;color:inherit;">
                    <img src="${game.main_image}" alt="${game.title}" loading="lazy">
                    <h3>${game.title}</h3>
                    <p>${game.description}</p>
                </a>`;
                document.getElementById('gameGrid').appendChild(card);
                allGames.push({ element: card, title: game.title.toLowerCase(), description: game.description.toLowerCase(), categories: game.categories ? game.categories.split(',') : [] });
            }
        });
        filterGames();
    } catch (e) { console.error('Failed to refresh games:', e); }
}

setInterval(refreshGames, 5000);
</script>

</body>
</html>
