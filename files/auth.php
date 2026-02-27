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
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$login_error = '';
$register_error = '';
$redirect = $_GET['redirect'] ?? 'index.php';

// ---- LOGOUT ----
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET token_selector=NULL, token_validator=NULL WHERE user_id=?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    session_unset();
    session_destroy();
    // Remove rememberme cookie (secure=true). If testing on HTTP, set last param to false.
    setcookie("rememberme", "", time() - 3600, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
    header("Location: auth.php");
    exit();
}

// ---- AUTO LOGIN USING REMEMBER-ME ----
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    if (strpos($_COOKIE['rememberme'], ":") !== false) {
        list($selector, $token) = explode(":", $_COOKIE['rememberme']);
        $stmt = $conn->prepare("SELECT user_id, username, role, profile_img, token_validator FROM users WHERE token_selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (!empty($row['token_validator']) && password_verify($token, $row['token_validator'])) {
                // login
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';
                // refresh token (rotate)
                $new_selector = bin2hex(random_bytes(9));
                $new_token = bin2hex(random_bytes(33));
                $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                $stmt2->execute();
                setcookie("rememberme", $new_selector . ":" . $new_token, time() + 86400 * 30, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
            }
        }
    }
}

// ---- LOGIN ----
if (isset($_POST['login_submit'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, profile_img FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';

            if (isset($_POST['remember'])) {
                $selector = bin2hex(random_bytes(9));
                $token = bin2hex(random_bytes(33));
                $validator = password_hash($token, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $selector, $validator, $row['user_id']);
                $stmt2->execute();
                setcookie("rememberme", $selector . ":" . $token, time() + 86400 * 30, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
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

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=? OR email=? LIMIT 1");
    $stmt->bind_param("ss", $user, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $register_error = "Username or email already exists.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $user, $email, $hash, $role);
        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $user;
            $_SESSION['role'] = $role;
            $_SESSION['profile_img'] = '/imgandgifs/login.png';

            if (isset($_POST['remember'])) {
                $selector = bin2hex(random_bytes(9));
                $token = bin2hex(random_bytes(33));
                $validator = password_hash($token, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET token_selector=?, token_validator=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $selector, $validator, $new_user_id);
                $stmt2->execute();
                setcookie("rememberme", $selector . ":" . $token, time() + 86400 * 30, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
            }

            header("Location: $redirect");
            exit();
        } else {
            $register_error = "Registration failed.";
        }
    }
}

// ---- FORGOT PASSWORD ----
$forgot_error = '';
$forgot_success = '';
$show_reset_form = false;
$reset_email = '';

// Step 1: Request Code
if (isset($_POST['forgot_submit'])) {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $username = $row['username'];

        // Generate 6-digit numeric code (Steam-style)
        $code = sprintf("%06d", mt_rand(1, 999999));
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 15); // 15 minutes

        $stmt = $conn->prepare("UPDATE users SET reset_token_hash=?, reset_token_expires_at=? WHERE user_id=?");
        $stmt->bind_param("ssi", $code_hash, $expiry, $row['user_id']);

        if ($stmt->execute()) {
            $subject = "AllGamesForYou Account Recovery - Verification Code";

            // Professional AllGamesForYou HTML Email Template
            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                    margin: 0;
                    padding: 0;
                    background-color: var(--primary);
                    font-family: "Poppins", Arial, Helvetica, sans-serif;
                    color: var(--text-light);
                }
                
                /* Card container */
                .container {
                    max-width: 600px;
                    margin: 40px auto;
                    background-color: var(--secondary);
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: var(--shadow);
                    border: 1px solid var(--border);
                }
                
                /* Header */
                .header {
                    background-color: var(--primary);
                    padding: 30px;
                    text-align: center;
                    border-bottom: 2px solid var(--border);
                }
                
                /* Content */
                .content {
                    padding: 40px;
                    line-height: 1.6;
                }
                
                /* Greeting text */
                .user-greeting {
                    font-size: 20px;
                    color: var(--text-light);
                    margin-bottom: 20px;
                    font-weight: bold;
                }
                
                /* Instruction text */
                .instruction {
                    font-size: 16px;
                    margin-bottom: 30px;
                    color: var(--text-muted);
                }
                
                /* Code box */
                .code-box {
                    background-color: var(--card-bg);
                    padding: 30px;
                    text-align: center;
                    border-radius: 8px;
                    border: 1px solid var(--border);
                    margin: 30px 0;
                }
                
                /* Code text */
                .code-text {
                    font-size: 42px;
                    font-weight: bold;
                    color: var(--accent);
                    letter-spacing: 12px;
                    font-family: "Courier New", Courier, monospace;
                }
                
                /* Footer */
                .footer {
                    background-color: var(--primary);
                    padding: 25px;
                    text-align: center;
                    font-size: 12px;
                    color: var(--text-muted);
                    border-top: 1px solid var(--border);
                }
                
                /* Warning text */
                .warning {
                    color: var(--text-muted);
                    font-size: 13px;
                    font-style: italic;
                    margin-top: 20px;
}

                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1 style="color: #9557a1; margin: 0; font-size: 26px; text-transform: uppercase; letter-spacing: 2px;">AllGamesForYou</h1>
                    </div>
                    <div class="content">
                        <div class="user-greeting">Dear ' . htmlspecialchars($username) . ',</div>
                        <div class="instruction">We received a request to access your account. To complete the recovery process, please enter the following verification code:</div>
                        
                        <div class="code-box">
                            <div class="code-text">' . $code . '</div>
                        </div>

                        <div class="warning">This code will expire in 15 minutes. If you did not request this code, your account is still secure. No further action is required.</div>
                    </div>
                    <div class="footer">
                        &copy; 2026 AllGamesForYou. All rights reserved.<br>
                        This is an automated message, please do not reply.
                    </div>
                </div>
            </body>
            </html>
            ';

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: AllGamesForYou Support <noreply@anty-gaming.com>" . "\r\n";

            if (@mail($email, $subject, $message, $headers)) {
                $forgot_success = "A verification code has been sent to your email.";
                $show_reset_form = true;
                $reset_email = $email;
            } else {
                $forgot_error = "Server failed to send email. Please contact support.";
            }
        } else {
            $forgot_error = "Database error. Please try again later.";
        }
    } else {
        $forgot_error = "No account is associated with this email address.";
    }
}

// Step 2: Verify Code & Reset Password
if (isset($_POST['reset_submit'])) {
    $email = trim($_POST['email']);
    $code = trim($_POST['code']);
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($pass) < 6) {
        $forgot_error = "Password must be at least 6 characters long.";
        $show_reset_form = true;
        $reset_email = $email;
    } elseif ($pass !== $confirm) {
        $forgot_error = "Passwords do not match.";
        $show_reset_form = true;
        $reset_email = $email;
    } else {
        $stmt = $conn->prepare("SELECT user_id, reset_token_hash, reset_token_expires_at FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (strtotime($row['reset_token_expires_at']) <= time()) {
                $forgot_error = "This verification code has expired. Please request a new one.";
            } elseif (!password_verify($code, $row['reset_token_hash'])) {
                $forgot_error = "Invalid verification code. Please check your email and try again.";
                $show_reset_form = true;
                $reset_email = $email;
            } else {
                $new_hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash=?, reset_token_hash=NULL, reset_token_expires_at=NULL WHERE user_id=?");
                $stmt->bind_param("si", $new_hash, $row['user_id']);

                if ($stmt->execute()) {
                    $forgot_success = "Success! Your password has been updated. You can now login.";
                    $show_reset_form = false;
                } else {
                    $forgot_error = "Failed to update password. Please try again.";
                    $show_reset_form = true;
                    $reset_email = $email;
                }
            }
        } else {
            $forgot_error = "Invalid request or session expired.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login / Register</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
        }

       body.dark {
            --primary: #0f0a15;
            --secondary: #1a1524;
            --accent: #bf32f1;
        
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
        
            --text-main: #e6e0eb;
            --text-light: #e6e0eb;
            --text-muted: rgba(230, 224, 235, 0.6);
        
            --border: rgba(191, 50, 241, 0.15);
            --border-color: rgba(191, 50, 241, 0.4);
        
            --card-bg: rgba(191, 50, 241, 0.1);
            --hover-bg: rgba(191, 50, 241, 0.25);
        
            --glass: rgba(15, 10, 21, 0.7);
            --glass-strong: rgba(142, 35, 193, 0.08);
        
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
        
            --input-bg: #1a0a24;
        }


                body.bright {
            --primary: #f7f3e8;
            --secondary: #fcfaf2;
            --accent: #9b59b6;
        
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
        
            --text-main: #2c2433;
            --text-light: #2c2433;
            --text-muted: rgba(44, 36, 51, 0.7);
        
            --border: rgba(155, 89, 182, 0.2);
            --border-color: rgba(155, 89, 182, 0.3);
        
            --card-bg: rgba(255, 255, 255, 0.4);
            --hover-bg: rgba(155, 89, 182, 0.12);
        
            --glass: rgba(247, 243, 232, 0.8);
            --glass-strong: rgba(255, 255, 255, 0.6);
        
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
        
            --input-bg: rgba(255, 255, 255, 0.8);
        }


        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: var(--text-main);
            overflow: hidden;
            background-attachment: fixed;
            transition: color 0.5s ease;
        }

       body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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

        /* Ambient Background Glows */
        .ambient-glow {
            position: absolute;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(149, 87, 161, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
            filter: blur(60px);
            pointer-events: none;
            animation: float 10s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-30px) scale(1.1);
            }
        }

        .glow-1 {
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .glow-2 {
            bottom: -100px;
            right: -100px;
            animation-delay: -5s;
        }

        .auth-box {
            background: var(--glass);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 420px;
            border: 1px solid var(--border-color);
            position: relative;
            transform-style: preserve-3d;
            animation: fadeIn 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Transition for form switching */
        #loginForm,
        #registerForm,
        #forgotForm {
            animation: formAppear 0.4s ease-out;
        }

        @keyframes formAppear {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .auth-box h2 {
            color: var(--text-main);
            margin-bottom: 30px;
            font-size: 26px;
            font-family: var(--orbitron);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-align: center;
            text-shadow: var(--glow);
        }

        .auth-box input {
            width: 100%;
            padding: 16px;
            margin-bottom: 20px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-main);
            outline: none;
            font-size: 15px;
            box-sizing: border-box;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-box input:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .auth-box button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-family: var(--orbitron);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: var(--glow);
        }

        .auth-box button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 20px rgba(191, 50, 241, 0.3);
            filter: brightness(1.1);
        }

        .auth-box button:active {
            transform: scale(0.98);
        }

        .toggle-container {
            margin-top: 25px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }

        .toggle {
            color: #22272b;
            cursor: pointer;
            font-size: 14px;
            transition: color 0.3s;
            text-decoration: none;
        }

        .toggle:hover {
            color: #620ec2;
            text-decoration: underline;
        }

        .error {
            background: rgba(193, 76, 76, 0.15);
            color: #ff9d9d;
            padding: 12px;
            border-radius: 2px;
            margin-bottom: 20px;
            border: 1px solid rgba(193, 76, 76, 0.3);
            font-size: 14px;
            text-align: center;
        }

        .success {
            background: rgba(76, 175, 80, 0.15);
            color: #a5d6a7;
            padding: 12px;
            border-radius: 2px;
            margin-bottom: 20px;
            border: 1px solid rgba(76, 175, 80, 0.3);
            font-size: 14px;
            text-align: center;
        }

        .home-link {
            display: block;
            margin-top: 30px;
            color: #22272b;
            text-align: center;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.3s;
        }

        .home-link:hover {
            color: #620ec2;
        }

        /* Specialized input for code */
        .code-input {
            text-align: center;
            font-size: 24px !important;
            letter-spacing: 8px;
            font-weight: bold;
            color: #66c0f4 !important;
        }
    </style>
</head>

<script>

// --- SYNC WITH MAIN MENU THEME ---
document.addEventListener("DOMContentLoaded", () => {
    const body = document.body;

    // Read the theme saved by the main menu
    const savedTheme = localStorage.getItem("theme") || "dark";

    // Apply it
    body.classList.remove("dark", "bright");
    body.classList.add(savedTheme);
});

    
</script>
<div class="auth-box">
    <!-- LOGIN FORM -->
    <div id="loginForm" <?php if (isset($_POST['register_submit']) || $show_reset_form || $forgot_error || $forgot_success)
        echo 'style="display:none"'; ?>>
        <h2>Sign In</h2>
        <?php if ($login_error)
            echo "<div class='error'>$login_error</div>"; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="USERNAME" required autofocus>
            <input type="password" name="password" placeholder="PASSWORD" required>

            <label
                style="display:flex;align-items:center;gap:10px;margin:15px 0 20px 0; font-size: 14px; color: #22272b; cursor: pointer;">
                <input type="checkbox" name="remember" value="1"
                    style="width:18px; height:18px; margin:0; cursor: pointer;">
                Remember me on this computer
            </label>

            <button type="submit" name="login_submit">Login</button>
        </form>

        <div class="toggle-container">
            <a class="toggle" onclick="toggleForms('register')">Create a new account...</a>
            <a class="toggle" onclick="toggleForms('forgot')">Forgot your password?</a>
        </div>
    </div>
    
    
    
    
    
            


    <!-- REGISTER FORM -->
    <div id="registerForm" style="display:none">
        <h2>Create Account</h2>
        <?php if ($register_error)
            echo "<div class='error'>$register_error</div>"; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="CHOOSE A USERNAME" required>
            <input type="email" name="email" placeholder="EMAIL ADDRESS" required>
            <input type="password" name="password" placeholder="CHOOSE A PASSWORD" required>

            <button type="submit" name="register_submit">Register</button>
        </form>

        <div class="toggle-container">
            <a class="toggle" onclick="toggleForms('login')">Already have an account? Sign In</a>
        </div>
    </div>

    <!-- FORGOT PASSWORD FORM -->
    <div id="forgotForm" <?php if (!$forgot_error && !$forgot_success && !isset($_POST['forgot_submit']) && !isset($_POST['reset_submit']))
        echo 'style="display:none"'; ?>>
        <h2>Account Recovery</h2>
        <?php if ($forgot_error)
            echo "<div class='error'>$forgot_error</div>"; ?>
        <?php if ($forgot_success)
            echo "<div class='success'>$forgot_success</div>"; ?>

        <?php if (!$show_reset_form): ?>
            <?php if (strpos($forgot_success, 'updated') === false): ?>
                <p style="font-size: 14px; color: #8f98a0; margin-bottom: 20px; text-align: center;">Enter your email
                    address to receive a verification code.</p>
                <form method="POST">
                    <input type="email" name="email" placeholder="EMAIL ADDRESS" required>
                    <button type="submit" name="forgot_submit">Send Code</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p style="font-size: 14px; color: #8f98a0; margin-bottom: 20px; text-align: center;">Enter the 6-digit
                verification code sent to your email.</p>
            <form method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($reset_email); ?>">
                <input type="text" name="code" placeholder="------" class="code-input" maxlength="6" required autofocus>
                <input type="password" name="password" placeholder="NEW PASSWORD" required>
                <input type="password" name="confirm_password" placeholder="CONFIRM NEW PASSWORD" required>
                <button type="submit" name="reset_submit">Reset Password</button>
            </form>
        <?php endif; ?>

        <div class="toggle-container">
            <a class="toggle" onclick="toggleForms('login')">Back to Sign In</a>
        </div>
    </div>

    <a href="index.php" class="home-link">← Cancel and return to home</a>
</div>

<script>
    function toggleForms(formName) {
        const login = document.getElementById('loginForm');
        const register = document.getElementById('registerForm');
        const forgot = document.getElementById('forgotForm');

        login.style.display = 'none';
        register.style.display = 'none';
        forgot.style.display = 'none';

        if (formName === 'register') {
            register.style.display = 'block';
        } else if (formName === 'forgot') {
            forgot.style.display = 'block';
        } else {
            login.style.display = 'block';
        }
    }

    // Logic to show registration if there was a registration error
    <?php if (isset($_POST['register_submit']) && $register_error): ?>
        toggleForms('register');
    <?php endif; ?>
</script>
</body>

</html>