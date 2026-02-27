<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'utils.php';

if (!empty($_SESSION['is_banned'])) {
    die("You are banned and cannot upload games.");
}


// AUTO-LOGIN USING REMEMBER-ME
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn_check = new mysqli($servername, $db_username, $db_password, $database);
if (!$conn_check->connect_error && !isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    if (strpos($_COOKIE['rememberme'], ':') !== false) {
        list($selector, $token) = explode(':', $_COOKIE['rememberme']);
        $stmt = $conn_check->prepare("SELECT user_id, username, role, profile_img, token_validator FROM users WHERE token_selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            if (!empty($row['token_validator']) && password_verify($token, $row['token_validator'])) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';
                // rotate token
                $new_selector = bin2hex(random_bytes(9));
                $new_token = bin2hex(random_bytes(33));
                $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                $stmt2 = $conn_check->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                $stmt2->execute();
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
                setcookie("rememberme", $new_selector . ":" . $new_token, time() + 86400 * 30, "/", "", $secure, true);
            }
        }
    }
}

/* ---------- original add_games.php logic (unchanged except DB connection reuse) ---------- */

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$uploadDir = __DIR__ . '/uploads';
$publicUploadDir = 'uploads';
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$categories = [
    'Platform games',
    'Adventure',
    'Shooter games',
    'Fighting games',
    'Stealth games',
    'Survival games',
    'Rhythm games',
    'Battle Royale games',
    'Puzzle games',
    'Logical game',
    'Role-playing',
    'CRPG',
    'MMORPG',
    'Roguelikes',
    'Sandbox RPG',
    'Simulation',
    'Vehicle simulation',
    'Strategy',
    'Multiplayer online battle arena (MOBA)',
    'Tower defense',
    'Wargame',
    'Competitive',
    'Board game',
    'Casino game',
    'Gacha game',
    'Horror game',
    'Idle game',
    'Party game',
    'Sandbox',
];

$initial_display = 10;
$error = '';
$success = '';

function sanitize_file_name($name)
{
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return substr($name, 0, 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selected_categories = $_POST['category'] ?? [];
    $user_id = (int) $_SESSION['user_id'];

    if ($title === '' || $description === '') {
        $error = "Please provide title and description.";
    } else {
        $stmt = $conn->prepare("SELECT game_id FROM games WHERE LOWER(title)=LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $title);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $error = "A game with this title already exists.";
        }
        $stmt->close();
    }

    if ($error === '') {
        if (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== 0) {
            $error = "Please select an image (no upload errors).";
        } else {
            $file = $_FILES['main_image'];
            if ($file['size'] > $maxFileSize) {
                $error = "Image too large. Maximum allowed size is " . ($maxFileSize / (1024 * 1024)) . " MB.";
            } else {
                $originalName = $file['name'];
                $originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($originalExt, $allowedExt)) {
                    $error = "Invalid image type. Allowed types: " . implode(', ', $allowedExt) . ".";
                } else {
                    $base = pathinfo($originalName, PATHINFO_FILENAME);
                    $safeBase = sanitize_file_name($base);
                    $newFileName = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $originalExt;
                    $destFullPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newFileName;
                    $dbPath = $publicUploadDir . '/' . $newFileName;

                    // Fast upload
                    if (!move_uploaded_file($file['tmp_name'], $destFullPath)) {
                        $error = "Failed to upload image.";
                    } else {
                        // Compress with Tinify
                        compressImageWithTinyPng($destFullPath);
                    }
                }
            }
        }
    }

    if ($error === '') {
        $conn->begin_transaction();
        try {
            $insertStmt = $conn->prepare("INSERT INTO games (title, description, main_image, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            $insertStmt->bind_param("sssi", $title, $description, $dbPath, $user_id);
            $insertStmt->execute();
            $game_id = $insertStmt->insert_id;
            $insertStmt->close();

            // ✅ Batch category insert
            if (!empty($selected_categories)) {
                $validCats = array_intersect($selected_categories, $categories);
                if (!empty($validCats)) {
                    $values = [];
                    $types = '';
                    $params = [];

                    foreach ($validCats as $cat) {
                        $values[] = "(?, ?)";
                        $types .= "is";
                        $params[] = $game_id;
                        $params[] = $cat;
                    }

                    $sql = "INSERT INTO game_categories (game_id, category) VALUES " . implode(", ", $values);
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $tmp = [];
                        foreach ($params as $k => $v)
                            $tmp[$k] = &$params[$k];
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
        } catch (Exception $e) {
            $conn->rollback();
            if (isset($destFullPath) && file_exists($destFullPath)) {
                @unlink($destFullPath);
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!-- HTML unchanged... (same as your original add_games.php markup & JS) -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add New Game</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Theme Variables */
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: rgba(255, 255, 255, 0.05);
            --cat-bg: rgba(191, 50, 241, 0.1);
            --cat-hover: rgba(191, 50, 241, 0.18);
            --cat-active-bg: var(--accent);
            --cat-active-text: #fff;
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.25);
            --glass: rgba(247, 243, 232, 0.85);
            --glass-strong: rgba(255, 255, 255, 0.95);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --input-bg: rgba(0, 0, 0, 0.04);
            --cat-bg: rgba(155, 89, 182, 0.08);
            --cat-hover: rgba(155, 89, 182, 0.15);
            --cat-active-bg: #9b59b6;
            --cat-active-text: #fff;
        }

        /* (same CSS as you provided earlier) */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 20px;
            color: var(--text-main);
            transition: color 0.5s ease;
            background-attachment: fixed;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at 0% 0%, var(--bg-mesh-2) 0%, transparent 50%),
                radial-gradient(circle at 100% 0%, var(--bg-mesh-3) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, var(--bg-mesh-2) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, var(--bg-mesh-3) 0%, transparent 50%),
                var(--bg-mesh-1);
            background-size: 200% 200%;
            animation: meshFlow 20s ease infinite;
        }

        @keyframes meshFlow {
            0% {
                background-position: 0% 0%;
            }

            50% {
                background-position: 100% 100%;
            }

            100% {
                background-position: 0% 0%;
            }
        }

        .container {
            background: var(--glass);
            backdrop-filter: blur(25px);
            padding: 40px;
            border-radius: 32px;
            margin: 60px 10px;
            width: 100%;
            max-width: 700px;
            box-sizing: border-box;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: floatIn 1s cubic-bezier(0.19, 1, 0.22, 1);
        }

        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            color: var(--text-main);
            margin-bottom: 30px;
            font-family: var(--orbitron);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: var(--glow);
        }

        input,
        textarea,
        select,
        button {
            width: 100%;
            box-sizing: border-box;
        }

        input,
        textarea {
            padding: 14px 18px;
            margin: 12px 0;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            outline: none;
            font-size: 1em;
            background: var(--input-bg);
            color: var(--text-main);
            transition: all 0.3s ease;
        }

        input:focus,
        textarea:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        button {
            padding: 14px 25px;
            margin-top: 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: #fff;
            font-size: 1.1em;
            font-family: var(--orbitron);
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--glow);
        }

        button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 20px rgba(191, 50, 241, 0.4);
            filter: brightness(1.1);
        }

        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 19px 30px;
            transition: .2s;
        }

        .back-btn:hover {
            transform: scale(1.08);
        }

        /* Theme Toggle */
        .theme-toggle-btn {
            position: fixed;
            top: 30px;
            left: 30px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            padding: 0;
            border-radius: 50%;
            cursor: pointer;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            z-index: 1000;
            overflow: hidden;
        }

        .theme-toggle-btn img {
            width: 35px;
            height: 35px;
            transition: all 0.6s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1) rotate(10deg);
            border-color: var(--accent);
            box-shadow: var(--glow);
        }


        .error {
            color: #ff5252;
            margin-bottom: 10px;
        }

        .success {
            color: #69f0ae;
            margin-bottom: 10px;
        }

        a.back-link {
            color: var(--text-main);
            text-decoration: underline;
            display: inline-block;
            margin-top: 15px;
        }

        .categories-container {
            text-align: left;
            margin-top: 10px;
        }

        .categories-search {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 12px;
            border: none;
            font-size: 0.95em;
            background: var(--input-bg);
            color: var(--input-text);
        }

        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 8px;
            border-radius: 12px;
            background: var(--input-bg);
        }

        .categories-list input[type="checkbox"] {
            display: none;
        }

        .categories-list label {
            padding: 0;
            background: none;
            cursor: pointer;
            user-select: none;
        }

        .categories-list label span {
            background: var(--cat-bg);
            padding: 10px 18px;
            border-radius: 25px;
            display: inline-block;
            transition: 0.18s ease;
            color: var(--text-main);
            font-size: 0.9em;
        }

        .categories-list input[type="checkbox"]:checked+span {
            background: var(--cat-active-bg);
            color: var(--cat-active-text);
            font-weight: 600;
            box-shadow: 0 6px 18px var(--shadow-color);
            transform: scale(1.03);
        }

        .categories-list label:hover span {
            background: var(--cat-hover);
        }

        #dropArea {
            margin: 15px 0;
            padding: 18px;
            border: 2px dashed var(--drop-border);
            border-radius: 15px;
            color: var(--text-main);
            cursor: pointer;
            text-align: center;
            font-size: 0.95em;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 120px;
            overflow: hidden;
            transition: border-color 0.3s, background 0.3s;
        }

        #dropArea.hover {
            border-color: var(--accent);
            background: rgba(191, 50, 241, 0.05);
            box-shadow: inset 0 0 20px rgba(191, 50, 241, 0.1);
        }

        #dropArea img {
            display: none;
            max-width: 100%;
            max-height: 340px;
            border-radius: 12px;
            object-fit: contain;
        }

        #dropText {
            pointer-events: none;
        }

        @media(max-width:600px) {
            .categories-list {
                justify-content: center;
            }

            input,
            textarea,
            button {
                font-size: 0.95em;
            }
        }
    </style>
</head>

<body>

    <div id="themeToggle" class="theme-toggle-btn">
        <!--<img src="imgandgifs/darklightmode.webp" alt="Theme">-->
    </div>
    <div class="container">
        <h2>Add New Game</h2>
        <?php if ($error)
            echo "<p class='error'>$error</p>"; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="Game Title" required>
            <textarea name="description" placeholder="Description" required></textarea>

            <div class="categories-container">
                <input type="text" id="catSearch" class="categories-search" placeholder="Search categories...">
                <div class="categories-list" id="catList">
                    <?php foreach ($categories as $i => $cat): ?>
                        <label style="display: <?php echo ($i < $initial_display) ? 'flex' : 'none'; ?>">
                            <input type="checkbox" name="category[]" value="<?php echo htmlspecialchars($cat); ?>">
                            <span>
                                <?php echo htmlspecialchars($cat); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="dropArea">
                <span id="dropText">Click or Drag & Drop Image Here (max
                    <?php echo ($maxFileSize / (1024 * 1024)); ?> MB)
                </span>
                <img id="imgPreview" src="#" alt="Preview">
            </div>
            <input type="file" name="main_image" id="fileInput" accept="image/*" style="display:none;" required>

            <button type="submit">Add Game</button>
        </form>
        <form action="index.php">
            <a href="index.php">
                <img src="imgandgifs/arrow-left-circle.svg" width="50" class="back-btn">
            </a>
        </form>

        <script>
            const searchInput = document.getElementById('catSearch');
            const labels = document.querySelectorAll('.categories-list label');
            searchInput.addEventListener('input', () => {
                const val = searchInput.value.toLowerCase();
                labels.forEach(label => label.style.display = label.innerText.toLowerCase().includes(val) ? "flex" : "none");
            });

            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('fileInput');
            const imgPreview = document.getElementById('imgPreview');
            const dropText = document.getElementById('dropText');
            const MAX_CLIENT_SIZE_MB = <?php echo (int) ($maxFileSize / (1024 * 1024)); ?>;

            function previewImage(file) {
                if (file.size > MAX_CLIENT_SIZE_MB * 1024 * 1024) { alert("Image too large!"); fileInput.value = ""; return; }
                imgPreview.src = URL.createObjectURL(file);
                imgPreview.onload = () => URL.revokeObjectURL(imgPreview.src);
                imgPreview.style.display = 'block';
                dropText.style.display = 'none';
            }

            dropArea.addEventListener('click', () => fileInput.click());
            dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('hover'); });
            dropArea.addEventListener('dragleave', e => { e.preventDefault(); dropArea.classList.remove('hover'); });
            dropArea.addEventListener('drop', e => { e.preventDefault(); dropArea.classList.remove('hover'); if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; previewImage(fileInput.files[0]); } });
            fileInput.addEventListener('change', () => { if (fileInput.files.length) previewImage(fileInput.files[0]); });
            imgPreview.addEventListener('click', () => { fileInput.value = ''; imgPreview.src = ''; imgPreview.style.display = 'none'; dropText.style.display = 'block'; });

            // Theme Logic
            const themeBtn = document.getElementById('themeToggle');
            const bodyValue = document.body;

            function updateThemeUI() {
                const isDark = bodyValue.classList.contains('dark');
                const img = themeBtn.querySelector('img');
                if (img) {
                    img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.2)';
                }
            }

            function applyTheme() {
                const t = localStorage.getItem('theme') || 'dark';
                bodyValue.className = t; // Reset to just theme class
                updateThemeUI();
            }

            themeBtn.addEventListener('click', () => {
                const isDark = bodyValue.classList.contains('dark');
                const newTheme = isDark ? 'bright' : 'dark';
                localStorage.setItem('theme', newTheme);
                applyTheme();
            });
            applyTheme();
        </script>

</body>

</html>