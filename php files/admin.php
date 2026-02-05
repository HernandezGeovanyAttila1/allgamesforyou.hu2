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
            --bg-body: #2a003f;
            --text-main: white;
            --box-bg: rgba(255, 255, 255, .1);
            --btn-bg: rgba(255, 255, 255, 0.1);
            --btn-color: white;
            --btn-hover: rgba(255, 255, 255, 0.2);
            --input-bg: white;
            --input-text: black;
            --panel-text: white;
        }

        body.bright {
            --bg-body: #f3e5f5;
            --text-main: #333;
            --box-bg: rgba(0, 0, 0, 0.05);
            --btn-bg: rgba(0, 0, 0, 0.05);
            --btn-color: #333;
            --btn-hover: rgba(0, 0, 0, 0.1);
            --input-bg: white;
            --input-text: #333;
            --panel-text: #4a148c;
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            font-family: Poppins, sans-serif;
            margin: 0;
            transition: background 0.3s, color 0.3s;
        }

        .admin-panel {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 32px;
            font-weight: bold;
            margin: 20px;
            color: var(--panel-text);
        }

        .box {
            background: var(--box-bg);
            padding: 15px;
            margin: 10px;
            border-radius: 12px;
        }

        button {
            margin: 4px;
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            background: var(--btn-bg);
            color: var(--btn-color);
            transition: background 0.2s;
        }

        button:hover {
            background: var(--btn-hover);
        }

        /* ----- BIG HOME BUTTON ----- */
        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 19px 30px;
            transition: .2s;
            cursor: pointer;
        }

        .back-btn:hover {
            transform: scale(1.08);
        }

        /* ----- SEARCH ----- */
        .search-box {
            margin: 15px;
        }

        .search-box input {
            padding: 8px;
            border-radius: 8px;
            border: none;
            width: 220px;
            background: var(--input-bg);
            color: var(--input-text);
        }

        .users {
            font-size: 30px;
        }

        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--box-bg);
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            z-index: 1000;
        }

        .theme-toggle-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>

<body>

    <div id="themeToggle" class="theme-toggle-btn">💡</div>
    <div class="admin-panel">
        <img src="/imgandgifs/admin_logo.png" width="80" alt="Admin Logo">
        <span>Admin Panel</span>
    </div>

    <!-- Removed invalid form wrapper -->
    <a href="profile.php">
        <img src="imgandgifs/back_button.png" width="80" class="back-btn" alt="Back">
    </a>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search user...">
    </div>

    <div id="users"></div>

    <script>
        const usersBox = document.getElementById("users");
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

        /* ---------- LOAD USERS ---------- */
        async function loadUsers() {
            // Don't poll if tab is hidden
            if (document.hidden) return;

            try {
                const res = await fetch(
                    "admin.php?ajax=1&search=" + encodeURIComponent(searchInput.value)
                );
                if (!res.ok) throw new Error("Network error");

                const users = await res.json();
                const fragment = document.createDocumentFragment();

                users.forEach(u => {
                    const div = document.createElement("div");
                    div.className = "box";

                    // Safe HTML generation
                    div.innerHTML = `
                <b>${escapeHtml(u.username)}</b><br>
                Role: ${escapeHtml(u.role)}<br>
                Status: ${u.is_banned ? "🚫 Banned" : "✅ Active"}
                ${u.user_id != currentUserId ? `
                <div>
                    <button onclick="doAction('${u.is_banned ? 'unban' : 'ban'}', ${u.user_id})">
                        ${u.is_banned ? 'Unban' : 'Ban'}
                    </button>
                    <button onclick="doAction('${u.role === 'admin' ? 'user' : 'admin'}', ${u.user_id})">
                        ${u.role === 'admin' ? 'Make User' : 'Make Admin'}
                    </button>
                    <button onclick="if(confirm('Delete ALL comments?')) doAction('delete_comments', ${u.user_id})">
                        🗑 Delete Comments
                    </button>
                </div>` : ''}
            `;
                    fragment.appendChild(div);
                });

                usersBox.innerHTML = "";
                usersBox.appendChild(fragment);

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

                const res = await fetch("admin.php", {
                    method: "POST",
                    body: fd
                });

                if (res.ok) {
                    loadUsers();
                } else {
                    alert("Action failed on server");
                }
            } catch (e) {
                alert("Connection error");
            }
        }

        /* ---------- AUTO UPDATE ---------- */
        setInterval(loadUsers, 3000);
        searchInput.oninput = debounce(loadUsers, 300);
        loadUsers();

        // Theme Logic
        const themeBtn = document.getElementById('themeToggle');
        function applyTheme() {
            const t = localStorage.getItem('theme') || 'dark';
            if (t === 'bright') {
                document.body.classList.add('bright');
                themeBtn.innerText = '🌙';
            } else {
                document.body.classList.remove('bright');
                themeBtn.innerText = '☀️';
            }
        }

        themeBtn.addEventListener('click', () => {
            const isBright = document.body.classList.contains('bright');
            const newTheme = isBright ? 'dark' : 'bright';
            localStorage.setItem('theme', newTheme);
            applyTheme();
        });

        applyTheme();
    </script>

</body>

</html>