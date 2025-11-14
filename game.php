<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
session_start();



$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if(!isset($_GET['id'])) {
    die("Game ID missing!");
}

$game_id = intval($_GET['id']);

// Handle comment submission
if(isset($_POST['comment_submit']) && isset($_SESSION['user_id'])){
    $content = trim($_POST['content']);
    if(!empty($content)){
        $stmt = $conn->prepare("INSERT INTO comments (game_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $game_id, $_SESSION['user_id'], $content);
        $stmt->execute();
    }
}

// Fetch game
$stmt = $conn->prepare("SELECT * FROM games WHERE game_id=?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_result = $stmt->get_result();
if($game_result->num_rows !== 1){
    die("Game not found!");
}
$game = $game_result->fetch_assoc();

// Fetch comments
$comments_sql = "SELECT c.content, u.username, c.created_at 
                 FROM comments c 
                 JOIN users u ON c.user_id = u.user_id 
                 WHERE c.game_id = $game_id 
                 ORDER BY c.created_at DESC";
$comments_result = $conn->query($comments_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($game['title']); ?></title>
<style>
body { font-family:Poppins,sans-serif; background:linear-gradient(180deg,#6a1b9a,#4a0072); color:#fff; padding:20px; }
.container { max-width:800px; margin:auto; background:rgba(255,255,255,0.1); padding:20px; border-radius:15px; }
h1 { margin-top:0; }
img { max-width:100%; border-radius:10px; }
.comment { background:#fff; color:#000; padding:10px; border-radius:8px; margin-bottom:10px; }
textarea { width:100%; padding:10px; border-radius:8px; border:none; margin-bottom:10px; }
button { padding:10px 15px; border:none; border-radius:8px; background:#9c27b0; color:white; cursor:pointer; }
button:hover { background:#ba68c8; }
a { color:#ffd740; display:block; margin-top:15px; text-decoration:underline; }
</style>
</head>
<body>

<div class="container">
    <h1><?php echo htmlspecialchars($game['title']); ?></h1>
    <p><strong>Category:</strong> <?php echo htmlspecialchars($game['category']); ?></p>
    <img src="<?php echo htmlspecialchars($game['main_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>">
    <p><?php echo nl2br(htmlspecialchars($game['description'])); ?></p>

    <h2>Comments</h2>
    <?php while($comment = $comments_result->fetch_assoc()): ?>
        <div class="comment">
            <strong><?php echo htmlspecialchars($comment['username']); ?>:</strong>
            <?php echo htmlspecialchars($comment['content']); ?>
            <br><small><?php echo htmlspecialchars($comment['created_at']); ?></small>
        </div>
    <?php endwhile; ?>

    <?php if(isset($_SESSION['user_id'])): ?>
        <form method="POST">
            <textarea name="content" rows="3" placeholder="Write a comment..." required></textarea>
            <button type="submit" name="comment_submit">Post Comment</button>
        </form>
    <?php else: ?>
        <p><a href="auth.php">Login to comment</a></p>
    <?php endif; ?>

    <a href="index.php">← Back to Home</a>
</div>

</body>
</html>
