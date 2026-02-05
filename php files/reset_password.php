<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$show_form = false;

if (!$token) {
    die("Invalid request. No token provided.");
}

$token_hash = hash('sha256', $token);

// Validate Token
$stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token_hash=? AND reset_token_expires_at > NOW() LIMIT 1");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $show_form = true;
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
} else {
    $error = "Invalid or expired token.";
}

// Handle Password Reset
if (isset($_POST['reset_submit']) && $show_form) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $new_hash = password_hash($pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password_hash=?, reset_token_hash=NULL, reset_token_expires_at=NULL WHERE user_id=?");
        $stmt->bind_param("si", $new_hash, $user_id);

        if ($stmt->execute()) {
            $success = "Password successfully updated. <a href='Auth.php' style='color:#ffd740'>Login here</a>";
            $show_form = false;
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: linear-gradient(135deg, #1a0b2e, #4a0072, #7b1fa2);
            background-size: 200% 200%;
            animation: gradientBG 15s ease infinite;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #fff;
            padding: 20px;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .auth-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateY(20px);
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-box h2 {
            color: #fff;
            margin-bottom: 25px;
            font-weight: 600;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .auth-box input {
            width: 100%;
            padding: 14px 16px;
            margin: 10px 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            outline: none;
            font-size: 1rem;
            color: #fff;
            box-sizing: border-box;
            transition: all 0.3s;
        }

        .auth-box input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .auth-box input:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: #ffd740;
            box-shadow: 0 0 10px rgba(255, 215, 64, 0.3);
        }

        .auth-box button {
            width: 100%;
            padding: 14px;
            margin-top: 20px;
            background: linear-gradient(45deg, #db8e1d, #ffb300);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(219, 142, 29, 0.4);
        }

        .auth-box button:hover {
            background: linear-gradient(45deg, #e69a2d, #ffca28);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(219, 142, 29, 0.6);
        }

        .error {
            background: rgba(255, 82, 82, 0.2);
            color: #ff8a80;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 82, 82, 0.3);
            font-size: 0.9em;
        }

        .success {
            background: rgba(76, 175, 80, 0.2);
            color: #b9f6ca;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid rgba(76, 175, 80, 0.3);
            font-size: 0.9em;
        }

        a.back-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            margin-top: 20px;
            display: inline-block;
            font-size: 0.9em;
            transition: 0.2s;
        }

        a.back-link:hover {
            color: #ffd740;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="auth-box">
        <h2>Set New Password</h2>

        <?php if ($error) echo "<div class='error'>$error</div>"; ?>
        <?php if ($success) echo "<div class='success'>$success</div>"; ?>

        <?php if ($show_form): ?>
            <form method="POST">
                <input type="password" name="password" placeholder="New Password" required minlength="6">
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required minlength="6">
                <button type="submit" name="reset_submit">Update Password</button>
            </form>
        <?php endif; ?>

        <?php if (!$show_form): ?>
            <a href="Auth.php" class="back-link">← Back to Login</a>
        <?php endif; ?>
    </div>

</body>
</html>