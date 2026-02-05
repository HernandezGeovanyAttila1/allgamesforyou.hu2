<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!empty($_SESSION['is_banned'])) {
    die("You are banned and cannot comment.");
}


// AUTO-LOGIN USING REMEMBER-ME
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";
$conn_check = new mysqli($servername, $db_username, $db_password, $database);
if (!$conn_check->connect_error && !isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    if (strpos($_COOKIE['rememberme'], ':') !== false) {
        list($selector, $token) = explode(':', $_COOKIE['rememberme']);
        $stmt = $conn_check->prepare("SELECT user_id, username, role, profile_img, token_validator FROM users WHERE token_selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            if (!empty($row['token_validator']) && password_verify($token, $row['token_validator'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_img'] = $row['profile_img'] ?? "imgandgifs/login.png";
                // rotate
                $new_selector = bin2hex(random_bytes(9));
                $new_token = bin2hex(random_bytes(33));
                $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                $stmt2 = $conn_check->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                $stmt2->execute();
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
                setcookie("rememberme", $new_selector . ":" . $new_token, time() + 86400 * 30, "/", "", $secure, true);
            }
        }
    }
}

/* ---------- original game.php follows (unchanged logic) ---------- */

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (!isset($_GET['id'])) die("Game ID missing!");
$game_id = intval($_GET['id']);

/* ---------- HANDLE COMMENT SUBMISSION ---------- */
if (isset($_POST['comment_submit']) && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);

    if (!empty($content)) {
        $stmt = $conn->prepare("
            INSERT INTO comments (game_id, user_id, content, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $game_id, $_SESSION['user_id'], $content);
        $stmt->execute();
    }

    header("Location: game.php?id=$game_id");
    exit();
}

/* ---------- FETCH GAME ---------- */
$stmt = $conn->prepare("SELECT * FROM games WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_result = $stmt->get_result();

if ($game_result->num_rows !== 1) die("Game not found!");
$game = $game_result->fetch_assoc();

/* ---------- FETCH CATEGORIES ---------- */
$cat_stmt = $conn->prepare("SELECT category FROM game_categories WHERE game_id=?");
$cat_stmt->bind_param("i", $game_id);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();

$game_categories = [];
while ($row = $cat_result->fetch_assoc()) $game_categories[] = $row['category'];

/* ---------- FETCH COMMENTS ---------- */
$comment_stmt = $conn->prepare("
    SELECT c.content, u.username, u.profile_img, c.created_at
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.game_id = ?
    ORDER BY c.created_at DESC
");
$comment_stmt->bind_param("i", $game_id);
$comment_stmt->execute();
$comments = $comment_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" sizes="128x128" href="imgandgifs/logo.png">
<title><?= htmlspecialchars($game['title']); ?></title>
<style>
/* your CSS unchanged */

.back-btn{
    position:fixed;
    top:20px;
    right:20px;
    padding:19px 30px;
    transition:.2s;
}
.back-btn:hover{
    transform:scale(1.08);
}

body { font-family: Poppins, sans-serif; background-image:url("imgandgifs/new_bg_darker.png");background-repeat:no-repeat; background-size: cover; background-attachment: fixed; margin: 0; padding: 20px; color: #fff; backdrop-filter: blur(2px); }
.container { max-width: 900px; margin: auto; background: rgba(59, 46, 74,0.9); padding: 25px; border-radius: 15px; word-wrap: break-word; overflow-wrap: break-word; }
h1 { margin-top: 0; max-width: 100%; font-size: 2em; }
.game-description { white-space: pre-wrap; line-height: 1.5; margin-bottom: 20px; }
img.game-image { width: 800px; height: 400px; border-radius: 12px; margin-top: 15px;object-fit:cover;margin-left:48px; }
.category-list span { background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 5px; margin-right: 5px; font-size: 0.9em; }
.comments-container { margin-top: 25px; }
.comment { background: rgba(255,255,255,0.15); padding: 15px; border-radius: 12px; margin-bottom: 12px; display: flex; gap: 15px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); transition: 0.25s; }
.comment img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #ffd740; }
.username { font-weight: bold; color: #ffd740; }
textarea { width: 100%; padding: 12px; border-radius: 12px; border: 2px solid rgba(255,255,255,0.25); background-color: rgba(255,255,255,0.08); resize: none; color: #fff; }
button.comment-btn { background: #fd9b27; color: #fff1d4; border: none; padding: 10px 18px; border-radius: 10px; cursor: pointer; font-weight: bold; }
a { color: #ffd740; text-decoration: underline; }
</style>
</head>
<body> 


<form action="index.php">
  <a href="index.php">
    <img src="imgandgifs/back_button.png" class ="back-btn" width="80">
  </a>
    </form>
<div class="container">
    <h1><?= htmlspecialchars($game['title']); ?></h1>
    <div class="category-list">
        <?php if ($game_categories) {
            foreach ($game_categories as $cat) echo "<span>" . htmlspecialchars($cat) . "</span>";
        } else {
            echo "<span>Uncategorized</span>";
        } ?>
         
    </div>
    <img class="game-image" src="<?= htmlspecialchars($game['main_image'] ?: 'default_game.png') ?>" alt="<?= htmlspecialchars($game['title']) ?>">
    <p class="game-description"><?= nl2br(htmlspecialchars($game['description'])); ?></p>
    <h2>Comments</h2>
    <div class="comments-container">
    <?php while ($c = $comments->fetch_assoc()): ?>
        <div class="comment">
            <img src="<?= htmlspecialchars($c['profile_img'] ?: 'imgandgifs/login.png'); ?>" alt="profile">
            <div class="content">
                <div class="username"><?= htmlspecialchars($c['username']) ?></div>
                <p><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                <small><?= htmlspecialchars($c['created_at']) ?></small>
            </div>
        </div>
    <?php endwhile; ?>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST">
            <textarea name="content" rows="2" placeholder="Write a comment..." required></textarea>
            <button type="submit" name="comment_submit" class="comment-btn">Post Comment</button>
        </form>
    <?php else: ?>
        <p><a href="auth.php">Login to comment</a></p>
    <?php endif; ?>
    <br><br>
   

</div>
</body>
</html>
