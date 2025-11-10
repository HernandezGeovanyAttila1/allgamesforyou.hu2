<?php
session_start();

// Redirect to login if not logged in
if(!isset($_SESSION['user_id'])){
    header("Location: auth.php");
    exit();
}

// Database connection
$servername = "localhost";
$db_username = "skdneoaa_yourusername"; // replace with your cPanel DB username
$db_password = "your_password";         // replace with your DB password
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if($conn->connect_error){
    die("Database connection failed: ".$conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch user comments
$comments_sql = "SELECT c.content, c.created_at, g.title AS game_title
                 FROM comments c
                 JOIN games g ON c.game_id = g.game_id
                 WHERE c.user_id = ?
                 ORDER BY c.created_at DESC";
$stmt = $conn->prepare($comments_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$comments_result = $stmt->get_result();

// Fetch user-uploaded games (optional)
$games_sql = "SELECT title, main_image, description FROM games WHERE user_id=?";
$stmt2 = $conn->prepare($games_sql);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$user_games = $stmt2->get_result();

// Fetch user-uploaded images (optional)
$images_sql = "SELECT img_path, description FROM images WHERE user_id=?";
$stmt3 = $conn->prepare($images_sql);
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$user_images = $stmt3->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($username); ?>'s Profile</title>
<style>
body { font-family: Poppins, sans-serif; margin:0; background: linear-gradient(180deg,#6a1b9a,#4a0072); color:#fff; }
header { display:flex; justify-content:space-between; align-items:center; padding:15px 30px; background:#4a0072; }
header a { color:white; text-decoration:none; margin-left:15px; }
.profile-container { max-width:900px; margin:30px auto; background:rgba(0,0,0,0.3); padding:20px; border-radius:15px; }
h2,h3 { margin-top:0; }
.comment, .user-game, .user-image { background:#f7f7f7; color:black; padding:10px; border-radius:8px; margin-bottom:10px; }
.user-game img, .user-image img { width:100%; max-width:250px; border-radius:10px; }
section { margin-bottom:30px; }
</style>
</head>
<body>

<header>
    <img src="imgandgifs/C4T.png" alt="logo" style="width:200px;">
    <div>
        <a href="index.php">Home</a>
        <a href="?action=logout">Logout</a>
    </div>
</header>

<div class="profile-container">
    <h2><?php echo htmlspecialchars($username); ?>'s Profile</h2>
    <p><strong>Role:</strong> <?php echo htmlspecialchars($role); ?></p>

    <!-- USER COMMENTS -->
    <section>
        <h3>Your Comments</h3>
        <?php
        if($comments_result->num_rows > 0){
            while($comment = $comments_result->fetch_assoc()){
                $ccontent = htmlspecialchars($comment['content']);
                $ctime = htmlspecialchars($comment['created_at']);
                $cgame = htmlspecialchars($comment['game_title']);
                echo "<div class='comment'><strong>Game: $cgame</strong><br>$ccontent<br><small>$ctime</small></div>";
            }
        } else {
            echo "<p>No comments yet.</p>";
        }
        ?>
    </section>

    <!-- USER GAMES -->
    <section>
        <h3>Your Uploaded Games</h3>
        <?php
        if($user_games->num_rows > 0){
            while($game = $user_games->fetch_assoc()){
                $title = htmlspecialchars($game['title']);
                $desc = htmlspecialchars($game['description']);
                $img = htmlspecialchars($game['main_image']);
                echo "<div class='user-game'><h4>$title</h4><img src='$img' alt='$title'><p>$desc</p></div>";
            }
        } else {
            echo "<p>No games uploaded.</p>";
        }
        ?>
    </section>

    <!-- USER IMAGES -->
    <section>
        <h3>Your Uploaded Images</h3>
        <?php
        if($user_images->num_rows > 0){
            while($img = $user_images->fetch_assoc()){
                $path = htmlspecialchars($img['img_path']);
                $desc = htmlspecialchars($img['description']);
                echo "<div class='user-image'><img src='$path' alt='User Image'><p>$desc</p></div>";
            }
        } else {
            echo "<p>No images uploaded.</p>";
        }
        ?>
    </section>

</div>

</body>
</html>
