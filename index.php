<?php
// ------------------ DEBUGGING ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ START SESSION ------------------
ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
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
<style>
body { 
    margin:0; 
    font-family:Poppins,sans-serif; 
    background:linear-gradient(180deg,#6a1b9a,#4a0072); 
    color:#fff; 
}
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-image: url("imgandgifs/header_bg.png");
    background-repeat: no-repeat;
    background-size: cover;
    background-position: center;
    flex-wrap: wrap;
    gap: 10px;
}
header img.logo { max-width: 200px; height:auto; }
.search-box { flex: 1 1 200px; margin:5px 10px; }
.search-box input { width:100%; max-width:300px; min-width:120px; padding:10px; border-radius:20px; border:none; transition: all 0.3s ease; }
.profile img { width:60px; border-radius:50%; cursor:pointer; }
.container { display:grid; grid-template-columns:220px 1fr; gap:10px; height:calc(100vh-90px); padding:10px; }
.sidebar { background:#7b1fa2; padding:20px; border-radius:15px; display:flex; flex-direction:column; gap:15px; }
.sidebar a { text-decoration:none; color:white; padding:8px 12px; border-radius:8px; display:block; }
.sidebar a.active { background:#ba68c8; font-weight:bold; }
.main { background:rgba(255,255,255,0.05); border-radius:15px; padding:20px; overflow-y:auto; }
.featured { background:linear-gradient(135deg,#9c27b0,#7b1fa2); border-radius:15px; text-align:center; padding:15px; margin-bottom:25px; }
.featured h2 { font-size:1.8em; margin:10px 0; }
.game-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:20px; }
.game-card { background:#8e24aa; border-radius:12px; padding:10px; transition:0.3s; }
.game-card:hover { background:#686ec8; transform:translateY(-5px); }
.game-card img { width:100%; border-radius:10px; }
.game-card h3 { margin:10px 0 5px; }
.game-card p { font-size:0.9em; color:#e1bee7; }
.comment { margin-left:10px; background:#f7f7f7; padding:8px; border-radius:8px; margin-bottom:8px; color:black; }
form textarea { width:100%; padding:5px; border-radius:5px; border:1px solid #ccc; }
form button { padding:5px 10px; border:none; border-radius:5px; background:#007bff; color:white; cursor:pointer; }
form button:hover { background:#0056b3; }
.add-game-btn { padding:8px 12px; background:#ff9800; color:white; border:none; border-radius:8px; cursor:pointer; margin-bottom:15px; display:inline-block; text-decoration:none; }
.add-game-btn:hover { background:#fb8c00; }

@media(max-width: 768px){
    .container { grid-template-columns: 1fr; height:auto; }
    header { justify-content: center; }
    .search-box input { max-width: 100%; }
    .sidebar { flex-direction: row; flex-wrap: wrap; gap: 8px; padding: 10px; }
    .sidebar a { flex: 1 1 calc(50% - 10px); text-align: center; }
    .game-grid { grid-template-columns: 1fr 1fr; }
}
@media(max-width: 480px){
    .game-grid { grid-template-columns: 1fr; }
    .profile img { width:50px; }
}
</style>
</head>
<body>

<header>
  <img src="imgandgifs/C4T.png" alt="logo" class="logo">
  <div class="search-box">
    <input id="searchInput" type="text" placeholder="Search games...">
  </div>
  <div class="profile">
  <?php if(isset($_SESSION['user_id'])): ?>
    <a href="profile.php">
      <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'imgandgifs/login.png'); ?>" alt="profile">
    </a>
  <?php else: ?>
    <a href="auth.php"><img src="imgandgifs/login.png" alt="login"></a>
  <?php endif; ?>
  </div>
</header>

<div class="container">
  <nav class="sidebar">
    <h3>Categories</h3>
    <a data-category="all" class="active">ðŸŒŸ All</a>
    <?php foreach($categories as $cat): ?>
        <a data-category="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></a>
    <?php endforeach; ?>
  </nav>

  <main class="main">
    <?php if(isset($_SESSION['user_id'])): ?>
        <a href="add_games.php" class="add-game-btn">âž• Add New Game</a>
    <?php endif; ?>

    <section class="featured">
      <h2>ðŸ”¥ Featured Game: Galaxy Blaster</h2>
      <p>Fly through galaxies, battle alien fleets, and save humanity in this high-speed space shooter!</p>
    </section>

    <section class="game-grid" id="gameGrid">
    <?php if(!empty($games)): ?>
        <?php foreach($games as $game): 
            $categories_str = $game['categories'] ?? '';
            $categories_array = explode(',', $categories_str);
            $data_category = htmlspecialchars(implode('|', $categories_array)); // separate by | for JS filter
        ?>
        <div class="game-card" data-category="<?php echo $data_category; ?>">
            <a href="game.php?id=<?php echo $game['game_id']; ?>" style="text-decoration:none;color:inherit;">
                <img src="<?php echo htmlspecialchars($game['main_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>">
                <h3><?php echo htmlspecialchars($game['title']); ?></h3>
                <p><?php echo htmlspecialchars($game['description']); ?></p>
            </a>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No games found.</p>
    <?php endif; ?>
    </section>
  </main>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const gameCards = document.querySelectorAll('.game-card');
const categoryLinks = document.querySelectorAll('.sidebar a');

searchInput.addEventListener('input', () => {
    const val = searchInput.value.toLowerCase();
    gameCards.forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        card.style.display = title.includes(val) ? 'block' : 'none';
    });
});

categoryLinks.forEach(link => {
    link.addEventListener('click', () => {
        categoryLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        const category = link.getAttribute('data-category');
        gameCards.forEach(card => {
            const categories = card.getAttribute('data-category').split('|');
            card.style.display = (category==='all' || categories.includes(category)) ? 'block' : 'none';
        });
    });
});
</script>
</body>
</html>
