<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database    = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($game_id <= 0) {
    header("Location: index.php");
    exit();
}

// 2. Fetch Game Details & Check Ownership (Admin can also edit)
$stmt = $conn->prepare("SELECT * FROM games WHERE game_id = ? LIMIT 1");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

if (!$game) {
    die("Game not found.");
}

// Ownership Check (Creator or Admin)
if ((int)$game['created_by'] !== $user_id && ($_SESSION['role'] ?? 'user') !== 'admin') {
    die("You do not have permission to edit this game.");
}

// 3. Fetch Selected Categories
$selected_cats = [];
$cat_stmt = $conn->prepare("SELECT category FROM game_categories WHERE game_id = ?");
$cat_stmt->bind_param("i", $game_id);
$cat_stmt->execute();
$cat_res = $cat_stmt->get_result();
while ($row = $cat_res->fetch_assoc()) {
    $selected_cats[] = $row['category'];
}

$categories = [
    'Platform games','Adventure','Shooter games','Fighting games','Stealth games',
    'Survival games','Rhythm games','Battle Royale games','Puzzle games','Logical game',
    'Role-playing','CRPG','MMORPG','Roguelikes','Sandbox RPG','Simulation',
    'Vehicle simulation','Strategy','Multiplayer online battle arena (MOBA)',
    'Tower defense','Wargame','Competitive','Board game','Casino game',
    'Gacha game','Horror game','Idle game','Party game','Sandbox',
];

$uploadDir      = __DIR__ . '/uploads';
$publicUploadDir = 'uploads';
$maxFileSize    = 5 * 1024 * 1024;
$allowedExt     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$error   = '';
$success = '';

function sanitize_file_name($name) {
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return substr($name, 0, 200);
}

// 4. Handle POST Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title               = trim($_POST['title'] ?? '');
    $description         = trim($_POST['description'] ?? '');
    $buy_link            = trim($_POST['buy_link'] ?? '');
    $post_categories     = $_POST['category'] ?? [];

    if ($title === '' || $description === '') {
        $error = "Please provide title and description.";
    } elseif ($buy_link === '') {
        $error = "Please provide a store/buy link for the game.";
    } elseif (!filter_var($buy_link, FILTER_VALIDATE_URL)) {
        $error = "The store link must be a valid URL.";
    }

    $dbPath = $game['main_image']; // Keep old image by default

    // Handle New Image Upload
    if ($error === '' && isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
        $file        = $_FILES['main_image'];
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['size'] > $maxFileSize) {
            $error = "Image too large. Max " . ($maxFileSize / 1048576) . " MB.";
        } elseif (!in_array($originalExt, $allowedExt)) {
            $error = "Invalid image type.";
        } else {
            $safeBase     = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
            $newFileName  = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $originalExt;
            $destFullPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
            if (move_uploaded_file($file['tmp_name'], $destFullPath)) {
                $dbPath = $publicUploadDir . '/' . $newFileName;
                // Optional: Delete old image file
                // if (!empty($game['main_image']) && file_exists(__DIR__ . '/' . $game['main_image'])) { @unlink(__DIR__ . '/' . $game['main_image']); }
            } else {
                $error = "Failed to upload image.";
            }
        }
    }

    if ($error === '') {
        $conn->begin_transaction();
        try {
            // Update Game Table
            $upd = $conn->prepare("UPDATE games SET title=?, description=?, main_image=?, buy_link=? WHERE game_id=?");
            $upd->bind_param("ssssi", $title, $description, $dbPath, $buy_link, $game_id);
            $upd->execute();
            $upd->close();

            // Sync Categories
            $del_cats = $conn->prepare("DELETE FROM game_categories WHERE game_id = ?");
            $del_cats->bind_param("i", $game_id);
            $del_cats->execute();
            $del_cats->close();

            if (!empty($post_categories)) {
                $validCats = array_intersect($post_categories, $categories);
                if (!empty($validCats)) {
                    $placeholders = implode(',', array_fill(0, count($validCats), '(?,?)'));
                    $sql = "INSERT INTO game_categories (game_id,category) VALUES $placeholders";
                    $stmt = $conn->prepare($sql);
                    $types  = str_repeat('is', count($validCats));
                    $params = [];
                    foreach ($validCats as $cat) { $params[] = $game_id; $params[] = $cat; }
                    $tmp = []; foreach ($params as $k => $v) $tmp[$k] = &$params[$k];
                    array_unshift($tmp, $types);
                    call_user_func_array([$stmt, 'bind_param'], $tmp);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
            header("Location: game.php?id=" . $game_id . "&updated=1");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Game: <?= htmlspecialchars($game['title']) ?></title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --accent: #bf32f1; }
        body.dark {
            --bg-mesh-1: #0b0712; --bg-mesh-2: #1e0b3c; --bg-mesh-3: #050308;
            --text-main: #e6e0eb; --border-color: rgba(191,50,241,0.2);
            --glass: rgba(15,10,21,0.75); --shadow: 0 10px 40px rgba(0,0,0,0.6);
            --glow: 0 0 30px rgba(191,50,241,0.4); --input-bg: rgba(255,255,255,0.05);
            --cat-bg: rgba(191,50,241,0.1); --cat-active-bg: #bf32f1;
        }
        body.bright {
            --bg-mesh-1: #f7f3e8; --bg-mesh-2: #fdf2ff; --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433; --border-color: rgba(155,89,182,0.25);
            --glass: rgba(247,243,232,0.85); --shadow: 0 10px 30px rgba(155,89,182,0.15);
            --glow: 0 0 20px rgba(155,89,182,0.2); --input-bg: rgba(0,0,0,0.04);
            --cat-bg: rgba(155,89,182,0.08); --cat-active-bg: #9b59b6;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            margin: 0; min-height: 100vh;
            padding: 20px; color: var(--text-main);
            display: flex; justify-content: center; align-items: flex-start;
        }
        .container {
            background: var(--glass); backdrop-filter: blur(25px);
            padding: 40px; border-radius: 32px;
            margin: 60px 10px; width: 100%; max-width: 700px;
            box-shadow: var(--shadow); border: 1px solid var(--border-color);
        }
        h2 { font-family: 'Orbitron', sans-serif; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 30px; }
        input[type="text"], input[type="url"], textarea {
            width: 100%; padding: 14px 18px; margin: 10px 0;
            border-radius: 14px; border: 1px solid var(--border-color);
            background: var(--input-bg); color: var(--text-main); outline: none;
        }
        .field-label { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin: 16px 0 4px; display: block; opacity: 0.7; }
        button[type="submit"] {
            width: 100%; padding: 14px 25px; margin-top: 20px; border: none; border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), #774280); color: #fff;
            font-family: 'Orbitron', sans-serif; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
        }
        .error { color: #f1416c; background: rgba(241,65,108,0.1); padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .categories-list { display: flex; flex-wrap: wrap; gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; border-radius: 12px; background: var(--input-bg); }
        .categories-list label span { background: var(--cat-bg); padding: 6px 14px; border-radius: 20px; cursor: pointer; display: inline-block; transition: 0.2s; }
        .categories-list input:checked + span { background: var(--cat-active-bg); color: white; }
        #dropArea { border: 2px dashed var(--accent); padding: 20px; border-radius: 15px; text-align: center; cursor: pointer; margin: 15px 0; }
        #dropArea img { max-width: 100%; max-height: 200px; border-radius: 10px; margin-top: 10px; }
        .back-btn { position: fixed; top: 20px; right: 20px; width: 40px; cursor: pointer; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5)); }
    </style>
</head>
<body class="dark">
    <a href="game.php?id=<?= $game_id ?>"><img src="imgandgifs/arrow-left-circle.svg" class="back-btn" alt="Back"></a>

    <div class="container">
        <h2>Update Game Details</h2>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label class="field-label">Game Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($game['title']) ?>" required>

            <label class="field-label">Description</label>
            <textarea name="description" rows="5" required><?= htmlspecialchars($game['description']) ?></textarea>

            <label class="field-label">Store / Buy Link</label>
            <input type="url" name="buy_link" value="<?= htmlspecialchars($game['buy_link'] ?? '') ?>" required>

            <label class="field-label">Categories</label>
            <div class="categories-list">
                <?php foreach ($categories as $cat): ?>
                    <label>
                        <input type="checkbox" name="category[]" value="<?= htmlspecialchars($cat) ?>" 
                            <?= in_array($cat, $selected_cats) ? 'checked' : '' ?> style="display:none">
                        <span><?= htmlspecialchars($cat) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <label class="field-label">Replace Image (Optional)</label>
            <div id="dropArea" onclick="document.getElementById('fileInput').click()">
                <div id="dropText">Current image will be kept if no new file is selected. Click to upload new.</div>
                <img id="imgPreview" src="<?= htmlspecialchars($game['main_image']) ?>" alt="Current Image">
            </div>
            <input type="file" name="main_image" id="fileInput" accept="image/*" style="display:none">

            <button type="submit">Update Game Info</button>
        </form>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const imgPreview = document.getElementById('imgPreview');
        const dropText = document.getElementById('dropText');
        
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                imgPreview.src = URL.createObjectURL(file);
                dropText.textContent = "New image selected!";
            }
        });

        // Theme sync from localStorage
        document.body.className = localStorage.getItem('theme') || 'dark';
    </script>
</body>
</html>
