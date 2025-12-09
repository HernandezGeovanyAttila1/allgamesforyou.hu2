<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

$categories = [
    'Platform games','Shooter games','Fighting games','Stealth games','Survival games','Rhythm games',
    'Battle Royale games','Puzzle games','Logical game','Role-playing','CRPG','MMORPG','Roguelikes',
    'Sandbox RPG','Simulation','Vehicle simulation','Strategy','Multiplayer online battle arena (MOBA)',
    'Tower defense','Wargame','Competitive','Board game','Casino game','Gacha game','Horror game',
    'Idle game','Party game','Sandbox'
];

$initial_display = 10;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $selected_categories = $_POST['category'] ?? [];
    $user_id = $_SESSION['user_id'];

    // Check duplicate
    $stmt = $conn->prepare("SELECT * FROM games WHERE title=?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $error = "A game with this title already exists.";
    } else {
        if(isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0){
            $allowed = ['jpg','jpeg','png','gif'];
            $file_name = $_FILES['main_image']['name'];
            $file_tmp = $_FILES['main_image']['tmp_name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if(in_array($ext, $allowed)){
                $new_file_name = 'uploads/'.uniqid().'_'.basename($file_name);
                if(!is_dir('uploads')) mkdir('uploads', 0777, true);
                if(move_uploaded_file($file_tmp, $new_file_name)){
                    $stmt = $conn->prepare("INSERT INTO games (title, description, main_image, created_by, created_at) VALUES (?,?,?,?,NOW())");
                    $stmt->bind_param("sssi", $title, $description, $new_file_name, $user_id);
                    if($stmt->execute()){
                        $game_id = $stmt->insert_id;
                        $cat_stmt = $conn->prepare("INSERT INTO game_categories (game_id, category) VALUES (?, ?)");
                        foreach($selected_categories as $cat){
                            $cat_stmt->bind_param("is", $game_id, $cat);
                            $cat_stmt->execute();
                        }
                        $success = "Game added successfully!";
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "Database insert failed.";
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid image type. Allowed: jpg, jpeg, png, gif.";
            }
        } else {
            $error = "Please select an image.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add New Game</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family:Poppins,sans-serif; background:linear-gradient(180deg,#6a1b9a,#4a0072); color:#fff; display:flex; justify-content:center; align-items:flex-start; min-height:100vh; margin:0; }
.container { background:rgba(255,255,255,0.15); padding:30px; border-radius:15px; width:100%; max-width:540px; margin:40px 10px; }
input, textarea, select, button { width:100%; box-sizing:border-box; }
input, textarea { padding:12px 15px; margin:10px 0; border-radius:12px; border:none; outline:none; }
textarea { resize:vertical; min-height:100px; }
button { padding:12px; margin-top:15px; border:none; border-radius:12px; background:#ff9800; color:#fff; cursor:pointer; }
button:hover { background:#fb8c00; }
.error { color:#ff5252; margin-bottom:10px; }
.success { color:#69f0ae; margin-bottom:10px; }
a.back-link { color:#fff; text-decoration:underline; display:inline-block; margin-top:15px; }

.categories-container { text-align:left; margin-top:10px; }
.categories-search { width:100%; padding:10px; margin-bottom:10px; border-radius:12px; border:none; font-size:0.95em; }
.categories-list { display:flex; flex-wrap:wrap; gap:8px; max-height:200px; overflow-y:auto; padding:5px; border-radius:12px; background:rgba(255,255,255,0.05); }
.categories-list label { background:rgba(255,255,255,0.15); padding:8px 12px; border-radius:20px; cursor:pointer; font-size:0.9em; display:flex; align-items:center; user-select:none; }
.categories-list input[type="checkbox"] { display:none; }
.categories-list input[type="checkbox"]:checked + span { background:#9c27b0; color:#fff; }

#dropArea { margin:15px 0; padding:20px; border:2px dashed #fff; border-radius:15px; color:#fff; cursor:pointer; text-align:center; font-size:0.95em; position:relative; display:flex; justify-content:center; align-items:center; min-height:120px; }
#dropArea.hover { background:rgba(255,255,255,0.2); }
#dropArea img { display:none; max-width:100%; max-height:300px; border-radius:12px; object-fit:contain; }
#dropText { pointer-events:none; }
</style>
</head>
<body>

<div class="container">
<h2>Add New Game</h2>
<?php if($error) echo "<p class='error'>$error</p>"; ?>
<?php if($success) echo "<p class='success'>$success</p>"; ?>

<form method="POST" enctype="multipart/form-data">
    <input type="text" name="title" placeholder="Game Title" required>
    <textarea name="description" placeholder="Description" required></textarea>

    <div class="categories-container">
        <input type="text" id="catSearch" class="categories-search" placeholder="Search categories...">
        <div class="categories-list" id="catList">
            <?php foreach($categories as $i => $cat): ?>
                <label style="display: <?php echo ($i<$initial_display)?'flex':'none'; ?>">
                    <input type="checkbox" name="category[]" value="<?php echo htmlspecialchars($cat); ?>">
                    <span><?php echo htmlspecialchars($cat); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="dropArea">
        <span id="dropText">Click or Drag & Drop Image Here</span>
        <img id="imgPreview" src="#" alt="Preview">
    </div>
    <input type="file" name="main_image" id="fileInput" accept="image/*" style="display:none;" required>

    <button type="submit">Add Game</button>
</form>

<a href="index.php" class="back-link">← Back to Home</a>
</div>

<script>
// Category search
const searchInput = document.getElementById('catSearch');
const labels = document.querySelectorAll('.categories-list label');
searchInput.addEventListener('input', ()=>{
    const val = searchInput.value.toLowerCase();
    labels.forEach(label => {
        const text = label.innerText.toLowerCase();
        label.style.display = text.includes(val) ? 'flex' : 'none';
    });
});

// Drag & drop image
const dropArea = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');
const imgPreview = document.getElementById('imgPreview');
const dropText = document.getElementById('dropText');

function previewImage(file){
    const reader = new FileReader();
    reader.onload = function(e){
        imgPreview.src = e.target.result;
        imgPreview.style.display = 'block';
        dropText.style.display = 'none';
    }
    reader.readAsDataURL(file);
}

dropArea.addEventListener('click', () => fileInput.click());

dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('hover'); });
dropArea.addEventListener('dragleave', e => { e.preventDefault(); dropArea.classList.remove('hover'); });
dropArea.addEventListener('drop', e => {
    e.preventDefault();
    dropArea.classList.remove('hover');
    if(e.dataTransfer.files.length > 0){
        fileInput.files = e.dataTransfer.files;
        previewImage(fileInput.files[0]);
    }
});

fileInput.addEventListener('change', ()=>{
    if(fileInput.files.length > 0){
        previewImage(fileInput.files[0]);
    }
});

// Click on image removes it
imgPreview.addEventListener('click', ()=>{
    fileInput.value = '';
    imgPreview.src = '';
    imgPreview.style.display = 'none';
    dropText.style.display = 'block';
});
</script>
</body>
</html>
