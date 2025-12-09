<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ---- DATABASE CONNECTION ----
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$login_error = '';
$register_error = '';
$redirect = $_GET['redirect'] ?? 'index.php';

// ---- LOGOUT ----
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET remember_token=NULL WHERE user_id=?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    session_unset();
    session_destroy();
    setcookie("rememberme", "", time()-3600, "/", "", true, true);
    header("Location: auth.php");
    exit();
}

// ---- AUTO LOGIN USING REMEMBER-ME ----
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    $token_raw = $_COOKIE['rememberme'];
    $stmt = $conn->prepare("SELECT user_id, username, role, profile_img, remember_token FROM users WHERE remember_token IS NOT NULL");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['remember_token']) && password_verify($token_raw, $row['remember_token'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? 'imgandgifs/login.png';

            // Regenerate token for security
            $new_token = bin2hex(random_bytes(32));
            $new_hash = password_hash($new_token, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE users SET remember_token=? WHERE user_id=?");
            $stmt2->bind_param("si", $new_hash, $row['user_id']);
            $stmt2->execute();
            setcookie("rememberme", $new_token, time() + 30*24*60*60, "/", "", true, true);

            break;
        }
    }
}

// ---- LOGIN ----
if (isset($_POST['login_submit'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, profile_img FROM users WHERE username=?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? 'imgandgifs/login.png';

            if (isset($_POST['remember'])) {
                $token_raw = bin2hex(random_bytes(32));
                $token_hash = password_hash($token_raw, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET remember_token=? WHERE user_id=?");
                $stmt2->bind_param("si", $token_hash, $row['user_id']);
                $stmt2->execute();
                setcookie("rememberme", $token_raw, time() + 30*24*60*60, "/", "", true, true);
            }

            header("Location: $redirect");
            exit();
        } else {
            $login_error = "Incorrect password.";
        }
    } else {
        $login_error = "User not found.";
    }
}

// ---- REGISTER ----
if (isset($_POST['register_submit'])) {
    $user = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $role = 'user';

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=? OR email=?");
    $stmt->bind_param("ss", $user, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $register_error = "Username or email already exists.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $user, $email, $hash, $role);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['username'] = $user;
            $_SESSION['role'] = $role;
            $_SESSION['profile_img'] = 'imgandgifs/login.png';
            header("Location: $redirect");
            exit();
        } else {
            $register_error = "Registration failed.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login / Register</title>
<style>
body {
  font-family: "Poppins", sans-serif;
  background: linear-gradient(180deg, #6a1b9a, #4a0072);
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin: 0;
  color: #fff;
  padding: 10px;
}
.auth-box {
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(8px);
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.4);
  width: 100%;
  max-width: 400px;
  text-align: center;
  box-sizing: border-box;
}
.auth-box h2 { color: #fff; font-size: 1.5em; }
.auth-box input {
  width: 100%; padding: 12px; margin: 8px 0;
  border: none; border-radius: 8px; outline: none;
  font-size: 1em; box-sizing: border-box;
}
.auth-box button {
  width: 100%; padding: 12px; background: #9c27b0;
  color: white; border: none; border-radius: 8px;
  font-size: 1em; cursor: pointer; transition: 0.3s;
}
.auth-box button:hover { background: #ba68c8; }
.toggle { color: #ffd740; cursor: pointer; text-decoration: underline; margin-top: 10px; }
.error { color: #ff5252; }
.success { color: #69f0ae; }
.home-link { color: #fff; text-decoration: underline; margin-top: 10px; display: block; }
</style>
</head>
<body>

<div class="auth-box">

    <!-- LOGIN FORM -->
    <div id="loginForm" <?php if(isset($_POST['register_submit'])) echo 'style="display:none"'; ?>>
        <h2>Login</h2>
        <?php if($login_error) echo "<p class='error'>$login_error</p>"; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <label style="display:flex;align-items:center;gap:6px;margin:10px 0;">
                <input type="checkbox" name="remember" value="1" style="width:auto;">
                Remember me
            </label>

            <button type="submit" name="login_submit">Login</button>
        </form>

        <p class="toggle" onclick="toggleForms()">Don't have an account? Register</p>
    </div>

    <!-- REGISTER FORM -->
    <div id="registerForm" <?php if(!isset($_POST['register_submit'])) echo 'style="display:none"'; ?>>
        <h2>Register</h2>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>

            <label style="display:flex;align-items:center;gap:6px;margin:10px 0;">
                <input type="checkbox" name="remember" value="1" style="width:auto;">
                Remember me
            </label>

            <button type="submit" name="register_submit">Register</button>
        </form>

        <p class="toggle" onclick="toggleForms()">Already have an account? Login</p>
    </div>

    <a href="index.php" class="home-link">← Back to Home</a>
</div>

<script>
function toggleForms() {
    const login = document.getElementById('loginForm');
    const register = document.getElementById('registerForm');
    login.style.display = login.style.display === 'none' ? 'block' : 'none';
    register.style.display = register.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
