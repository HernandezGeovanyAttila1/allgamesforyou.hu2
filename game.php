<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
session_start();

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (!isset($_GET['id'])) die("Game ID missing!");
$game_id = intval($_GET['id']);

/* ---------- HANDLE COMMENT SUBMISSION ---------- */
if (isset($_POST['comment_submit']) && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);

    if (!empty($content)) {
        $stmt = $conn->prepare("
            INSERT INTO comments (game_id, user_id, content, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $game_id, $_SESSION['user_id'], $content);
        $stmt->execute();
    }

    header("Location: game.php?id=$game_id");
    exit();
}

/* ---------- FETCH GAME ---------- */
$stmt = $conn->prepare("SELECT * FROM games WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_result = $stmt->get_result();

if ($game_result->num_rows !== 1) die("Game not found!");
$game = $game_result->fetch_assoc();

/* ---------- FETCH CATEGORIES ---------- */
$cat_stmt = $conn->prepare("SELECT category FROM game_categories WHERE game_id=?");
$cat_stmt->bind_param("i", $game_id);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();

$game_categories = [];
while ($row = $cat_result->fetch_assoc()) $game_categories[] = $row['category'];

/* ---------- FETCH COMMENTS ---------- */
$comment_stmt = $conn->prepare("
    SELECT c.content, u.username, u.profile_img, c.created_at
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.game_id = ?
    ORDER BY c.created_at DESC
");
$comment_stmt->bind_param("i", $game_id);
$comment_stmt->execute();
$comments = $comment_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($game['title']); ?></title>

<style>
    body {
        font-family: Poppins, sans-serif;
        background: linear-gradient(180deg, #6a1b9a, #4a0072);
        margin: 0;
        padding: 20px;
        color: #fff;
    }

    .container {
        max-width: 900px;
        margin: auto;
        background: rgba(255,255,255,0.12);
        padding: 25px;
        border-radius: 15px;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    h1 { 
        margin-top: 0; 
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        max-width: 100%;
        font-size: 2em;
    }

    /* Fix description text overflowing */
    .game-description {
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: pre-wrap;
        line-height: 1.5;
        margin-bottom: 20px;
    }

    img.game-image {
        width: 100%;
        border-radius: 12px;
        margin-bottom: 15px;
    }

    .category-list span {
        background: rgba(255,255,255,0.2);
        padding: 5px 10px;
        border-radius: 5px;
        margin-right: 5px;
        font-size: 0.9em;
    }

    /* COMMENT SECTION */
    .comments-container {
        margin-top: 25px;
    }

    .comment {
        background: rgba(255,255,255,0.15);
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 12px;
        display: flex;
        gap: 15px;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.1);
        transition: 0.25s;
    }

    .comment:hover {
        background: rgba(255,255,255,0.22);
    }

    .comment img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ffd740;
    }

    .comment .content {
        flex-grow: 1;
        overflow-wrap: break-word;
        word-wrap: break-word;
        white-space: pre-wrap;
    }

    .comment .username {
        font-weight: bold;
        color: #ffd740;
        margin-bottom: 3px;
    }

    .comment small {
        opacity: 0.85;
        font-size: 0.8em;
    }

    textarea {
        width: 100%;
        padding: 12px;
        border-radius: 12px;
        border: 2px solid rgba(255,255,255,0.25);
        background-color: rgba(255,255,255,0.08);
        resize: none;
        color: #fff;
        font-size: 1em;
        outline: none;
        transition: 0.25s;
        overflow-wrap: break-word;
        word-wrap: break-word;
        white-space: pre-wrap;
    }

    textarea:focus {
        border-color: #ffd740;
        background-color: rgba(0,0,0,0.25);
    }

    button.comment-btn {
        background: #ffd740;
        color: #000;
        border: none;
        padding: 10px 18px;
        border-radius: 10px;
        margin-top: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: 0.25s;
    }

    button.comment-btn:hover {
        background: #ffeb80;
        transform: translateY(-2px);
    }

    textarea.auto-expand {
        overflow: hidden;
    }

    a {
        color: #ffd740;
        text-decoration: underline;
    }
</style>

</head>

<body>
<div class="container">

    <h1><?= htmlspecialchars($game['title']); ?></h1>

    <div class="category-list">
        <?php
        if ($game_categories) {
            foreach ($game_categories as $cat) echo "<span>" . htmlspecialchars($cat) . "</span>";
        } else {
            echo "<span>Uncategorized</span>";
        }
        ?>
    </div>

    <img class="game-image"
         src="<?= htmlspecialchars($game['main_image'] ?: 'default_game.png') ?>"
         alt="<?= htmlspecialchars($game['title']) ?>">

    <p class="game-description">
        <?= nl2br(htmlspecialchars($game['description'])); ?>
    </p>

    <h2>Comments</h2>

    <div class="comments-container">
    <?php while ($c = $comments->fetch_assoc()): ?>
        <div class="comment">
            <img src="<?= htmlspecialchars($c['profile_img'] ?: 'imgandgifs/login.png'); ?>" alt="profile">

            <div class="content">
                <div class="username"><?= htmlspecialchars($c['username']) ?></div>
                <p><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                <small><?= htmlspecialchars($c['created_at']) ?></small>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST">
            <textarea class="auto-expand" name="content" rows="2" placeholder="Write a comment..." required></textarea>
            <button type="submit" name="comment_submit" class="comment-btn">Post Comment</button>
        </form>
    <?php else: ?>
        <p><a href="auth.php">Login to comment</a></p>
    <?php endif; ?>

    <br><br>
    <a href="index.php">← Back to Home</a>

</div>

<script>
// Auto-expanding textarea
document.querySelectorAll("textarea.auto-expand").forEach(t => {
    t.addEventListener("input", () => {
        t.style.height = "auto";
        t.style.height = (t.scrollHeight) + "px";
    });
});
</script>

</body>
</html>
