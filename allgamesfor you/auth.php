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
    die("Connection failed:" . $conn->connect_error);

// ---- DATABASE MIGRATIONS (Ensure columns exist) ----
$check_token = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token_hash'");
if ($check_token && $check_token->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN reset_token_hash VARCHAR(255) DEFAULT NULL");
}
$check_expiry = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token_expires_at'");
if ($check_expiry && $check_expiry->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN reset_token_expires_at DATETIME DEFAULT NULL");
}

$login_error = '';
$register_error = '';
$redirect = $_GET['redirect'] ?? 'index.php';
// ---- LOGOUT ----
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET token_selector=NULL,token_validator=NULL WHERE user_id=?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    session_unset();
    session_destroy();
    // Remove rememberme cookie (secure=true). If testing on HTTP,set last param to false.
    setcookie("rememberme", "", time() - 3600, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
    header("Location:auth.php");
    exit();
}
// ---- AUTO LOGIN USING REMEMBER-ME ----
if (!isset($_SESSION['user_id']) && isset($_COOKIE['rememberme'])) {
    if (strpos($_COOKIE['rememberme'], ":") !== false) {
        list($selector, $token) = explode(":", $_COOKIE['rememberme']);
        $stmt = $conn->prepare("SELECT user_id,username,role,profile_img,token_validator FROM users WHERE token_selector=? LIMIT 1");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (!empty($row['token_validator']) && password_verify($token, $row['token_validator'])) {
                if ($row['role'] !== 'unverified') {
                    // login
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';
                    // refresh token (rotate)
                    $new_selector = bin2hex(random_bytes(9));
                    $new_token = bin2hex(random_bytes(33));
                    $new_validator = password_hash($new_token, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare("UPDATE users SET token_selector=?,token_validator=? WHERE user_id=?");
                    $stmt2->bind_param("ssi", $new_selector, $new_validator, $row['user_id']);
                    $stmt2->execute();
                    setcookie("rememberme", $new_selector . ":" . $new_token, time() + 86400 * 30, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
                } else {
                    // if unverified, remove the rememberme cookie
                    setcookie("rememberme", "", time() - 3600, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
                }
            }
        }
    }
}// ---- LOGIN ----
if (isset($_POST['login_submit'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $stmt = $conn->prepare("SELECT user_id,username,password_hash,role,profile_img FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password_hash'])) {
            if ($row['role'] === 'unverified') {
                $login_error = "Your email is not verified. Please check your inbox for the code.";
                $show_verify_form = true;
                $verify_email = $user; // We'll use username or email to verify
                // find the email for this user to display in the verify form
                $stmt_email = $conn->prepare("SELECT email FROM users WHERE username=? LIMIT 1");
                $stmt_email->bind_param("s", $user);
                $stmt_email->execute();
                $res_email = $stmt_email->get_result();
                if ($res_email && $r_email = $res_email->fetch_assoc()) {
                    $verify_email_addr = $r_email['email'];
                }
            } else {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['profile_img'] = $row['profile_img'] ?? '/imgandgifs/login.png';
                if (isset($_POST['remember'])) {
                    $selector = bin2hex(random_bytes(9));
                    $token = bin2hex(random_bytes(33));
                    $validator = password_hash($token, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare("UPDATE users SET token_selector=?,token_validator=? WHERE user_id=?");
                    $stmt2->bind_param("ssi", $selector, $validator, $row['user_id']);
                    $stmt2->execute();
                    setcookie("rememberme", $selector . ":" . $token, time() + 86400 * 30, "/", "", !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
                }
                header("Location:$redirect");
                exit();
            }
        } else {
            $login_error = "Incorrect password.";
        }
    } else {
        $login_error = "User not found.";
    }
}// ---- REGISTER ----
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
        $role = 'unverified';
        $code = sprintf("%06d", mt_rand(1, 999999));
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 60 * 24); // 24 hours to verify

        $stmt = $conn->prepare("INSERT INTO users (username,email,password_hash,role,reset_token_hash,reset_token_expires_at) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssss", $user, $email, $hash, $role, $code_hash, $expiry);
        if ($stmt->execute()) {
            // Send Verification Email
            $subject = "Welcome to AllGamesForYou - Verify Your Email";
            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
            </head>
            <body style="margin: 0; padding: 0; background-color: #12141a; font-family: Arial, Helvetica, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #12141a; padding: 40px 0;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #171a21; text-align: left; border-radius: 4px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 30px; background-color: #171a21;">
                                        <img src="https://anty-gaming.com/imgandgifs/logo.png" alt="Logo" style="height: 40px; vertical-align: middle;">
                                        <span style="color: #ffffff; font-size: 22px; font-weight: bold; vertical-align: middle; margin-left: 10px; letter-spacing: 1px;">ALLGAMESFORYOU</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="height: 3px; background: #2781cf; background: linear-gradient(to right, #2781cf, #171a21);"></td>
                                </tr>
                                <tr>
                                    <td style="padding: 30px 40px; color: #acb2b8; line-height: 1.6; font-size: 14px;">
                                        <div style="font-size: 18px; color: #ffffff; margin-bottom: 20px;">Welcome, ' . htmlspecialchars($user) . '!</div>
                                        <div style="margin-bottom: 30px;">Thank you for joining our community. To activate your account and start gaming, please use the verification code below:</div>
                                        <div style="font-size: 36px; font-weight: bold; color: #66c0f4; text-align: center; letter-spacing: 5px; margin: 30px 0;">' . $code . '</div>
                                        <div style="font-size: 12px; color: #8f98a0; margin-top: 30px;">If you did not create an account, you can safely ignore this email.</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 20px 40px; background-color: #12141a; font-size: 11px; color: #61686d; border-top: 1px solid #2a475e;">
                                        &copy; ' . date('Y') . ' AllGamesForYou Corporation. All rights reserved.<br>
                                        This is an automated message. Please do not reply.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: AllGamesForYou <noreply@anty-gaming.com>\r\n";
            @mail($email, $subject, $message, $headers);

            $register_success = "Verification code sent to your email!";
            $show_verify_form = true;
            $verify_email_addr = $email;
            $verify_username = $user;
        } else {
            $register_error = "Registration failed: " . $conn->error;
        }
    }
}// ---- FORGOT PASSWORD ----
$forgot_error = '';
$forgot_success = '';
$show_reset_form = false;
$reset_email = '';
// Step 1:Request Code
if (isset($_POST['forgot_submit'])) {
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT user_id,username FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $username = $row['username'];
        // Generate 6-digit numeric code (Steam-style)
        $code = sprintf("%06d", mt_rand(1, 999999));
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 15);
        // 15 minutes

        $stmt = $conn->prepare("UPDATE users SET reset_token_hash=?,reset_token_expires_at=? WHERE user_id=?");
        $stmt->bind_param("ssi", $code_hash, $expiry, $row['user_id']);
        if ($stmt->execute()) {
            $subject = "AllGamesForYou Account Recovery - Verification Code";
            // Professional AllGamesForYou HTML Email Template
            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
            </head>
            <body style="margin: 0; padding: 0; background-color: #12141a; font-family: Arial, Helvetica, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #12141a; padding: 40px 0;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #171a21; text-align: left; border-radius: 4px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 30px; background-color: #171a21;">
                                        <img src="https://anty-gaming.com/imgandgifs/logo.png" alt="Logo" style="height: 40px; vertical-align: middle;">
                                        <span style="color: #ffffff; font-size: 22px; font-weight: bold; vertical-align: middle; margin-left: 10px; letter-spacing: 1px;">ALLGAMESFORYOU</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="height: 3px; background: #2781cf; background: linear-gradient(to right, #2781cf, #171a21);"></td>
                                </tr>
                                <tr>
                                    <td style="padding: 30px 40px; color: #acb2b8; line-height: 1.6; font-size: 14px;">
                                        <div style="font-size: 18px; color: #ffffff; margin-bottom: 20px;">Dear ' . htmlspecialchars((string)($username ?? '')) . ',</div>
                                        <div style="margin-bottom: 30px;">We received a request to access your account. To complete the recovery process, please enter the following verification code:</div>
                                        <div style="font-size: 36px; font-weight: bold; color: #66c0f4; text-align: center; letter-spacing: 5px; margin: 30px 0;">' . $code . '</div>
                                        <div style="font-size: 12px; color: #8f98a0; margin-top: 30px;">This code will expire in 15 minutes. If you did not request this, your account is still secure. No further action is required.</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 20px 40px; background-color: #12141a; font-size: 11px; color: #61686d; border-top: 1px solid #2a475e;">
                                        &copy; ' . date('Y') . ' AllGamesForYou Corporation. All rights reserved.<br>
                                        This is an automated message. Please do not reply.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $headers = "MIME-Version:1.0" . "\r\n";
            $headers .= "Content-type:text/html;
  charset=UTF-8" . "\r\n";
            $headers .= "From:AllGamesForYou Support <noreply@anty-gaming.com>" . "\r\n";
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
}// Step 2:Verify Code & Reset Password
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
        $stmt = $conn->prepare("SELECT user_id,reset_token_hash,reset_token_expires_at FROM users WHERE email=? LIMIT 1");
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
                $stmt = $conn->prepare("UPDATE users SET password_hash=?,reset_token_hash=NULL,reset_token_expires_at=NULL WHERE user_id=?");
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
// ---- RESEND VERIFICATION CODE ----
$verify_success = '';
if (isset($_POST['resend_code_submit'])) {
    $email = trim($_POST['resend_email']);
    $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email=? AND role='unverified' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $user = $row['username'];
        $code = sprintf("%06d", mt_rand(1, 999999));
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 60 * 24); // 24 hours to verify
        
        $stmt2 = $conn->prepare("UPDATE users SET reset_token_hash=?, reset_token_expires_at=? WHERE user_id=?");
        $stmt2->bind_param("ssi", $code_hash, $expiry, $row['user_id']);
        if ($stmt2->execute()) {
            // Send Verification Email
            $subject = "Welcome to AllGamesForYou - Verify Your Email";
            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
            </head>
            <body style="margin: 0; padding: 0; background-color: #12141a; font-family: Arial, Helvetica, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #12141a; padding: 40px 0;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #171a21; text-align: left; border-radius: 4px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 30px; background-color: #171a21;">
                                        <img src="https://anty-gaming.com/imgandgifs/logo.png" alt="Logo" style="height: 40px; vertical-align: middle;">
                                        <span style="color: #ffffff; font-size: 22px; font-weight: bold; vertical-align: middle; margin-left: 10px; letter-spacing: 1px;">ALLGAMESFORYOU</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="height: 3px; background: #2781cf; background: linear-gradient(to right, #2781cf, #171a21);"></td>
                                </tr>
                                <tr>
                                    <td style="padding: 30px 40px; color: #acb2b8; line-height: 1.6; font-size: 14px;">
                                        <div style="font-size: 18px; color: #ffffff; margin-bottom: 20px;">Welcome, ' . htmlspecialchars($user) . '!</div>
                                        <div style="margin-bottom: 30px;">To activate your account and start gaming, please use the new verification code below:</div>
                                        <div style="font-size: 36px; font-weight: bold; color: #66c0f4; text-align: center; letter-spacing: 5px; margin: 30px 0;">' . $code . '</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 20px 40px; background-color: #12141a; font-size: 11px; color: #61686d; border-top: 1px solid #2a475e;">
                                        &copy; ' . date('Y') . ' AllGamesForYou Corporation. All rights reserved.<br>
                                        This is an automated message. Please do not reply.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: AllGamesForYou <noreply@anty-gaming.com>\r\n";
            @mail($email, $subject, $message, $headers);

            $verify_success = "A new verification code has been sent to your email.";
            $show_verify_form = true;
            $verify_email_addr = $email;
            $verify_username = $user;
        } else {
            $verify_error = "Failed to update verification code.";
            $show_verify_form = true;
            $verify_email_addr = $email;
            $verify_username = $user;
        }
    } else {
        $verify_error = "Account already verified or not found.";
        $show_verify_form = true;
        $verify_email_addr = $email;
    }
}

// ---- EMAIL VERIFICATION ----
$verify_error = $verify_error ?? '';
if (isset($_POST['verify_submit'])) {
    $user_cred = trim($_POST['user_cred']);
    $code = trim($_POST['code']);
    $stmt = $conn->prepare("SELECT user_id,username,email,role,reset_token_hash,reset_token_expires_at FROM users WHERE username=? OR email=? LIMIT 1");
    $stmt->bind_param("ss", $user_cred, $user_cred);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if ($row['role'] !== 'unverified') {
            $verify_error = "Account is already verified.";
        } elseif (strtotime($row['reset_token_expires_at']) <= time()) {
            $verify_error = "Verification code expired. Please request a new one.";
        } elseif (!password_verify($code, $row['reset_token_hash'])) {
            $verify_error = "Invalid verification code.";
            $show_verify_form = true;
            $verify_email_addr = $row['email'];
            $verify_username = $row['username'];
        } else {
            // Success!
            $stmt = $conn->prepare("UPDATE users SET role='user', reset_token_hash=NULL, reset_token_expires_at=NULL WHERE user_id=?");
            $stmt->bind_param("i", $row['user_id']);
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = 'user';
                $_SESSION['profile_img'] = '/imgandgifs/login.png';
                header("Location:$redirect");
                exit();
            } else {
                $verify_error = "Database error during verification.";
            }
        }
    } else {
        $verify_error = "User not found.";
    }
} ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login / Register</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;
  400;
  600&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191, 50, 241, 0.4);
            --glass: rgba(15, 10, 21, 0.7);
            --glass-strong: rgba(142, 35, 193, 0.08);
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: #1a0a24;

            /* Auth specific legacy */
            --text-muted: rgba(230, 224, 235, 0.6);
            --border: rgba(191, 50, 241, 0.15);
            --card-bg: rgba(191, 50, 241, 0.1);
            --hover-bg: rgba(191, 50, 241, 0.25);
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.3);
            --glass: rgba(247, 243, 232, 0.8);
            --glass-strong: rgba(255, 255, 255, 0.6);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --input-bg: rgba(255, 255, 255, 0.8);

            /* Auth specific legacy */
            --primary: #f7f3e8;
            --secondary: #fcfaf2;
            --accent: #9b59b6;
            --text-light: #2c2433;
            --text-muted: rgba(44, 36, 51, 0.7);
            --border: rgba(155, 89, 182, 0.2);
            --card-bg: rgba(255, 255, 255, 0.4);
            --hover-bg: rgba(155, 89, 182, 0.12);
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
            background: radial-gradient(circle at 0% 0%, var(--bg-mesh-2) 0%, transparent 50%), radial-gradient(circle at 100% 0%, var(--bg-mesh-3) 0%, transparent 50%), radial-gradient(circle at 100% 100%, var(--bg-mesh-2) 0%, transparent 50%), radial-gradient(circle at 0% 100%, var(--bg-mesh-3) 0%, transparent 50%), var(--bg-mesh-1);
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
            /*color:#22272b;
  */
            cursor: pointer;
            font-size: 14px;
            transition: color 0.3s;
            text-decoration: none;
        }

        .toggle:hover {
            color: #e5aafa;
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
            /*color:#22272b;
  */
            text-align: center;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.3s;
        }

        .home-link:hover {
            color: #e5aafa;
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
    }
    );
</script>
<div class="auth-box">
    <!-- LOGIN FORM -->
    <div id="loginForm" <?php if (isset($_POST['register_submit']) || $show_reset_form || (isset($show_verify_form) && $show_verify_form) || $forgot_error || $forgot_success)
        echo 'style="display:none"';
    ?>>
        <h2>Sign In</h2>
        <?php if ($login_error)
            echo "<div class='error'>$login_error</div>";
        ?>
        <?php if (isset($register_success))
            echo "<div class='success'>$register_success</div>";
        ?>

        <form method="POST">
            <input type="text" name="username" placeholder="USERNAME" required autofocus>
            <input type="password" name="password" placeholder="PASSWORD" required>

            <label style="display:flex;
  align-items:center;
  gap:10px;
  margin:15px 0 20px 0;
  font-size:14px;
  cursor:pointer;
  ">
                <input type="checkbox" name="remember" value="1" style="width:18px;
  height:18px;
  margin:0;
  cursor:pointer;
  ">
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
            echo "<div class='error'>$register_error</div>";
        ?>

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
        echo 'style="display:none"';
    ?>>
        <h2>Account Recovery</h2>
        <?php if ($forgot_error)
            echo "<div class='error'>$forgot_error</div>";
        ?>
        <?php if ($forgot_success)
            echo "<div class='success'>$forgot_success</div>";
        ?>

        <?php if (!$show_reset_form): ?>
            <?php if (strpos($forgot_success, 'updated') === false): ?>
                <p style="font-size:14px;
  color:#8f98a0;
  margin-bottom:20px;
  text-align:center;
  ">Enter your email
                    address to receive a verification code.</p>
                <form method="POST">
                    <input type="email" name="email" placeholder="EMAIL ADDRESS" required>
                    <button type="submit" name="forgot_submit">Send Code</button>
                </form>
            <?php endif;
            ?>
        <?php else: ?>
            <p style="font-size:14px;
  color:#8f98a0;
  margin-bottom:20px;
  text-align:center;
  ">Enter the 6-digit
                verification code sent to your email.</p>
            <form method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars((string) ($reset_email ?? ''));
                ?>">
                <input type="text" name="code" placeholder="------" class="code-input" maxlength="6" required autofocus>
                <input type="password" name="password" placeholder="NEW PASSWORD" required>
                <input type="password" name="confirm_password" placeholder="CONFIRM NEW PASSWORD" required>
                <button type="submit" name="reset_submit">Reset Password</button>
            </form>
        <?php endif;
        ?>

        <div class="toggle-container">
            <a class="toggle" onclick="toggleForms('login')">Back to Sign In</a>
        </div>
    </div>

    <!-- VERIFY EMAIL FORM -->
    <div id="verifyForm" <?php if (!isset($show_verify_form) || !$show_verify_form)
        echo 'style="display:none"'; ?>>
        <h2>Account Verification</h2>
        <?php if ($verify_error)
            echo "<div class='error'>$verify_error</div>"; ?>
        <?php if (!empty($verify_success))
            echo "<div class='success'>$verify_success</div>"; ?>
        <p style="font-size:14px; color:var(--text-muted); margin-bottom:20px; text-align:center;">
            Enter the 6-digit code sent to:<br>
            <strong
                style="color:var(--accent);"><?php echo htmlspecialchars((string) ($verify_email_addr ?? '')); ?></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="user_cred"
                value="<?php echo htmlspecialchars((string) ($verify_username ?? $verify_email ?? '')); ?>">
            <input type="text" name="code" placeholder="------" class="code-input" maxlength="6" required autofocus>
            <button type="submit" name="verify_submit">Verify & Start Gaming</button>
        </form>
        <form method="POST" style="margin-top: 10px;">
            <input type="hidden" name="resend_email" value="<?php echo htmlspecialchars((string) ($verify_email_addr ?? '')); ?>">
            <button type="submit" name="resend_code_submit" style="background: transparent; border: 1px solid var(--accent); color: var(--text-main); font-size: 14px; padding: 10px; margin-top: 5px;">Resend Code</button>
        </form>
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
        const verify = document.getElementById('verifyForm');
        login.style.display = 'none';
        register.style.display = 'none';
        forgot.style.display = 'none';
        if (verify) verify.style.display = 'none';

        if (formName === 'register') {
            register.style.display = 'block';
        }
        else if (formName === 'forgot') {
            forgot.style.display = 'block';
        }
        else if (formName === 'verify') {
            if (verify) verify.style.display = 'block';
        }
        else {
            login.style.display = 'block';
        }
    }

    // --- THEME TOGGLE LOGIC ---
    const themeToggleBtn = document.getElementById('themeToggle');
    const body = document.body;

    function updateThemeUI() {
        const isDark = body.classList.contains('dark');
        const img = themeToggleBtn ? themeToggleBtn.querySelector('img') : null;
        if (img) {
            img.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";
            img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.1)';
            img.style.filter = isDark ? 'drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))' : 'drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))';
        }
    }

    function initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        body.classList.remove('dark', 'bright');
        body.classList.add(savedTheme);
        updateThemeUI();
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const currentTheme = body.classList.contains('dark') ? 'dark' : 'bright';
            const newTheme = currentTheme === 'dark' ? 'bright' : 'dark';
            body.classList.remove('dark', 'bright');
            body.classList.add(newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeUI();
        });
    }
    initTheme();

    // Logic to show registration if there was a registration error
    <?php if (isset($_POST['register_submit']) && $register_error): ?>
        toggleForms('register');
    <?php endif;
    ?>
</script>
</body>

</html>