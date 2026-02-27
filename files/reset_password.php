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
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: #1a0a24;
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.3);
            --glass: rgba(247, 243, 232, 0.85);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --input-bg: rgba(255, 255, 255, 0.8);
        }

        body {
            font-family: "Poppins", sans-serif;
            background: var(--bg-mesh-1);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: var(--text-main);
            padding: 20px;
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
            background: radial-gradient(circle at 0% 0%, var(--bg-mesh-2) 0%, transparent 50%),
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

        .auth-box {
            background: var(--glass);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border: 1px solid var(--border-color);
            transform: translateY(20px);
            opacity: 0;
            animation: fadeInUp 0.8s cubic-bezier(0.19, 1, 0.22, 1) forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-box h2 {
            color: var(--text-main);
            margin-bottom: 30px;
            font-family: var(--orbitron);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: var(--glow);
        }

        .auth-box input {
            width: 100%;
            padding: 16px;
            margin: 12px 0;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            outline: none;
            font-size: 1rem;
            color: var(--text-main);
            box-sizing: border-box;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-box input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .auth-box input:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .auth-box button {
            width: 100%;
            padding: 16px;
            margin-top: 25px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-family: var(--orbitron);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--glow);
        }

        .auth-box button:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 25px rgba(191, 50, 241, 0.4);
            filter: brightness(1.1);
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

    <script>
        const bodyValue = document.body;
        const savedTheme = localStorage.getItem("theme") || "dark";
        bodyValue.classList.add(savedTheme);
    </script>
    <div class="auth-box">
        <h2>Set New Password</h2>

        <?php if ($error)
            echo "<div class='error'>$error</div>"; ?>
        <?php if ($success)
            echo "<div class='success'>$success</div>"; ?>

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