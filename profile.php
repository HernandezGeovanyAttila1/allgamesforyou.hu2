<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
session_start();


// Redirect if not logged in
if(!isset($_SESSION['user_id'])){
    header("Location: auth.php");
    exit();
}

// Database connection
$servername = "localhost";
$db_username = "skdneoaa"; // change this
$db_password = "t3YnVb0HN**40f";         // change this
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if($conn->connect_error){
    die("Database connection failed: ".$conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle profile picture upload
if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK){
    $upload_dir = "uploads/";
    $file_tmp = $_FILES['profile_pic']['tmp_name'];
    $file_name = basename($_FILES['profile_pic']['name']);
    $target_path = $upload_dir . uniqid() . "_" . $file_name;

    $allowed = ['jpg','jpeg','png','gif'];
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if(in_array($ext, $allowed)){
        if(move_uploaded_file($file_tmp, $target_path)){
            $update = $conn->prepare("UPDATE users SET profile_img=? WHERE user_id=?");
            $update->bind_param("si", $target_path, $user_id);
            $update->execute();
            $_SESSION['profile_img'] = $target_path;
        }
    }
}

// Get updated user info
$user_query = $conn->prepare("SELECT username, role, profile_img FROM users WHERE user_id=?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();
$profile_img = $user_data['profile_img'] ?? "imgandgifs/login.png";

// Get user's comments
$comments_query = $conn->prepare("SELECT c.content, c.created_at, g.title FROM comments c JOIN games g ON c.game_id=g.game_id WHERE c.user_id=? ORDER BY c.created_at DESC");
$comments_query->bind_param("i", $user_id);
$comments_query->execute();
$comments = $comments_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($username); ?>'s Profile</title>
<style>
body {
  font-family: "Poppins", sans-serif;
  background: linear-gradient(180deg, #6a1b9a, #4a0072);
  color: white;
  margin: 0;
  padding: 0;
}
header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #4a0072;
  padding: 15px 30px;
}
header a { color: white; text-decoration: none; margin-left: 15px; }
.profile-container {
  max-width: 800px;
  margin: 40px auto;
  background: rgba(255, 255, 255, 0.1);
  padding: 25px;
  border-radius: 15px;
  box-shadow: 0 0 10px rgba(0,0,0,0.3);
  text-align: center;
}
.profile-container img {
  width: 150px;
  height: 150px;
  object-fit: cover;
  border-radius: 50%;
  border: 3px solid white;
}
input[type="file"] {
  margin-top: 10px;
  color: white;
}
button {
  margin-top: 10px;
  padding: 8px 16px;
  border: none;
  background: #9c27b0;
  color: white;
  border-radius: 8px;
  cursor: pointer;
  transition: 0.3s;
}
button:hover { background: #ba68c8; }
.comment {
  background: rgba(255,255,255,0.2);
  padding: 10px;
  margin: 10px 0;
  border-radius: 10px;
  text-align: left;
}
</style>
</head>
<body>

<header>
  <img src="imgandgifs/C4T.png" alt="logo" style="width:200px;">
  <div>
    <a href="index.php">🏠 Home</a>
    <a href="auth.php?action=logout">🚪 Logout</a>
  </div>
</header>

<div class="profile-container">
  <h2><?php echo htmlspecialchars($username); ?>'s Profile</h2>
  <p><strong>Role:</strong> <?php echo htmlspecialchars($role); ?></p>

  <!-- Profile picture -->
  <form method="POST" enctype="multipart/form-data">
    <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile Picture"><br>
    <input type="file" name="profile_pic" accept="image/*">
    <button type="submit">Update Profile Picture</button>
  </form>

  <hr style="margin: 30px 0; border-color: rgba(255,255,255,0.3);">

  <h3>Your Comments</h3>
  <?php if($comments->num_rows > 0): ?>
    <?php while($row = $comments->fetch_assoc()): ?>
      <div class="comment">
        <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
        <?php echo htmlspecialchars($row['content']); ?><br>
        <small><?php echo htmlspecialchars($row['created_at']); ?></small>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No comments yet.</p>
  <?php endif; ?>
</div>

</body>
</html>
