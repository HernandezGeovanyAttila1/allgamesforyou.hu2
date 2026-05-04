<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!empty($_SESSION['is_banned'])) {
    die("You are banned and cannot upload games.");
}



$conn_check = new mysqli($servername, $db_username, $db_password, $database);
if (!$conn_check->connect_error && !isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    if (strpos($_COOKIE['rememberme'], ':') !== false) {
        list($selector, $token) = explode(':', $_COOKIE['rememberme']);
        $stmt = $conn_check->prepare("SELECT user_id,username,role,profile_img,token_validator FROM users WHERE token_selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            if (!empty($row['token_validator']) && password_verify($token, $row['token_validator'])) {
                $_SESSION['user_id']    = $row['user_id'];
                $_SESSION['username']   = $row['username'];
                $_SESSION['role']       = $row['role'];
                $_SESSION['profile_img']= $row['profile_img'] ?? '/imgandgifs/login.png';
                $new_selector  = bin2hex(random_bytes(9));
                $new_token     = bin2hex(random_bytes(33));
                $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                $stmt2 = $conn_check->prepare("UPDATE users SET token_selector=?,token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                $stmt2->execute();
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
                setcookie("rememberme", $new_selector . ":" . $new_token, time() + 86400 * 30, "/", "", $secure, true);
            }
        }
    }
}

$uploadDir      = __DIR__ . '/uploads';
$publicUploadDir = 'uploads';
$maxFileSize    = 5 * 1024 * 1024;
$allowedExt     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Ensure buy_link column exists
$conn->query("ALTER TABLE games ADD COLUMN IF NOT EXISTS buy_link VARCHAR(512) DEFAULT NULL");

$categories = [
    'Indie', 'Action', 'Adventure', 'Casual', 'Singleplayer', 'Simulation', 'RPG', 'Strategy', '2D', 'Early Access', '3D', 'Free to Play', 'Atmospheric', 'Colorful', 'Story Rich', 'Exploration', 'Fantasy', 'Cute', 'Multiplayer', 'Pixel Graphics', 'Puzzle', 'Combat', 'First-Person', 'Action-Adventure', 'Relaxing', 'Funny', 'Stylized', 'Arcade', 'Anime', 'Controller', 'Horror', 'Massively Multiplayer', 'Sports', 'Sci-fi', 'PvE', 'Third Person', 'Violent', 'Shooter', 'Choices Matter', 'Retro', 'Family Friendly', 'Top-Down', 'Female Protagonist', 'Sexual Content', 'Co-op', 'Racing', 'Realistic', 'Dark', 'PvP', 'Nudity', 'Open World', 'Linear', 'Mystery', 'Survival', 'Multiple Endings', 'Character Customization', 'Cartoon', 'Comedy', 'Platformer', 'Visual Novel', 'Physics', 'Psychological Horror', 'Online Co-op', 'Gore', '2D Platformer', 'Roguelike', 'Magic', 'Rogue-lite', 'FPS', 'Management', 'Sandbox', 'Medieval', 'Tactical', 'Hand-drawn', 'Action RPG', 'Resource Management', 'Old School', 'Minimalist', 'Immersive Sim', 'Futuristic', 'Drama', 'Crafting', 'Building', 'Point & Click', 'Emotional', 'Dark Fantasy', 'Cartoon', 'Action Roguelike', 'Difficult', 'Space', '3D Platformer', 'Procedural Generation', 'Romance', 'Choose Your Own Adventure', 'Interactive Fiction', 'Nature', 'Survival Horror', 'Logic', 'Turn-Based Combat', 'Turn-Based Tactics', 'Turn-Based Strategy', 'Hentai', 'Local Multiplayer', 'Adult', '90s','VR', 'Base Building', 'Hack and Slash', 'Hidden Object', 'Surreal', 'Dating Sim', 'Side Scroller', 'Puzzle Platformer', 'Bullet Hell', 'Post-apocalyptic', 'Education', 'Walking Simulator', 'Dungeon Crawler', 'Shoot Em Up', 'Cinematic', 'Clicker', 'NSFW', 'War', 'Lore-Rich', 'Utilities', 'Conversation', 'Life Sim', 'Score Attack', 'Zombies', 'JRPG', 'Text-Based', 'Subtitles', 'Board Game', 'Great Soundtrack', 'Card Game', 'Inventory Management', 'Design & Illustration', 'Stealth', '80s', 'LGBTQ+', 'Psychological', 'Local Co-op', 'Investigation', 'Economy', '2.5D', 'Thriller', 'Historical', 'Supernatural', 'Party-Based RPG', 'Idler', 'Isometric', 'Nonlinear', 'Educational', 'Dark Humor', 'Third-Person Shooter', 'Military', 'Top-Down Shooter', 'Replay Value', 'Demons', 'Time Management', 'Deckbuilding', 'Team-Based', 'Aliens', 'Strategy RPG', 'Artificial Intelligence', 'Cyberpunk', 'Robots', 'Loot', 'Detective', 'Collectathon', 'Turn-Based', 'Real-Time Tactics', 'Modern', 'Dystopia', 'Abstract', 'Permadeath', 'Tower Defense', 'Driving', 'RTS', 'Precision Platformer', 'Tabletop', 'Arena Shooter', 'Psychedelic', 'Comic Book', 'Souls-like', 'Tactical RPG', 'Card Battler', 'City Builder', 'Memes', 'Mythology', 'Software', 'Alternative History', 'Cats', 'Wargame', 'Automation', 'Local 4 Player', 'Capitalism', 'Web Content', 'Game Development', 'Creature Collector', 'Grid-Based Movement', 'Short', 'Crime', 'Beat em up', 'Destruction', 'Metroidvania', 'Parkour', 'Fast-Paced', 'Cozy', 'Flight', 'CRPG', 'Level Editor', 'Animation & Modeling', 'Runner', 'Class-Based', 'Moddable', 'Philosophical', 'Music', 'Dark Comedy', '2D Fighter', 'Soundtrack', 'Weapon Customization', 'Trading', 'Automobile Sim', 'Cooking', 'Farming Sim', 'Rhythm', 'RPGMaker', 'Vehicular Combat', 'MMORPG', '3D Fighter', 'Co-op Campaign', 'Auto Battler', 'Lovecraftian', 'Science', 'Swordplay', 'Noir', 'America', 'Quick-Time Events', 'Conspiracy', 'Competitive', 'Twin Stick Shooter', 'Fighting', 'Dragons', 'Word Game', 'Colony Sim', 'eSports', 'Party Game', 'Space Sim', 'Classic', 'Parody', 'Grand Strategy', 'Satire', 'Gothic', 'Video Editing', 'Experimental', '3D Vision', 'Battle Royale', 'Dynamic Narration', 'Looter Shooter', 'Mystery Dungeon', '6DOF', 'Audio Production', 'Underground', 'Mining', 'Split Screen', 'WWII', 'Agriculture', 'Narrative', 'Bullet Time', 'Time Manipulation', 'Fishing', 'Martial Arts', 'Political', 'Beautiful', 'Movie', 'Wholesome', 'Hero Shooter', 'Match 3', 'Mechs', 'Spectacle Fighter', 'Combat Racing', 'Roguelike Deckbuilder', 'Immersive', 'Dogs', 'Blood', 'Open World Survival Craft', 'Action RTS', 'Time Travel', 'Voxel', 'FMV', 'Gambling', 'Asynchronous Multiplayer', 'Vampires', 'Otome', 'Trading Card Game', 'God Game', 'Solitaire', 'Steampunk', 'Pirates', 'Politics', 'Transportation', 'Software Training', 'Underwater', 'Hunting', 'Boomer Shooter', 'Hex Grid', 'Faith', 'Hacking', 'Tanks', 'Ninjas', 'Political Sim', 'Trains', 'MOBA', 'Typing', 'Business Management', 'Sokoban', '4X', 'Assassin', 'Superhero', 'Illuminati', 'Character Action Game', 'Renovation', 'Programming', 'Party', 'Dinosaurs', 'Western', 'Diplomacy', 'Photo Editing', 'Heist', 'Mouse only', 'Cold War', 'Mini-games', 'Foreign', 'Snow', 'Naval', 'Transhumanism', 'Traditional Roguelike', 'Addictive', 'Naval Combat', 'Archery', 'Escape Room', 'Job Simulator', 'Sailing', 'Horses', 'Real-Time', 'Episodic', 'Nostalgia', 'Farming', 'Epic', 'Music-Based Procedural Generation', 'Off-Road', 'Trivia', 'Werewolves', 'Villain Protagonist', 'Sniper', 'Soccer', 'Real-Time with Pause', 'Cult Classic', 'On-Rail Shooter', 'Time Attack', 'Dungeons & Dragons', 'Sequel', 'Spelling', 'Mars', 'WWI', 'Outbreak Sim', 'Boxing', 'Jet', 'Touch-Friendly', 'Mod', 'Chess', 'Roguevania', 'Kickstarter', 'Dwarves', 'Astronauts', 'Basketball', 'Golf', 'Medical Sim', 'Motorbike', '360 Video', 'Gaming', 'Submarine', 'Spooky', 'Unforgiving', 'Guessing', 'Extraction Shooter', 'Games Workshop', 'Experience', 'Rome', 'Bikes', 'GameMaker', 'Pinball', 'Relaxing', 'LEGO', 'Electronic Music', 'Dice', 'Asymmetric VR', 'Baseball', 'Wrestling', 'Boss Rush', 'Silent Protagonist', 'Skateboarding', 'Well-Written', 'Instrumental Music', 'Billiards', 'Minigolf', 'Football (American)', 'Warhammer 40K', 'Elves', 'Documentary', 'Skater', 'Vikings', 'Cycling', 'Rock Music', 'Community-Supported', 'Tennis', 'TrackIR', 'Intentionally Awkward Controls', 'Motocross', 'Birds', 'Bowling', 'Hockey', 'Off-road', 'Mahjong', 'Based on a Novel', 'Snowboarding', '8-bit Music', 'Lemmings', 'Skiing', 'Voice Control', 'Hardware', 'BMX', 'Foxes', 'Musou', 'Benchmark', 'Electronic', 'Steam Hardware', 'Hobby Sim', 'Coding', 'Volleyball', 'Film', 'Cricket', 'Rugby', 'Snooker', 'Reimagining'
];
$initial_display = 10;
$error   = '';
$success = '';

function sanitize_file_name($name) {
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return substr($name, 0, 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title               = trim($_POST['title'] ?? '');
    $description         = trim($_POST['description'] ?? '');
    $buy_link            = trim($_POST['buy_link'] ?? '');
    $selected_categories = $_POST['category'] ?? [];
    $user_id             = (int)($_SESSION['user_id'] ?? 0);

    if ($title === '' || $description === '') {
        $error = "Please provide title and description.";
    } elseif ($buy_link === '') {
        $error = "Please provide a store/buy link for the game.";
    } elseif (!filter_var($buy_link, FILTER_VALIDATE_URL)) {
        $error = "The store link must be a valid URL (e.g. https://store.steampowered.com/...).";
    } else {
        $stmt = $conn->prepare("SELECT game_id FROM games WHERE LOWER(title)=LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $title);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $error = "A game with this title already exists.";
        $stmt->close();
    }

    if ($error === '') {
        if (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== 0) {
            $error = "Please select an image (no upload errors).";
        } else {
            $file        = $_FILES['main_image'];
            $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file['size'] > $maxFileSize) {
                $error = "Image too large. Max " . ($maxFileSize / 1048576) . " MB.";
            } elseif (!in_array($originalExt, $allowedExt)) {
                $error = "Invalid image type. Allowed: " . implode(', ', $allowedExt) . ".";
            } else {
                $safeBase     = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
                $newFileName  = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $originalExt;
                $destFullPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
                $dbPath       = $publicUploadDir . '/' . $newFileName;
                if (!move_uploaded_file($file['tmp_name'], $destFullPath)) {
                    $error = "Failed to upload image.";
                }
            }
        }
    }

    if ($error === '') {
        $conn->begin_transaction();
        try {
            $ins = $conn->prepare("INSERT INTO games (title,description,main_image,buy_link,created_by,created_at) VALUES (?,?,?,?,?,NOW())");
            $ins->bind_param("ssssi", $title, $description, $dbPath, $buy_link, $user_id);
            $ins->execute();
            $game_id = $ins->insert_id;
            $ins->close();

            if (!empty($selected_categories)) {
                $validCats = array_intersect($selected_categories, $categories);
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
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            if (isset($destFullPath) && file_exists($destFullPath)) @unlink($destFullPath);
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
    <link rel="icon" type="image/x-icon" href="/imgandgifs/logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Orbitron:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #bf32f1; }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191,50,241,0.2);
            --glass: rgba(15,10,21,0.75);
            --shadow: 0 10px 40px rgba(0,0,0,0.6);
            --glow: 0 0 30px rgba(191,50,241,0.4);
            --input-bg: rgba(255,255,255,0.05);
            --cat-bg: rgba(191,50,241,0.1);
            --cat-hover: rgba(191,50,241,0.18);
            --cat-active-bg: #bf32f1;
            --cat-active-text: #fff;
            --drop-border: rgba(191,50,241,0.4);
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155,89,182,0.25);
            --glass: rgba(247,243,232,0.85);
            --shadow: 0 10px 30px rgba(155,89,182,0.15);
            --glow: 0 0 20px rgba(155,89,182,0.2);
            --input-bg: rgba(0,0,0,0.04);
            --cat-bg: rgba(155,89,182,0.08);
            --cat-hover: rgba(155,89,182,0.15);
            --cat-active-bg: #9b59b6;
            --cat-active-text: #fff;
            --drop-border: rgba(155,89,182,0.4);
        }

        * { box-sizing: border-box; }

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
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            background:
                radial-gradient(circle at 0% 0%, var(--bg-mesh-2) 0%, transparent 50%),
                radial-gradient(circle at 100% 0%, var(--bg-mesh-3) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, var(--bg-mesh-2) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, var(--bg-mesh-3) 0%, transparent 50%),
                var(--bg-mesh-1);
            background-size: 200% 200%;
            animation: meshFlow 20s ease infinite;
        }

        @keyframes meshFlow {
            0%   { background-position: 0% 0%; }
            50%  { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }

        .container {
            background: var(--glass);
            backdrop-filter: blur(25px);
            padding: 40px;
            border-radius: 32px;
            margin: 60px 10px;
            width: 100%;
            max-width: 700px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            animation: floatIn 1s cubic-bezier(0.19,1,0.22,1);
        }

        @keyframes floatIn {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        h2 {
            color: var(--text-main);
            margin-bottom: 30px;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: var(--glow);
        }

        input[type="text"],
        input[type="url"],
        textarea {
            width: 100%;
            padding: 14px 18px;
            margin: 10px 0;
            border-radius: 14px;
            border: 1px solid var(--border-color);
            outline: none;
            font-size: 1em;
            font-family: 'Poppins', sans-serif;
            background: var(--input-bg);
            color: var(--text-main);
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="url"]:focus,
        textarea:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        textarea { resize: vertical; min-height: 120px; }

        /* Buy link field highlight */
        .buy-link-wrapper { position: relative; margin: 10px 0; }
        .buy-link-wrapper::before {
            content: '🛒';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1em;
            pointer-events: none;
        }
        .buy-link-wrapper input {
            padding-left: 46px;
            margin: 0;
            border-color: rgba(191,50,241,0.45);
        }
        .buy-link-wrapper input:focus { border-color: var(--accent); box-shadow: var(--glow); }
        .field-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            margin: 16px 0 4px;
            display: block;
        }
        .field-label .required { color: #f1416c; margin-left: 3px; }

        button[type="submit"] {
            width: 100%;
            padding: 14px 25px;
            margin-top: 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: #fff;
            font-size: 1.1em;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.19,1,0.22,1);
            box-shadow: var(--glow);
        }
        button[type="submit"]:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 20px rgba(191,50,241,0.4);
            filter: brightness(1.1);
        }

        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 50px;
            cursor: pointer;
            transition: transform 0.2s;
            z-index: 1001;
        }
        .back-btn:hover { transform: scale(1.1); }

        .theme-toggle-btn {
            position: fixed;
            top: 30px; left: 30px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-radius: 50%;
            cursor: pointer;
            width: 55px; height: 55px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.19,1,0.22,1);
            z-index: 1000;
        }
        .theme-toggle-btn img { width: 30px; height: 30px; transition: all 0.5s ease; }
        .theme-toggle-btn:hover { transform: scale(1.1) rotate(10deg); border-color: var(--accent); box-shadow: var(--glow); }

        .error {
            background: rgba(241,65,108,0.12);
            border: 1px solid rgba(241,65,108,0.4);
            color: #f1416c;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .categories-container { margin-top: 10px; }
        .categories-search {
            width: 100%;
            padding: 10px 14px;
            margin-bottom: 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            font-size: 0.95em;
            background: var(--input-bg);
            color: var(--text-main);
            outline: none;
            font-family: 'Poppins', sans-serif;
        }
        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 8px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
        }
        .categories-list input[type="checkbox"] { display: none; }
        .categories-list label { padding: 0; cursor: pointer; user-select: none; }
        .categories-list label span {
            background: var(--cat-bg);
            padding: 8px 16px;
            border-radius: 25px;
            display: inline-block;
            transition: 0.18s ease;
            color: var(--text-main);
            font-size: 0.9em;
        }
        .categories-list input[type="checkbox"]:checked + span {
            background: var(--cat-active-bg);
            color: var(--cat-active-text);
            font-weight: 600;
            transform: scale(1.03);
        }
        .categories-list label:hover span { background: var(--cat-hover); }

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
            background: rgba(191,50,241,0.05);
            box-shadow: inset 0 0 20px rgba(191,50,241,0.1);
        }
        #dropArea img { display: none; max-width: 100%; max-height: 340px; border-radius: 12px; object-fit: contain; }
        #dropText { pointer-events: none; }

        @media (max-width:600px) {
            .container { padding: 24px; }
            .categories-list { justify-content: center; }
        }
    </style>
</head>

<body>
    <button class="theme-toggle-btn" id="themeToggle" title="Toggle theme">
        <img src="imgandgifs/sun.svg" alt="Theme">
    </button>
    <a href="games.php"><img src="imgandgifs/arrow-left-circle.svg" class="back-btn" alt="Back"></a>

    <div class="container">
        <h2>Add New Game</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <label class="field-label">Game Title <span class="required">*</span></label>
            <input type="text" name="title" placeholder="e.g. The Witcher 3" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>

            <label class="field-label">Description <span class="required">*</span></label>
            <textarea name="description" placeholder="Describe the game..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

            <label class="field-label">Store / Buy Link <span class="required">*</span></label>
            <div class="buy-link-wrapper">
                <input type="url" name="buy_link"
                    placeholder="https://store.steampowered.com/app/..."
                    value="<?= htmlspecialchars($_POST['buy_link'] ?? '') ?>"
                    required>
            </div>

            <label class="field-label">Categories</label>
            <div class="categories-container">
                <input type="text" id="catSearch" class="categories-search" placeholder="Search categories...">
                <div class="categories-list" id="catList">
                    <?php foreach ($categories as $i => $cat): ?>
                        <label style="display:<?= ($i < $initial_display) ? 'flex' : 'none' ?>">
                            <input type="checkbox" name="category[]" value="<?= htmlspecialchars($cat) ?>">
                            <span><?= htmlspecialchars($cat) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="field-label">Game Image <span class="required">*</span></label>
            <div id="dropArea">
                <span id="dropText">Click or Drag &amp; Drop Image Here (max <?= $maxFileSize / 1048576 ?> MB)</span>
                <img id="imgPreview" src="#" alt="Preview">
            </div>
            <input type="file" name="main_image" id="fileInput" accept="image/*" style="display:none" required>

            <button type="submit">Add Game</button>
        </form>
    </div>

    <script>
        // Category search
        const catSearch = document.getElementById('catSearch');
        const labels    = document.querySelectorAll('.categories-list label');
        catSearch.addEventListener('input', () => {
            const val = catSearch.value.toLowerCase();
            labels.forEach(l => l.style.display = l.innerText.toLowerCase().includes(val) ? 'flex' : 'none');
        });

        // Drop area
        const dropArea   = document.getElementById('dropArea');
        const fileInput  = document.getElementById('fileInput');
        const imgPreview = document.getElementById('imgPreview');
        const dropText   = document.getElementById('dropText');
        const MAX_MB     = <?= (int)($maxFileSize / 1048576) ?>;

        function previewImage(file) {
            if (file.size > MAX_MB * 1048576) { alert('Image too large!'); fileInput.value = ''; return; }
            imgPreview.src = URL.createObjectURL(file);
            imgPreview.onload = () => URL.revokeObjectURL(imgPreview.src);
            imgPreview.style.display = 'block';
            dropText.style.display   = 'none';
        }

        dropArea.addEventListener('click', () => fileInput.click());
        dropArea.addEventListener('dragover',  e => { e.preventDefault(); dropArea.classList.add('hover'); });
        dropArea.addEventListener('dragleave', e => { e.preventDefault(); dropArea.classList.remove('hover'); });
        dropArea.addEventListener('drop', e => {
            e.preventDefault(); dropArea.classList.remove('hover');
            if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; previewImage(fileInput.files[0]); }
        });
        fileInput.addEventListener('change', () => { if (fileInput.files.length) previewImage(fileInput.files[0]); });
        imgPreview.addEventListener('click', () => {
            fileInput.value = ''; imgPreview.src = '';
            imgPreview.style.display = 'none'; dropText.style.display = 'block';
        });

        // Theme
        const themeBtn = document.getElementById('themeToggle');
        function updateThemeUI() {
            const isDark = document.body.classList.contains('dark');
            const img = themeBtn.querySelector('img');
            if (img) {
                img.src = isDark ? 'imgandgifs/moon.svg' : 'imgandgifs/sun.svg';
                img.style.transform = isDark ? 'rotate(180deg)' : 'rotate(0deg)';
                img.style.filter = isDark
                    ? 'drop-shadow(0 0 8px rgba(149,87,161,0.6))'
                    : 'drop-shadow(0 0 8px rgba(255,157,0,0.6))';
            }
        }
        function applyTheme() {
            document.body.className = localStorage.getItem('theme') || 'dark';
            updateThemeUI();
        }
        themeBtn.addEventListener('click', () => {
            const newTheme = document.body.classList.contains('dark') ? 'bright' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme();
        });
        applyTheme();
    </script>
</body>
</html>
