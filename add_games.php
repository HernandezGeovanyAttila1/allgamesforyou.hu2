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

$uploadDir = __DIR__ . '/uploads';
$publicUploadDir = 'uploads';
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedExt = ['jpg','jpeg','png','gif','webp'];

if(!is_dir($uploadDir)){
    mkdir($uploadDir, 0775, true);
}

$conn = new mysqli($servername, $db_username, $db_password, $database);
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$categories = [
    'Platform games','Adventure','Shooter games','Fighting games','Stealth games','Survival games','Rhythm games',
    'Battle Royale games','Puzzle games','Logical game','Role-playing','CRPG','MMORPG','Roguelikes',
    'Sandbox RPG','Simulation','Vehicle simulation','Strategy','Multiplayer online battle arena (MOBA)',
    'Tower defense','Wargame','Competitive','Board game','Casino game','Gacha game','Horror game',
    'Idle game','Party game','Sandbox',
];

$initial_display = 10;
$error = '';
$success = '';

function sanitize_file_name($name){
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return substr($name, 0, 200);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selected_categories = $_POST['category'] ?? [];
    $user_id = (int)$_SESSION['user_id'];

    if($title === '' || $description === ''){
        $error = "Please provide title and description.";
    } else {
        $stmt = $conn->prepare("SELECT game_id FROM games WHERE LOWER(title)=LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $title);
        $stmt->execute();
        $res = $stmt->get_result();
        if($res && $res->num_rows > 0){
            $error = "A game with this title already exists.";
        }
        $stmt->close();
    }

    if($error === ''){
        if(!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== 0){
            $error = "Please select an image (no upload errors).";
        } else {
            $file = $_FILES['main_image'];
            if($file['size'] > $maxFileSize){
                $error = "Image too large. Maximum allowed size is " . ($maxFileSize / (1024*1024)) . " MB.";
            } else {
                $originalName = $file['name'];
                $originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if(!in_array($originalExt, $allowedExt)){
                    $error = "Invalid image type. Allowed types: " . implode(', ', $allowedExt) . ".";
                } else {
                    $base = pathinfo($originalName, PATHINFO_FILENAME);
                    $safeBase = sanitize_file_name($base);
                    $newFileName = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $originalExt;
                    $destFullPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newFileName;
                    $dbPath = $publicUploadDir . '/' . $newFileName;

                    // Fast upload
                    if(!move_uploaded_file($file['tmp_name'], $destFullPath)){
                        $error = "Failed to upload image.";
                    }
                }
            }
        }
    }

    if($error === ''){
        $conn->begin_transaction();
        try {
            $insertStmt = $conn->prepare("INSERT INTO games (title, description, main_image, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            $insertStmt->bind_param("sssi", $title, $description, $dbPath, $user_id);
            $insertStmt->execute();
            $game_id = $insertStmt->insert_id;
            $insertStmt->close();

            // ✅ Batch category insert
            if(!empty($selected_categories)){
                $validCats = array_intersect($selected_categories, $categories);
                if(!empty($validCats)){
                    $values = [];
                    $types = '';
                    $params = [];

                    foreach($validCats as $cat){
                        $values[] = "(?, ?)";
                        $types .= "is";
                        $params[] = $game_id;
                        $params[] = $cat;
                    }

                    $sql = "INSERT INTO game_categories (game_id, category) VALUES " . implode(", ", $values);
                    $stmt = $conn->prepare($sql);
                    if($stmt){
                        $tmp = [];
                        foreach($params as $k => $v) $tmp[$k] = &$params[$k];
                        array_unshift($tmp, $types);
                        call_user_func_array([$stmt, 'bind_param'], $tmp);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            $conn->commit();
            header("Location: index.php");
            exit();
        } catch (Exception $e){
            $conn->rollback();
            if(isset($destFullPath) && file_exists($destFullPath)){
                @unlink($destFullPath);
            }
            $error = "Database error: " . $e->getMessage();
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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');
body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg,#4a0072,#6a1b9a); margin:0; display:flex; justify-content:center; align-items:flex-start; min-height:100vh; padding:20px; }
.container { background: rgba(255,255,255,0.06); padding: 28px; border-radius: 20px; margin: 40px 10px; width: 100%; max-width: 640px; box-sizing: border-box; box-shadow: 0 8px 30px rgba(0,0,0,0.35); }
h2 { color:#fff; margin-bottom: 18px; font-weight:600; }
input, textarea, select, button { width: 100%; box-sizing: border-box; }
input, textarea { padding: 12px 15px; margin: 10px 0; border-radius: 12px; border: none; outline: none; font-size: 1em; background: rgba(255,255,255,0.06); color: #fff; }
textarea { resize: vertical; min-height: 120px; }
button { padding: 12px 20px; margin-top: 15px; border: none; border-radius: 12px; background: #ff9800; color: #fff; font-size: 1em; cursor: pointer; }
button:hover { background: #fb8c00; }
.error { color:#ff5252; margin-bottom:10px; }
.success { color:#69f0ae; margin-bottom:10px; }
a.back-link { color:#fff; text-decoration: underline; display: inline-block; margin-top:15px; }
.categories-container { text-align: left; margin-top: 10px; }
.categories-search { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 12px; border: none; font-size: 0.95em; background: rgba(255,255,255,0.04); color: #fff; }
.categories-list { display: flex; flex-wrap: wrap; gap: 8px; max-height: 200px; overflow-y: auto; padding: 8px; border-radius: 12px; background: rgba(255,255,255,0.03); }
.categories-list input[type="checkbox"] { display: none; }
.categories-list label { padding: 0; background: none; cursor: pointer; user-select: none; }
.categories-list label span { background: rgba(255,255,255,0.08); padding: 10px 18px; border-radius: 25px; display: inline-block; transition: 0.18s ease; color: #fff; font-size: 0.9em; }
.categories-list input[type="checkbox"]:checked + span { background: #d29ef0; color: #000; font-weight: 600; box-shadow: 0 6px 18px rgba(0,0,0,0.3); transform: scale(1.03); }
.categories-list label:hover span { background: rgba(255,255,255,0.18); }
#dropArea { margin: 15px 0; padding: 18px; border: 2px dashed rgba(255,255,255,0.18); border-radius: 15px; color: #fff; cursor: pointer; text-align: center; font-size: 0.95em; display: flex; justify-content: center; align-items: center; min-height: 120px; overflow: hidden; }
#dropArea.hover { background: rgba(255,255,255,0.06); }
#dropArea img { display:none; max-width:100%; max-height:340px; border-radius:12px; object-fit:contain; }
#dropText { pointer-events:none; }
@media(max-width:600px){ .categories-list { justify-content:center; } input, textarea, button { font-size: 0.95em; } }
</style>
</head>
<body>

<div class="container">
<h2>Add New Game</h2>
<?php if($error) echo "<p class='error'>$error</p>"; ?>
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
        <span id="dropText">Click or Drag & Drop Image Here (max <?php echo ($maxFileSize/(1024*1024)); ?> MB)</span>
        <img id="imgPreview" src="#" alt="Preview">
    </div>
    <input type="file" name="main_image" id="fileInput" accept="image/*" style="display:none;" required>

    <button type="submit">Add Game</button>
</form>
<a href="index.php" class="back-link">← Back to Home</a>
</div>

<script>
const searchInput = document.getElementById('catSearch');
const labels = document.querySelectorAll('.categories-list label');
searchInput.addEventListener('input', ()=>{ 
    const val = searchInput.value.toLowerCase();
    labels.forEach(label=>label.style.display = label.innerText.toLowerCase().includes(val) ? "flex" : "none");
});

const dropArea = document.getElementById('dropArea');
const fileInput = document.getElementById('fileInput');
const imgPreview = document.getElementById('imgPreview');
const dropText = document.getElementById('dropText');
const MAX_CLIENT_SIZE_MB = <?php echo (int)($maxFileSize/(1024*1024)); ?>;

function previewImage(file){
    if(file.size > MAX_CLIENT_SIZE_MB*1024*1024){ alert("Image too large!"); fileInput.value = ""; return; }
    imgPreview.src = URL.createObjectURL(file);
    imgPreview.onload = ()=>URL.revokeObjectURL(imgPreview.src);
    imgPreview.style.display='block';
    dropText.style.display='none';
}

dropArea.addEventListener('click',()=>fileInput.click());
dropArea.addEventListener('dragover',e=>{e.preventDefault(); dropArea.classList.add('hover');});
dropArea.addEventListener('dragleave',e=>{e.preventDefault(); dropArea.classList.remove('hover');});
dropArea.addEventListener('drop',e=>{e.preventDefault(); dropArea.classList.remove('hover'); if(e.dataTransfer.files.length){fileInput.files=e.dataTransfer.files; previewImage(fileInput.files[0]);}});
fileInput.addEventListener('change',()=>{if(fileInput.files.length) previewImage(fileInput.files[0]);});
imgPreview.addEventListener('click',()=>{fileInput.value=''; imgPreview.src=''; imgPreview.style.display='none'; dropText.style.display='block';});
</script>

</body>
</html>
