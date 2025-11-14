<?php
// ---- SESSION SETUP (safe for cPanel) ----
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/tmp'));
session_start();



// ---- DATABASE CONNECTION ----
$servername = "localhost";
$db_username = "skdneoaa"; // your cPanel DB username
$db_password = "t3YnVb0HN**40f"; // your DB password
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$login_error = '';
$register_error = '';
$register_success = '';

// ---- LOGOUT ----
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: auth.php");
    exit();
}

// ---- LOGIN ----
if (isset($_POST['login_submit'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, profile_img FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password_hash'])) {
            // ✅ Store user data in session
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? 'imgandgifs/login.png';

            // Redirect to homepage
            header("Location: index.php");
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

    // Check if username or email already exists
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
            $register_success = "Registration successful! You can now log in.";
        } else {
            $register_error = "Registration failed. Please try again.";
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
}
.auth-box {
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(8px);
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.4);
  width: 350px;
  text-align: center;
}
.auth-box h2 {
  margin-top: 0;
  color: #fff;
}
.auth-box input {
  width: 100%;
  padding: 10px;
  margin: 8px 0;
  border: none;
  border-radius: 8px;
  outline: none;
  font-size: 1em;
}
.auth-box button {
  width: 100%;
  padding: 10px;
  background: #9c27b0;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1em;
  cursor: pointer;
  transition: 0.3s;
}
.auth-box button:hover { background: #ba68c8; }
.toggle {
  color: #ffd740;
  cursor: pointer;
  text-decoration: underline;
  display: block;
  margin-top: 10px;
}
.error { color: #ff5252; margin: 5px 0; }
.success { color: #69f0ae; margin: 5px 0; }
a.home-link {
  display: block;
  margin-top: 10px;
  color: #fff;
  text-decoration: underline;
}
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
            <button type="submit" name="login_submit">Login</button>
        </form>
        <p class="toggle" onclick="toggleForms()">Don't have an account? Register</p>
    </div>

    <!-- REGISTER FORM -->
    <div id="registerForm" <?php if(!isset($_POST['register_submit'])) echo 'style="display:none"'; ?>>
        <h2>Register</h2>
        <?php 
        if($register_error) echo "<p class='error'>$register_error</p>";
        if($register_success) echo "<p class='success'>$register_success</p>";
        ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register_submit">Register</button>
        </form>
        <p class="toggle" onclick="toggleForms()">Already have an account? Login</p>
    </div>

    <a href="index.php" class="home-link">← Back to Home</a>
</div>

<script>
function toggleForms(){
    const login = document.getElementById('loginForm');
    const register = document.getElementById('registerForm');
    login.style.display = login.style.display === 'none' ? 'block' : 'none';
    register.style.display = register.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
