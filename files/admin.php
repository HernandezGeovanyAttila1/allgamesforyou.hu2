<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* ---------- ADMIN CHECK ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied");
}

/* ---------- DB CONNECTION ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli("localhost", "skdneoaa", "t3YnVb0HN**40f", "skdneoaa_Felhasznalok");
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    die("Database connection error");
}

/* ---------- AJAX: FETCH USERS ---------- */
if (isset($_GET['ajax'])) {
    header("Content-Type: application/json");
    try {
        $search = $_GET['search'] ?? '';
        $searchTerm = "%$search%";

        $stmt = $conn->prepare("
            SELECT user_id, username, role, is_banned
            FROM users
            WHERE username LIKE ?
            ORDER BY username
            LIMIT 50
        ");
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();

        $users = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($users);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch users']);
    }
    exit;
}

/* ---------- AJAX: USER ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['uid'])) {
    $uid = (int) $_POST['uid'];
    $action = $_POST['action'];

    try {
        if ($action === 'ban') {
            $stmt = $conn->prepare("UPDATE users SET is_banned=1 WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            // Secure DELETEs
            $stmt = $conn->prepare("DELETE FROM comments WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM games WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();

            $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id=? OR receiver_id=?");
            $stmt->bind_param("ii", $uid, $uid);
            $stmt->execute();
        } elseif ($action === 'unban') {
            $stmt = $conn->prepare("UPDATE users SET is_banned=0 WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } elseif ($action === 'admin') {
            $stmt = $conn->prepare("UPDATE users SET role='admin' WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } elseif ($action === 'user') {
            $stmt = $conn->prepare("UPDATE users SET role='user' WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } elseif ($action === 'delete_comments') {
            $stmt = $conn->prepare("DELETE FROM comments WHERE user_id=?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        }
        echo "ok";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Action failed";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">

    <style>
        /* Themes */
        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
            --poppins: 'Poppins', sans-serif;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f1c40f;
        }

        body.dark {
            --bg-mesh-1: #0b0712;
            --bg-mesh-2: #1e0b3c;
            --bg-mesh-3: #050308;
            --text-main: #e6e0eb;
            --border-color: rgba(191, 50, 241, 0.15);
            --glass: rgba(15, 10, 21, 0.75);
            --glass-strong: rgba(10, 5, 20, 0.9);
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 30px rgba(191, 50, 241, 0.4);
            --box-bg: rgba(191, 50, 241, 0.08);
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-text: #fff;
            --table-hover: rgba(191, 50, 241, 0.1);
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.2);
            --glass: rgba(247, 243, 232, 0.8);
            --glass-strong: rgba(255, 255, 255, 0.95);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --box-bg: rgba(155, 89, 182, 0.06);
            --input-bg: rgba(0, 0, 0, 0.04);
            --input-text: #333;
            --table-hover: rgba(155, 89, 182, 0.08);
        }

        body {
            font-family: var(--poppins);
            margin: 0;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            min-height: 100vh;
            background-attachment: fixed;
            transition: color 0.5s ease;
            overflow-x: hidden;
        }

        /* Bg Animation */
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

        /* ---------- LAYOUT ---------- */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--glass);
            backdrop-filter: blur(30px);
            padding: 20px 40px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: slideDown 0.8s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-title {
            font-family: var(--orbitron);
            font-size: 1.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: var(--glow);
        }

        /* Search Bar */
        .search-wrapper {
            position: relative;
        }

        .search-input {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            padding: 12px 20px 12px 45px;
            border-radius: 12px;
            color: var(--text-main);
            font-family: var(--poppins);
            width: 250px;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            width: 350px;
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        /* ---------- TABLE ---------- */
        .table-container {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            overflow: hidden;
            /* Rounded corners for table */
            animation: fadeIn 1s ease 0.2s backwards;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: rgba(0, 0, 0, 0.2);
            padding: 20px;
            font-family: var(--orbitron);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--table-hover);
        }

        /* User Info */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .username {
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-admin {
            background: rgba(191, 50, 241, 0.2);
            color: var(--accent);
            border: 1px solid var(--accent);
        }

        .badge-user {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .status-active {
            color: var(--success);
        }

        .status-banned {
            color: var(--danger);
            font-weight: bold;
        }

        /* Actions */
        .action-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
            margin-right: 6px;
            font-family: var(--poppins);
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-danger {
            color: var(--danger);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            background: rgba(231, 76, 60, 0.1);
            border-color: var(--danger);
        }

        .btn-success {
            color: var(--success);
            border-color: rgba(46, 204, 113, 0.3);
        }

        .btn-success:hover {
            background: rgba(46, 204, 113, 0.1);
            border-color: var(--success);
        }

        /* Back Button logic */
        .back-nav {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-main);
            gap: 8px;
            font-weight: 600;
            transition: 0.3s;
        }

        .back-nav:hover {
            color: var(--accent);
            transform: translateX(-5px);
        }

        /* TOAST NOTIFICATION */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: var(--glass-strong);
            backdrop-filter: blur(15px);
            padding: 15px 25px;
            border-radius: 12px;
            border-left: 5px solid var(--accent);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.4);
            color: var(--text-main);
            font-family: var(--poppins);
            min-width: 250px;
            animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Theme Toggle */
        /*.theme-toggle-btn {*/
        /*    position: fixed;*/
        /*    bottom: 30px;*/
        /*    left: 30px;*/
        /*    width: 50px;*/
        /*    height: 50px;*/
        /*    border-radius: 50%;*/
        /*    background: var(--glass);*/
        /*    display: flex;*/
        /*    align-items: center;*/
        /*    justify-content: center;*/
        /*    cursor: pointer;*/
        /*    border: 1px solid var(--border-color);*/
        /*    box-shadow: var(--shadow);*/
        /*    z-index: 1000;*/
        /*    transition: 0.3s;*/
        /*}*/

        /*.theme-toggle-btn:hover {*/
        /*    transform: scale(1.1) rotate(10deg);*/
        /*    border-color: var(--accent);*/
        /*}*/
    </style>
</head>

<body>

    <!-- TOAST CONTAINER -->
    <div id="toast-container" class="toast-container"></div>

    <!-- THEME TOGGLE -->
    <div id="themeToggle" class="theme-toggle-btn">
        <!--<img src="imgandgifs/darklightmode.webp" alt="Theme" style="width: 24px; height: 24px;">-->
    </div>

    <div class="container">
        <!-- HEADER -->
        <header class="admin-header">
            <div class="header-left">
               <a href="profile.php" class="back-nav" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
    <img src="imgandgifs/arrow-left-circle.svg" alt="Back" style="width: 22px; height: 22px;">
    <span>Back to Profile</span>
</a>

                <div class="admin-title">Admin Panel</div>
            </div>
            
            <div class="search-wrapper">
                <input type="text" id="searchInput" class="search-input" placeholder="Search user...">
            </div>
        </header>

        <!-- TABLE SECTION -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <!-- JS Injects Rows Here -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const tableBody = document.getElementById("users-table-body");
        const searchInput = document.getElementById("searchInput");
        const currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;

        /* ---------- UTILS ---------- */
        function escapeHtml(text) {
            if (!text) return "";
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerText = message;
            
            // Animation out
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.4s forwards';
                setTimeout(() => toast.remove(), 400);
            }, 3000);
            
            container.appendChild(toast);
        }

        /* ---------- LOAD USERS ---------- */
        async function loadUsers() {
            if (document.hidden) return;

            try {
                const res = await fetch("admin.php?ajax=1&search=" + encodeURIComponent(searchInput.value));
                if (!res.ok) throw new Error("Network error");

                const users = await res.json();
                const fragment = document.createDocumentFragment();

                if(users.length === 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="4" style="text-align:center; padding: 40px; color: rgba(255,255,255,0.5);">No users found.</td>`;
                    fragment.appendChild(tr);
                } else {
                    users.forEach(u => {
                        const tr = document.createElement("tr");

                        // Role Badge
                        const roleBadge = u.role === 'admin' 
                            ? `<span class="badge badge-admin">Admin</span>` 
                            : `<span class="badge badge-user">User</span>`;

                        // Status Text
                        const statusText = u.is_banned 
                            ? `<span class="status-banned">Banned</span>` 
                            : `<span class="status-active">Active</span>`;
                        
                        // Avatar (placeholder if not in DB select, simplistic)
                        // Note: The AJAX only selects user_id, username, role, is_banned. 
                        // If we want avatars we need to update the PHP query. For now, use a generic one.
                        const avatar = "/imgandgifs/login.png"; 

                        // Actions logic
                        let actionsHtml = '';
                        if (u.user_id != currentUserId) {
                             const banBtn = u.is_banned 
                                ? `<button class="action-btn btn-success" onclick="doAction('unban', ${u.user_id})">Unban</button>`
                                : `<button class="action-btn btn-danger" onclick="doAction('ban', ${u.user_id})">Ban</button>`;
                                
                             const roleBtn = u.role === 'admin'
                                ? `<button class="action-btn" onclick="doAction('user', ${u.user_id})">Demote</button>`
                                : `<button class="action-btn" onclick="doAction('admin', ${u.user_id})">Promote</button>`;
                                
                             const delBtn = `<button class="action-btn btn-danger" onclick="if(confirm('Delete user comments?')) doAction('delete_comments', ${u.user_id})">Clear Comments</button>`;
                             
                             actionsHtml = `${banBtn} ${roleBtn} ${delBtn}`;
                        } else {
                            actionsHtml = `<span style="opacity:0.5; font-size:0.8rem;">(You)</span>`;
                        }

                        tr.innerHTML = `
                            <td>
                                <div class="user-cell">
                                    <img src="${avatar}" class="user-avatar" alt="User">
                                    <span class="username">${escapeHtml(u.username)}</span>
                                </div>
                            </td>
                            <td>${roleBadge}</td>
                            <td>${statusText}</td>
                            <td>${actionsHtml}</td>
                        `;
                        fragment.appendChild(tr);
                    });
                }

                tableBody.innerHTML = "";
                tableBody.appendChild(fragment);

            } catch (e) {
                console.error("Load users failed", e);
            }
        }

        /* ---------- ACTION ---------- */
        async function doAction(action, uid) {
            try {
                const fd = new FormData();
                fd.append("action", action);
                fd.append("uid", uid);

                const res = await fetch("admin.php", { method: "POST", body: fd });
                const txt = await res.text();

                if (res.ok) {
                    showToast("Action successful!", "success");
                    loadUsers();
                } else {
                    showToast("Action failed.", "error");
                }
            } catch (e) {
                showToast("Connection error.", "error");
            }
        }

        /* ---------- AUTO UPDATE ---------- */
        setInterval(loadUsers, 5000); // Poll slower
        searchInput.oninput = debounce(loadUsers, 300);
        loadUsers();

        /* ---------- THEME LOGIC ---------- */
        const themeBtn = document.getElementById('themeToggle');
        const bodyValue = document.body;

        function updateThemeUI() {
            const isDark = bodyValue.classList.contains('dark');
            const img = themeBtn.querySelector('img');
            if (img) {
                img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.1)';
            }
        }

        function applyTheme() {
            const t = localStorage.getItem('theme') || 'dark';
            // Ensure no duplicate classes
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

        applyTheme();
    </script>

</body>

</html>