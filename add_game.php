<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
session_start();

if(!isset($_SESSION['user_id'])){
  header("Location: auth.php");
  exit();
}

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$message = '';

if($_SERVER["REQUEST_METHOD"] == "POST"){
  $name = trim($_POST['name']);
  $desc = trim($_POST['description']);
  $img = trim($_POST['image_url']);

  // prevent duplicates
  $stmt = $conn->prepare("SELECT id FROM games WHERE name = ?");
  $stmt->bind_param("s", $name);
  $stmt->execute();
  $res = $stmt->get_result();

  if($res->num_rows > 0){
    $message = "⚠️ A game with this name already exists!";
  } else {
    $stmt = $conn->prepare("INSERT INTO games (name, description, image_url) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $desc, $img);
    if($stmt->execute()){
      $message = "✅ Game added successfully!";
    } else {
      $message = "❌ Failed to add game.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add New Game</title>
<style>
body {
  font-family: "Poppins", sans-serif;
  background: linear-gradient(180deg, #6a1b9a, #4a0072);
  color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
form {
  background: rgba(255,255,255,0.1);
  padding: 25px;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.3);
  width: 350px;
}
input, textarea {
  width: 100%;
  padding: 10px;
  margin: 8px 0;
  border: none;
  border-radius: 8px;
}
button {
  width: 100%;
  padding: 10px;
  background: #8e24aa;
  border: none;
  border-radius: 8px;
  color: white;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s;
}
button:hover { background: #ba68c8; }
.message { text-align: center; margin-bottom: 10px; }
a.back { color: white; text-decoration: none; display: block; text-align: center; margin-top: 10px; }
</style>
</head>
<body>
<form method="POST">
  <h2>Add a New Game</h2>
  <?php if($message) echo "<p class='message'>$message</p>"; ?>
  <input type="text" name="name" placeholder="Game name" required>
  <textarea name="description" placeholder="Game description" required></textarea>
  <input type="text" name="image_url" placeholder="Image URL (https://...)" required>
  <button type="submit">Add Game</button>
  <a href="index.php" class="back">← Back to Homepage</a>
</form>
</body>
</html>
