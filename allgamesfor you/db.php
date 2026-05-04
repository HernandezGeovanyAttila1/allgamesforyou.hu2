<?php


$conn = new mysqli($servername, $db_username, $db_password, $database);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");

// Table for favorites (updated to match user's screenshot + guest_id support)
$conn->query("CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_id VARCHAR(64) NULL,
    game_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_fav (user_id, game_id),
    UNIQUE KEY unique_guest_fav (guest_id, game_id)
)");

// Table for banned games
$conn->query("CREATE TABLE IF NOT EXISTS user_banned_games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_id VARCHAR(64) NULL,
    game_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_ban (user_id, game_id),
    UNIQUE KEY unique_guest_ban (guest_id, game_id)
)");

// Table for reports
$conn->query("CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    headline VARCHAR(255) NOT NULL,
    report TEXT NOT NULL,
    reply TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Migration to add game_id if it's missing (Old table might not have it)
$check_reports = $conn->query("SHOW COLUMNS FROM reports LIKE 'game_id'");
if ($check_reports && $check_reports->num_rows == 0) {
    $conn->query("ALTER TABLE reports ADD COLUMN game_id INT NOT NULL AFTER user_id");
}

// Migration to add updated_at if it's missing
$check_updated = $conn->query("SHOW COLUMNS FROM reports LIKE 'updated_at'");
if ($check_updated && $check_updated->num_rows == 0) {
    $conn->query("ALTER TABLE reports ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $conn->query("UPDATE reports SET updated_at = created_at WHERE updated_at IS NOT NULL");
}



/**
 * Get or create a persistent guest ID stored in a cookie.
 */
function getGuestId() {
    if (!isset($_COOKIE['guest_id'])) {
        $guestId = bin2hex(random_bytes(16));
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
        setcookie('guest_id', $guestId, time() + (86400 * 365), "/", "", $secure, true);
        $_COOKIE['guest_id'] = $guestId;
    }
    return $_COOKIE['guest_id'];
}
?>
