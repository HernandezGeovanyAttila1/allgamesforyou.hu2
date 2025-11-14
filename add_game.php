<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$error = '';
$success = '';

// Categories array
$categories = ['fps'=>'FPS','adventure'=>'Adventure','rpg'=>'RPG','racing'=>'Racing','sports'=>'Sports','arcade'=>'Arcade'];

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $selected_categories = $_POST['category'] ?? []; // array
    $category_str = implode(',', $selected_categories); // store as comma-separated
    $user_id = $_SESSION['user_id'];

    // Prevent duplicate titles
    $stmt = $conn->prepare("SELECT * FROM games WHERE title=?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $error = "A game with this title already exists.";
    } else {
        // Insert game without image for simplicity
        $stmt = $conn->prepare("INSERT INTO games (title, description, category, created_by, created_at) VALUES (?,?,?,?,NOW())");
        $stmt->bind_param("sssi", $title, $description, $category_str, $user_id);
        if($stmt->execute()){
            $success = "Game added successfully!";
            header("Location: index.php");
            exit();
        } else {
            $error = "Database insert failed.";
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
body { font-family:Poppins,sans-serif; background:linear-gradient(180deg,#6a1b9a,#4a0072); color:#fff; display:flex; justify-content:center; align-items:center; height:100vh; margin:0; }
.container { background:rgba(255,255,255,0.15); padding:30px; border-radius:15px; width:400px; text-align:center; }
input, textarea { width:100%; padding:10px; margin:8px 0; border-radius:8px; border:none; outline:none; }
button { padding:10px 15px; border:none; border-radius:8px; background:#ff9800; color:white; cursor:pointer; }
button:hover { background:#fb8c00; }
.error { color:#ff5252; }
.success { color:#69f0ae; }
.category-checkbox { text-align:left; margin:5px 0; }
a { color:#fff; text-decoration:underline; display:block; margin-top:10px; }
</style>
</head>
<body>
<div class="container">
<h2>Add New Game</h2>
<?php if($error) echo "<p class='error'>$error</p>"; ?>
<?php if($success) echo "<p class='success'>$success</p>"; ?>
<form method="POST">
    <input type="text" name="title" placeholder="Game Title" required>
    <textarea name="description" placeholder="Description" rows="4" required></textarea>

    <p>Select Category(s):</p>
    <?php foreach($categories as $key=>$label): ?>
        <label class="category-checkbox">
            <input type="checkbox" name="category[]" value="<?php echo $key; ?>"> <?php echo $label; ?>
        </label>
    <?php endforeach; ?>

    <button type="submit">Add Game</button>
</form>
<a href="index.php">← Back to Home</a>
</div>
</body>
</html>
