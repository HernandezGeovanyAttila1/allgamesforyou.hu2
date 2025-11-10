<?php
// ------------------ DEBUGGING ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ START SESSION ------------------
session_start();

// ------------------ DATABASE CONNECTION ------------------
$servername = "localhost";
$db_username = "skdneoaa"; // <-- replace with your cPanel DB username
$db_password = "t3YnVb0HN**40f";         // <-- replace with your MySQL password
$database = "skdneoaa_Felhasznalok";    // <-- your DB name

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALL GAMES FOR YOU</title>
<link rel="icon" type="image/png" sizes="128x128" href="imgandgifs/logo.svg">
<style>
/* ---------- CSS ---------- */
body { margin:0; font-family:Poppins,sans-serif; background:linear-gradient(180deg,#6a1b9a,#4a0072); color:#fff; }
header { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#4a0072; }
.logo { width:300px; height:auto; }
.search-box input { padding:10px; border-radius:20px; border:none; }
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
</style>
</head>
<body>

<!-- HEADER -->
<header>
  <img src="imgandgifs/C4T.png" alt="logo" class="logo">
  <div class="search-box">
    <input id="searchInput" type="text" placeholder="Search games...">
  </div>
  <div class="profile">
    <a href="auth.php"><img src="imgandgifs/login.png" alt="login"></a>
    <?php if(isset($_SESSION['user_id'])): ?>
        <span style="margin-left:10px;">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="?action=logout"><img src="imgandgifs/logout.png" alt="logout" style="margin-left:5px;"></a>
    <?php endif; ?>
  </div>
</header>

<div class="container">
  <nav class="sidebar">
    <h3>Categories</h3>
    <a data-category="all" class="active">🌟 All</a>
    <a data-category="fps">🎯 FPS</a>
    <a data-category="adventure">⚔️ Adventure</a>
    <a data-category="rpg">🧙‍♂️ RPG</a>
    <a data-category="racing">🚗 Racing</a>
    <a data-category="sports">🏟️ Sports</a>
    <a data-category="arcade">👾 Arcade</a>
  </nav>

  <main class="main">
    <section class="featured">
      <h2>🔥 Featured Game: Galaxy Blaster</h2>
      <p>Fly through galaxies, battle alien fleets, and save humanity in this high-speed space shooter!</p>
    </section>

    <section class="game-grid" id="gameGrid">
    <?php
    $games_sql = "SELECT * FROM games";
    if($games_result = $conn->query($games_sql)){
        if($games_result->num_rows > 0){
            while($game = $games_result->fetch_assoc()){
                $category = htmlspecialchars($game['category'] ?? 'all');
                $title = htmlspecialchars($game['title']);
                $desc = htmlspecialchars($game['description']);
                $img = htmlspecialchars($game['main_image']);
                $game_id = $game['game_id'];

                echo "<div class='game-card' data-category='{$category}'>";
                echo "<img src='{$img}' alt='{$title}'>";
                echo "<h3>{$title}</h3>";
                echo "<p>{$desc}</p>";

                // Comments
                $comments_sql = "SELECT c.content, u.username, c.created_at 
                                 FROM comments c 
                                 JOIN users u ON c.user_id = u.user_id 
                                 WHERE c.game_id = $game_id 
                                 ORDER BY c.created_at DESC";
                if($comments_result = $conn->query($comments_sql)){
                    while($comment = $comments_result->fetch_assoc()){
                        $cuser = htmlspecialchars($comment['username']);
                        $ccontent = htmlspecialchars($comment['content']);
                        $ctime = htmlspecialchars($comment['created_at']);
                        echo "<div class='comment'><strong>{$cuser}:</strong> {$ccontent}<br><small><i>{$ctime}</i></small></div>";
                    }
                }

                // Comment form if logged in
                if(isset($_SESSION['user_id'])){
                    echo "<form method='POST'>
                            <input type='hidden' name='game_id' value='{$game_id}'>
                            <textarea name='content' rows='2' placeholder='Write a comment...' required></textarea><br>
                            <button type='submit' name='comment_submit'>Post Comment</button>
                          </form>";
                }

                echo "</div>";
            }
        } else { echo "<p>No games found.</p>"; }
    } else { echo "<p>Error fetching games.</p>"; }
    ?>
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
            card.style.display = (category==='all' || card.getAttribute('data-category')===category) ? 'block' : 'none';
        });
    });
});
</script>
</body>
</html>
