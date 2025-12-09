<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ---- DATABASE CONNECTION ----
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

// ---- AUTO LOGIN USING REMEMBER-ME ----
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    $token_raw = $_COOKIE['rememberme'];
    $stmt = $conn->prepare("SELECT user_id, username, role, profile_img, remember_token FROM users WHERE remember_token IS NOT NULL");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['remember_token']) && password_verify($token_raw, $row['remember_token'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? 'imgandgifs/login.png';

            // Regenerate token for security
            $new_token = bin2hex(random_bytes(32));
            $new_hash = password_hash($new_token, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE users SET remember_token=? WHERE user_id=?");
            $stmt2->bind_param("si", $new_hash, $row['user_id']);
            $stmt2->execute();
            setcookie("rememberme", $new_token, time() + 30*24*60*60, "/", "", true, true);

            break;
        }
    }
}

// ---- REDIRECT IF NOT LOGGED IN ----
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// ---- GET USER DATA ----
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

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
$comments_query = $conn->prepare("SELECT c.content, c.created_at, g.title FROM comments c 
JOIN games g ON c.game_id=g.game_id 
WHERE c.user_id=? ORDER BY c.created_at DESC");
$comments_query->bind_param("i", $user_id);
$comments_query->execute();
$comments = $comments_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($username); ?>'s Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ---------- BASE ---------- */
body {
  font-family: "Poppins", sans-serif;
  background: radial-gradient(circle at top, #7b1fa2, #2a003f);
  color: white;
  margin: 0;
  padding: 0;
  overflow-x: hidden;
}

/* ---------- HEADER ---------- */
header {
  position: sticky;
  top: 0;
  z-index: 1000;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(30, 0, 45, 0.7);
  backdrop-filter: blur(10px);
  padding: 15px 25px;
  transition: top 0.4s;
}
header a {
  color: white;
  text-decoration: none;
  margin-left: 20px;
  font-weight: 500;
  font-size: 18px;
}
header img {
  width: 150px;
}

/* ---------- PROFILE CARD ---------- */
.profile-container {
  max-width: 700px;
  margin: 30px auto;
  background: rgba(255, 255, 255, 0.1);
  padding: 25px;
  border-radius: 18px;
  box-shadow: 0 0 25px rgba(0,0,0,0.4);
  text-align: center;
  backdrop-filter: blur(8px);
}

#profilePreview {
  width: 160px;
  height: 160px;
  object-fit: cover;
  border-radius: 50%;
  border: 4px solid #c47cff;
  transition: 0.3s;
}
#profilePreview:hover {
  transform: scale(1.05);
  box-shadow: 0 0 15px #d694ff;
}

/* ---------- COMMENTS ---------- */
.comment {
  background: rgba(255,255,255,0.18);
  padding: 12px;
  margin: 15px 0;
  border-radius: 12px;
  text-align: left;
  border-left: 4px solid #c47cff;
}
.comment strong {
  font-size: 18px;
  color: #ffe2ff;
}
.comment small {
  opacity: 0.8;
}

/* ---------- MOBILE FIXES ---------- */
@media(max-width: 600px) {
  header img { width: 130px; }
  .profile-container { margin: 15px; padding: 20px; }
}
</style>
</head>
<body>

<!-- HEADER -->
<header id="navbar">
  <img src="imgandgifs/C4T.png" alt="logo">
  <div>
    <a href="index.php">🏠 Home</a>
    <a href="auth.php?action=logout">🚪 Logout</a>
  </div>
</header>

<!-- PROFILE CARD -->
<div class="profile-container">
  <h2><?php echo htmlspecialchars($username); ?>'s Profile</h2>
  

  <!-- Auto-update profile picture -->
  <form id="profileForm" method="POST" enctype="multipart/form-data">
    <input 
      id="profilePicInput"
      type="file" 
      name="profile_pic" 
      accept="image/*"
      style="display:none;"
    >
    <img id="profilePreview" src="<?php echo htmlspecialchars($profile_img); ?>">
  </form>

  <hr style="margin: 25px 0; border-color: rgba(255,255,255,0.3);">

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
    <p>You haven't commented yet.</p>
  <?php endif; ?>
</div>

<script>
// CLICK PROFILE IMAGE = open file picker
document.getElementById("profilePreview").addEventListener("click", () => {
  document.getElementById("profilePicInput").click();
});

// AUTO PREVIEW + AUTO UPLOAD
document.getElementById("profilePicInput").addEventListener("change", function(){
  if(this.files && this.files[0]) {
    document.getElementById("profilePreview").src = URL.createObjectURL(this.files[0]);
    document.getElementById("profileForm").submit();
  }
});

// HEADER AUTO-HIDE ON SCROLL (gaming launcher style)
let lastScroll = 0;
const nav = document.getElementById("navbar");

window.addEventListener("scroll", () => {
  const current = window.pageYOffset;
  nav.style.top = current > lastScroll ? "-90px" : "0";
  lastScroll = current;
});
</script>

</body>
</html>
