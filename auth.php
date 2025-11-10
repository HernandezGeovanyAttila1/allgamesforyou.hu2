<?php
session_start();
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$login_error = '';
$register_error = '';
$register_success = '';

/* ---------------- LOGIN ---------------- */
if(isset($_POST['login_submit'])){
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->bind_param("s",$user);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows===1){
        $row = $result->fetch_assoc();
        if(password_verify($pass,$row['password_hash'])){
            $_SESSION['user_id']=$row['user_id'];
            $_SESSION['username']=$row['username'];
            $_SESSION['role']=$row['role'];
            header("Location: index.php");
            exit();
        } else { $login_error="Incorrect password"; }
    } else { $login_error="User not found"; }
}

/* ---------------- REGISTER ---------------- */
if(isset($_POST['register_submit'])){
    $user = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $role = 'user';

    // check if username/email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=? OR email=?");
    $stmt->bind_param("ss",$user,$email);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows>0){
        $register_error="Username or email already exists";
    } else {
        $hash = password_hash($pass,PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users(username,email,password_hash,role) VALUES(?,?,?,?)");
        $stmt->bind_param("ssss",$user,$email,$hash,$role);
        if($stmt->execute()) $register_success="Registration successful! You can now login.";
        else $register_error="Registration failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login / Register</title>
<style>
body{font-family:Arial;background:#f0f0f0;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
.auth-box{background:white;padding:30px;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,0.2);width:350px;}
.auth-box h2{text-align:center;}
.auth-box input{width:100%;padding:8px;margin:10px 0;border:1px solid #ccc;border-radius:5px;}
.auth-box button{width:100%;padding:10px;background:#007bff;color:white;border:none;border-radius:5px;cursor:pointer;}
.auth-box button:hover{background:#0056b3;}
.toggle{color:#007bff;cursor:pointer;text-decoration:underline;text-align:center;margin-top:10px;}
.error{color:red;text-align:center;}
.success{color:green;text-align:center;}
</style>
</head>
<body>

<div class="auth-box">
    <div id="loginForm">
        <h2>Login</h2>
        <?php if($login_error) echo "<p class='error'>$login_error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login_submit">Login</button>
        </form>
        <p class="toggle" onclick="toggleForms()">Don't have an account? Register</p>
    </div>

    <div id="registerForm" style="display:none;">
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
</div>

<script>
function toggleForms(){
    document.getElementById('loginForm').style.display = 
        document.getElementById('loginForm').style.display === 'none' ? 'block' : 'none';
    document.getElementById('registerForm').style.display = 
        document.getElementById('registerForm').style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
