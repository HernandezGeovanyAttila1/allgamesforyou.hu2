<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// AUTO-LOGIN
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
                $_SESSION['profile_img'] = $row['profile_img'] ?? 'imgandgifs/login.png';
                // rotate
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

/* The rest of your original games.php content should follow here unchanged. */
?>
