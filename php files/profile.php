<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* ===== FAVOURITE HANDLER ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {

    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data["toggle"])) {
        $gameId = (string)$data["toggle"];

        if (!isset($_SESSION["favourites"])) {
            $_SESSION["favourites"] = [];
        }

        if (in_array($gameId, $_SESSION["favourites"])) {
            // remove
            $_SESSION["favourites"] = array_filter(
            $_SESSION["favourites"],
            function($id) use ($gameId) {
                return $id !== $gameId;
            }
        );

            echo json_encode(["status" => "removed"]);
        } else {
            // add
            $_SESSION["favourites"][] = $gameId;
            echo json_encode(["status" => "added"]);
        }

        exit;
    }
}





/* ===== ADDED: ADMIN + BAN DEFAULTS ===== */
if (!isset($_SESSION['role'])) $_SESSION['role'] = 'user';
if (!isset($_SESSION['is_banned'])) $_SESSION['is_banned'] = 0;

/* ---- DATABASE CONNECTION ---- */
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Database connection failed: ".$conn->connect_error);

/* ---- AUTO LOGIN USING REMEMBER-ME ---- */
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    $token_raw = $_COOKIE['rememberme'];
    $stmt = $conn->prepare("SELECT user_id, username, role, is_banned, profile_img, remember_token FROM users WHERE remember_token IS NOT NULL");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['remember_token']) && password_verify($token_raw, $row['remember_token'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['is_banned'] = $row['is_banned'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';
            break;
        }
    }
}

/* ---- REDIRECT IF NOT LOGGED IN ---- */
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

/* ---- GET USER DATA ---- */
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* ===== ADDED ===== */
$is_admin = ($_SESSION['role'] === 'admin');

/* ---- Handle profile picture upload ---- */
/* ===== ADDED: BAN CHECK (WRAPS ORIGINAL LOGIC) ===== */
if ($_SESSION['is_banned'] == 0) {
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
}
/* ===== END ADDED ===== */

/* ---- Get updated user info ---- */
$user_query = $conn->prepare("SELECT username, role, is_banned, profile_img FROM users WHERE user_id=?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();
$profile_img = $user_data['profile_img'] ?? "/imgandgifs/login.png";

/* ===== ADDED ===== */
$_SESSION['role'] = $user_data['role'];
$_SESSION['is_banned'] = $user_data['is_banned'];
/* ===== END ADDED ===== */

/* ---- Get user's comments ---- */
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
<link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- (YOUR ORIGINAL CSS – UNCHANGED) -->
<style>
/* ---------- BASE ---------- */
body {
  font-family: "Poppins", sans-serif;
  background: radial-gradient(circle at top, #7b1fa2, #2a003f);
  color: white;
  margin: 0;
  padding: 0;
  overflow-x: hidden;
  margin-bottom:100px;
  
}

/* ---------- HEADER ---------- */
header {
  position: sticky;
  top: 0;
  z-index: 1000;
  display: flex;
  justify-content: space-between;
  align-items: center;
  /*background-image:url("/imgandgifs/new_bg.png");*/
  /*backdrop-filter: blur(10px);*/
  /*background-repeat:no-repeat;*/
  /*background-attachment: fixed;*/
  /*background-size:cover;*/
  padding: 15px 25px;
  max-height:50px;
}
header a { color: white; text-decoration: none; margin-left: 20px; }

#logo{
    width:120px;
    height:80px;
    padding-top:30px;
}

/* ---------- PROFILE CARD ---------- */
.profile-container {
  max-width: 700px;
  margin: 30px auto;
  background: rgba(255, 255, 255, 0.1);
  padding: 25px;
  border-radius: 18px;
  text-align: center;

}
#profilePreview {
  width: 160px;
  height: 160px;
  border-radius: 50%;
  object-fit: cover;
}
.comment {
  background: rgba(255,255,255,0.18);
  padding: 12px;
  margin: 15px 0;
  border-radius: 12px;
}

.ikonok {
  display: flex;         
  gap: 30px;             
}
.iknok :hover{
    transform:scale(1.08);
}

.menu-item {
  display: flex;
  flex-direction: column; 
  align-items: center;
  text-decoration: none;
  color: white;
  font-size: 14px;
}

.menu-item img {
  margin-bottom: 6px;    
}


.favourites-list {
    text-align: center; 
    
}


.fav-grid {
    display: grid; grid-template-columns: 
    repeat(auto-fill, minmax(180px, 1fr)); 
    gap: 20px; margin: 20px auto; 
    max-width: 800px; 
    display:flex;
    justify-content:center;
}

.fav-card {
    background: rgba(255,255,255,0.15);
    padding: 15px;
    border-radius: 12px;
    text-align: center;
    backdrop-filter: blur(5px);
    transition: 0.2s;
    text-align: center;

}

.fav-btn-open:hover {
    transform: scale(1.05);
}

.fav-title {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 10px;
}

.fav-btn-open {
    background: #ffcc00;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}

.fav-btn-open:hover {
    background: #ffdd33;
}





</style>
</head>

<body>

<header class="navbar">
  <img src="/imgandgifs/catlogo.png" id="logo" width="150">
 <div class="ikonok">

  <a href="index.php" class="menu-item">
    <img src="/imgandgifs/home_logo.png" width="40">
    <span>Home</span>
  </a>

  <?php if($is_admin): ?>
  <a href="admin.php" class="menu-item">
    <img src="/imgandgifs/admin_logo.png" width="40">
    <span>Admin</span>
  </a>
  <?php endif; ?>

  <a href="auth.php?action=logout" class="menu-item">
    <img src="/imgandgifs/logout_logo.png" width="40">
    <span>Logout</span>
  </a>

</div>


</header>

<div class="profile-container">
  <h2><?php echo htmlspecialchars($username); ?>'s Profile</h2>

  <!-- ===== ADDED ===== -->
  <?php if($_SESSION['is_banned']): ?>
    <p style="color:red;">🚫 You are banned</p>
  <?php endif; ?>
  <!-- ===== END ADDED ===== -->

  <form id="profileForm" method="POST" enctype="multipart/form-data">
    <input id="profilePicInput" type="file" name="profile_pic" accept="image/*" hidden>
    <img id="profilePreview" src="<?php echo htmlspecialchars($profile_img); ?>">
  </form>

  <hr>

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


<div class="favourites-list"> <h3>Your Favourite Games</h3>


<?php




$favs = $_SESSION["favourites"] ?? []; if (empty($favs)) { echo "<p>You have no favourite games yet.</p>"; } else { echo '<div class="fav-grid">'; foreach ($favs as $id) { $q = $conn->prepare("SELECT title FROM games WHERE game_id=?"); $q->bind_param("i", $id); $q->execute(); $res = $q->get_result()->fetch_assoc(); if ($res) { echo ' <div class="fav-card"> <div class="fav-title">'.htmlspecialchars($res["title"]).'</div> <button class="fav-btn-open" onclick="window.location.href=\'game.php?id='.$id.'\'"> Open Game </button> </div>'; } } echo '</div>'; } ?>


<script>
document.getElementById("profilePreview").onclick = () =>
  document.getElementById("profilePicInput").click();

document.getElementById("profilePicInput").onchange = () =>
  document.getElementById("profileForm").submit();
</script>

</body>
</html>
