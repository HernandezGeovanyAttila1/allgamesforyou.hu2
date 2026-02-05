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

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        $code = rand(100000, 999999);
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 15); // 15 minutes

        $stmt = $conn->prepare("UPDATE users SET reset_token_hash=?, reset_token_expires_at=? WHERE user_id=?");
        $stmt->bind_param("ssi", $code_hash, $expiry, $row['user_id']);

        if ($stmt->execute()) {
            $subject = "Your Password Reset Code";

            // Steam-like HTML Email Template
            $message = '
            <html>
            <body style="background-color: #171a21; font-family: Arial, sans-serif; color: #c7d5e0; padding: 20px; margin: 0;">
                <div style="max-width: 500px; margin: 0 auto; background-color: #1b2838; padding: 30px; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                    <h2 style="color: #66c0f4; text-align: center; border-bottom: 2px solid #2a475e; padding-bottom: 15px; margin-top: 0; font-size: 24px;">
                        Account Recovery
                    </h2>
                    <p style="font-size: 16px; margin-top: 20px;">
                        Hello,
                    </p>
                    <p style="font-size: 16px;">
                        We received a request to reset your password. Here is the protection code you need:
                    </p>
                    <div style="background-color: #16202d; padding: 20px; margin: 30px 0; text-align: center; border-radius: 5px; border: 1px solid #2a475e;">
                        <span style="font-size: 38px; font-weight: bold; color: #ffffff; letter-spacing: 8px; font-family: monospace;">
                            ' . $code . '
                        </span>
                    </div>
                    <p style="font-size: 14px; color: #8f98a0; text-align: center;">
                        This code expires in 15 minutes.
                    </p>
                    <div style="border-top: 1px solid #2a475e; margin-top: 30px; padding-top: 15px; font-size: 12px; color: #6d7780; text-align: center;">
                        If you did not request this email, please ignore it. Your account is safe.
                    </div>
                </div>
            </body>
            </html>
            ';

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: no-reply@anty.com" . "\r\n";

            if (mail($email, $subject, $message, $headers)) {
                $forgot_success = "Reset code sent to your email.";
                $show_reset_form = true;
                $reset_email = $email;
            } else {
                $forgot_error = "Failed to send email.";
            }
        } else {
            $forgot_error = "Database error. Please try again.";
        }
    } else {
        $forgot_error = "No account found with that email.";
    }
}

// Step 2: Verify Code & Reset Password
if (isset($_POST['reset_submit'])) {
    $email = trim($_POST['email']);
    $code = $_POST['code'];
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
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
            if (strtotime($row['reset_token_expires_at']) > time()) {
                if (password_verify($code, $row['reset_token_hash'])) {
                    $new_hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash=?, reset_token_hash=NULL, reset_token_expires_at=NULL WHERE user_id=?");
                    $stmt->bind_param("si", $new_hash, $row['user_id']);
                    if ($stmt->execute()) {
                        $forgot_success = "Password updated successfully. You can now login.";
                        $show_reset_form = false;
                    } else {
                        $forgot_error = "Failed to update password.";
                        $show_reset_form = true;
                        $reset_email = $email;
                    }
                } else {
                    $forgot_error = "Invalid code.";
                    $show_reset_form = true;
                    $reset_email = $email;
                }
            } else {
                $forgot_error = "Code expired.";
            }
        } else {
            $forgot_error = "Invalid request.";
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
            background-image: url("/imgandgifs/new_bg_darker.png");
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed;
        }

        .auth-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-sizing: border-box;
        }

        .auth-box h2 {
            color: #fff;
            font-size: 1.5em;
        }

        .auth-box input {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: none;
            border-radius: 8px;
            outline: none;
            font-size: 1em;
            box-sizing: border-box;
        }

        .auth-box button {
            width: 100%;
            padding: 12px;
            background: #db8e1d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: 0.3s;
        }

        .auth-box button:hover {
            background: #fe9d2a;
        }

        .toggle {
            color: #ffd740;
            cursor: pointer;
            text-decoration: underline;
            margin-top: 10px;
        }

        .error {
            color: #ff5252;
        }

        .home-link {
            color: #fff;
            text-decoration: underline;
            margin-top: 10px;
            display: block;
        }
    </style>
</head>

<body>

    <div class="auth-box">

        <!-- LOGIN FORM -->
        <div id="loginForm" <?php if (isset($_POST['register_submit']))
            echo 'style="display:none"'; ?>>
            <h2>Login</h2>
            <?php if ($login_error)
                echo "<p class='error'>$login_error</p>"; ?>

            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>

                <label style="display:flex;align-items:center;gap:6px;margin:10px 0;">
                    <input type="checkbox" name="remember" value="1" style="width:auto;">
                    Remember me
                </label>

                <button type="submit" name="login_submit">Login</button>
            </form>

            <p class="toggle" onclick="toggleForms('register')">Don't have an account? Register</p>
            <p class="toggle" onclick="toggleForms('forgot')">Forgot Password?</p>
        </div>

        <!-- REGISTER FORM -->
        <div id="registerForm" <?php if (!isset($_POST['register_submit']))
            echo 'style="display:none"'; ?>>
            <h2>Register</h2>
            <?php if ($register_error)
                echo "<p class='error'>$register_error</p>"; ?>

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

            <p class="toggle" onclick="toggleForms('login')">Already have an account? Login</p>
        </div>

        <!-- FORGOT PASSWORD FORM -->
        <div id="forgotForm" <?php if (!isset($_POST['forgot_submit']) && !isset($_POST['reset_submit']))
            echo 'style="display:none"'; ?>>
            <h2>Reset Password</h2>
            <?php if ($forgot_error)
                echo "<p class='error'>$forgot_error</p>"; ?>
            <?php if ($forgot_success)
                echo "<p style='color:#4caf50'>$forgot_success</p>"; ?>

            <?php if (!$show_reset_form): ?>
                <!-- Step 1: Email Form -->
                <?php if (strpos($forgot_success, 'updated') === false): ?>
                    <form method="POST">
                        <input type="email" name="email" placeholder="Enter your email" required>
                        <button type="submit" name="forgot_submit">Send Code</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <!-- Step 2: Code & New Password Form -->
                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($reset_email); ?>">
                    <input type="text" name="code" placeholder="Enter 6-digit code" required>
                    <input type="password" name="password" placeholder="New Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="submit" name="reset_submit">Change Password</button>
                </form>
            <?php endif; ?>

            <p class="toggle" onclick="toggleForms('login')">Back to Login</p>
        </div>

        <a href="index.php" class="home-link">← Back to Home</a>
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
    </script>

</body>

</html>