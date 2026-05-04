<?php
session_start();
require_once 'db.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_report'])) {
    $headline = trim($_POST['headline'] ?? '');
    $report = trim($_POST['report'] ?? '');
    
    if (!empty($headline) && !empty($report)) {
        $stmt = $conn->prepare("INSERT INTO reports (user_id, headline, report, status) VALUES (?, ?, ?, 'pending')");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $headline, $report);
            if ($stmt->execute()) {
                $_SESSION['support_success'] = "Your support request has been submitted successfully!";
            } else {
                $_SESSION['support_error'] = "Error submitting request: " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $_SESSION['support_error'] = "Please fill in all fields.";
    }
    header("Location: custumersupport.php");
    exit();
}

// Get messages from session
if (isset($_SESSION['support_success'])) {
    $success_msg = $_SESSION['support_success'];
    unset($_SESSION['support_success']);
}
if (isset($_SESSION['support_error'])) {
    $error_msg = $_SESSION['support_error'];
    unset($_SESSION['support_error']);
}

// Fetch user's previous reports AJAX
if (isset($_GET['ajax'])) {
    header("Content-Type: application/json");
    $stmt = $conn->prepare("SELECT headline, report, reply, status, created_at FROM reports WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reports_json = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($reports_json);
    exit;
}

// Fetch user's previous reports Initial Load
$reports = [];
$stmt = $conn->prepare("SELECT headline, report, reply, status, created_at FROM reports WHERE user_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support - The Vault</title>
    <link rel="icon" type="image/x-icon" href="/imgandgifs/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
            --poppins: 'Poppins', sans-serif;
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191, 50, 241, 0.2);
            --border: rgba(191, 50, 241, 0.2);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --input-bg: rgba(255, 255, 255, 0.05);
            --item-bg: rgba(255, 255, 255, 0.03);
            --item-hover: rgba(255, 255, 255, 0.07);
        }

        body.bright {
            --bg-mesh-1: #f2f2f2;
            --bg-mesh-2: #ffffff;
            --bg-mesh-3: #dddddd;
            --text-main: #333333;
            --border-color: rgba(191, 50, 241, 0.2);
            --border: rgba(191, 50, 241, 0.15);
            --glass: rgba(255, 255, 255, 0.85);
            --glass-strong: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --glow: 0 0 15px rgba(191, 50, 241, 0.2);
            --input-bg: rgba(0, 0, 0, 0.05);
            --item-bg: rgba(0, 0, 0, 0.02);
            --item-hover: rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: var(--poppins);
            background: var(--bg-mesh-1);
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-attachment: fixed;
            transition: background 0.5s ease, color 0.3s ease;
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
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            width: 100%;
        }

        .support-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
        }

        h1, h2 {
            font-family: var(--orbitron);
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--accent);
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 15px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: 0.3s;
        }

        input[type="text"]:focus, textarea:focus {
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent), #774280);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: var(--orbitron);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: var(--glow);
        }

        button:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        .report-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .report-item {
            background: var(--item-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            margin-bottom: 25px;
            padding: 25px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            color: var(--text-main);
        }

        .report-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
            opacity: 0.5;
            transition: 0.3s;
        }

        .report-item:hover {
            background: var(--item-hover);
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .report-item:hover::before {
            opacity: 1;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        .report-headline {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
        }

        .report-date {
            font-size: 0.75rem;
            opacity: 0.6;
        }

        .report-content {
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .admin-reply {
            background: var(--item-hover);
            border-left: 4px solid var(--accent);
            padding: 15px;
            border-radius: 0 10px 10px 0;
            margin-top: 15px;
        }

        .reply-label {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 5px;
            display: block;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .theme-toggle-btn {
            background: var(--glass);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            padding: 0;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: var(--glow);
            border-color: var(--accent);
        }

        .theme-toggle-btn img {
            width: 28px;
            height: 28px;
            pointer-events: none;
            transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .status-pending {
            background: rgba(241, 194, 15, 0.2);
            color: #f1c40f;
        }

        .status-answered {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            background: var(--glass);
            padding: 10px 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .back-btn:hover {
            color: var(--accent);
            transform: translateX(-5px);
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        /* Better scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-mesh-1);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #d466f2;
        }
    </style>
</head>
<body class="dark">
    <div class="container">
        <div class="top-bar">
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
            <button id="themeToggle" class="theme-toggle-btn" title="Toggle Theme">
                <img src="imgandgifs/sun.svg" alt="Toggle" id="themeIcon">
            </button>
        </div>
        
        <div class="support-card">
            <h1>Customer Support</h1>
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="headline">Headline</label>
                    <input type="text" id="headline" name="headline" placeholder="Briefly describe your issue..." required>
                </div>
                
                <div class="form-group">
                    <label for="report">How can we help you?</label>
                    <textarea id="report" name="report" rows="5" placeholder="Details of your request..." required></textarea>
                </div>
                
                <button type="submit" name="submit_report">Submit Request</button>
            </form>
        </div>

        <?php if (!empty($reports)): ?>
            <h2>My Previous Requests</h2>
            <div class="report-list">
                <?php foreach ($reports as $r): ?>
                    <div class="report-item">
                        <div class="report-header">
                            <div>
                                <span class="report-headline"><?php echo htmlspecialchars((string)($r['headline'] ?? '')); ?></span>
                                <div class="report-date"><i class="far fa-calendar-alt"></i> <?php echo date('F j, Y, g:i a', strtotime($r['created_at'])); ?></div>
                            </div>
                            <span class="status-badge status-<?php echo $r['status']; ?>">
                                <?php echo $r['status'] === 'answered' ? '<i class="fas fa-check-circle"></i> ' : '<i class="far fa-clock"></i> '; ?>
                                <?php echo ucfirst($r['status']); ?>
                            </span>
                        </div>
                        <div class="report-content">
                            <?php echo nl2br(htmlspecialchars((string)($r['report'] ?? ''))); ?>
                        </div>
                        
                        <?php if ($r['reply']): ?>
                            <div class="admin-reply">
                                <span class="reply-label"><i class="fas fa-user-shield"></i> Admin Response</span>
                                <div class="reply-content">
                                    <?php echo nl2br(htmlspecialchars((string)($r['reply'] ?? ''))); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const bodyValue = document.body;

        function updateThemeUI() {
            const isDark = bodyValue.classList.contains('dark');
            if (themeIcon) {
                themeIcon.src = isDark ? "imgandgifs/moon.svg" : "imgandgifs/sun.svg";
                themeIcon.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.1)';
                themeIcon.style.filter = isDark ? 'drop-shadow(0 0 8px rgba(149, 87, 161, 0.6))' : 'drop-shadow(0 0 8px rgba(255, 157, 0, 0.6))';
            }
        }

        function applyTheme() {
            const t = localStorage.getItem('theme') || 'dark';
            bodyValue.classList.remove('dark', 'bright');
            bodyValue.classList.add(t);
            updateThemeUI();
        }

        themeBtn.addEventListener('click', () => {
            const isDark = bodyValue.classList.contains('dark');
            const newTheme = isDark ? 'bright' : 'dark';
            localStorage.setItem('theme', newTheme);
            applyTheme();
        });

        // Initialize theme early
        applyTheme();

        async function refreshReports() {
            if (document.hidden) return;
            try {
                const res = await fetch("custumersupport.php?ajax=1");
                if (!res.ok) return;
                const reports = await res.json();
                const container = document.querySelector(".report-list");
                if (!container) return;
                
                let html = "";
                reports.forEach(r => {
                    const statusIcon = r.status === 'answered' ? '<i class="fas fa-check-circle"></i> ' : '<i class="far fa-clock"></i> ';
                    const dateObj = new Date(r.created_at);
                    const dateStr = dateObj.toLocaleString('en-US', {
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric', 
                        hour: 'numeric', 
                        minute: 'numeric', 
                        hour12: true
                    });
                    
                    const statusClass = r.status || 'pending';
                    const displayStatus = statusClass.charAt(0).toUpperCase() + statusClass.slice(1);

                    html += `
                        <div class="report-item">
                            <div class="report-header">
                                <div>
                                    <span class="report-headline">${escapeHtml(r.headline)}</span>
                                    <div class="report-date"><i class="far fa-calendar-alt"></i> ${dateStr}</div>
                                </div>
                                <span class="status-badge status-${statusClass}">
                                    ${statusIcon} ${displayStatus}
                                </span>
                            </div>
                            <div class="report-content">
                                ${escapeHtml(r.report).replace(/\n/g, '<br>')}
                            </div>
                            ${r.reply ? `
                                <div class="admin-reply">
                                    <span class="reply-label"><i class="fas fa-user-shield"></i> Admin Response</span>
                                    <div class="reply-content">
                                        ${escapeHtml(r.reply).replace(/\n/g, '<br>')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
                container.innerHTML = html;
            } catch (e) {
                console.error("Refresh failed", e);
            }
        }

        function escapeHtml(text) {
            if (!text) return "";
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        setInterval(refreshReports, 10000);
    </script>
</body>
</html>