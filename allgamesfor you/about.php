<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - All Games For You</title>
    <link rel="icon" type="image/x-icon" href="/imgandgifs/logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');

        :root {
            --accent: #bf32f1;
            --orbitron: 'Orbitron', sans-serif;
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
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-hover: rgba(255, 255, 255, 0.07);
        }

        body.bright {
            --bg-mesh-1: #f7f3e8;
            --bg-mesh-2: #fdf2ff;
            --bg-mesh-3: #e8dbf2;
            --text-main: #2c2433;
            --border-color: rgba(155, 89, 182, 0.25);
            --glass: rgba(247, 243, 232, 0.85);
            --glass-strong: rgba(255, 255, 255, 0.95);
            --shadow: 0 10px 30px rgba(155, 89, 182, 0.15);
            --glow: 0 0 20px rgba(155, 89, 182, 0.2);
            --box-bg: rgba(155, 89, 182, 0.06);
            --input-bg: rgba(0, 0, 0, 0.04);
            --card-bg: rgba(0, 0, 0, 0.02);
            --card-hover: rgba(0, 0, 0, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            transition: background 0.8s ease, color 0.5s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-attachment: fixed;
            overflow-x: hidden;
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

        header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background: var(--glass);
            backdrop-filter: blur(30px);
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.5s ease;
        }

        .menu-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0;
            border: none;
            background: none;
            position: static;
            font-family: var(--orbitron);
        }

        .menu-bar .menu-items {
            list-style: none;
            display: flex;
            gap: 25px;
            margin: 0;
            padding: 0;
        }

        .menu-bar .menu-items li a {
            text-decoration: none;
            color: var(--text-main);
            padding: 10px 20px;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            font-weight: bold;
            font-family: var(--orbitron);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid transparent;
        }

        .menu-bar .menu-items li a:hover,
        .menu-bar .menu-items li a.active {
            border-color: var(--accent);
            color: var(--accent);
            text-shadow: var(--glow);
            transform: translateY(-2px);
        }

        header img.logo {
            max-width: 150px;
            height: auto;
            margin-right: 0;
        }

        .container {
            max-width: 1000px;
            margin: 60px auto;
            padding: 50px;
            background: var(--glass);
            backdrop-filter: blur(25px);
            border-radius: 32px;
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-color);
            animation: floatUp 1s cubic-bezier(0.19, 1, 0.22, 1);
            transition: all 0.5s ease;
            position: relative;
            z-index: 10;
        }

        @keyframes floatUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            font-family: var(--orbitron);
            color: var(--accent);
            text-transform: uppercase;
            margin-bottom: 40px;
            letter-spacing: 4px;
            text-shadow: var(--glow);
            font-size: 2.5rem;
        }

        p {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 20px;
            color: var(--text-main);
        }

        .highlight {
            color: var(--accent);
            font-weight: bold;
            text-shadow: 0 0 10px rgba(191, 50, 241, 0.2);
        }

        /* About Page Specific Styles */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-top: 40px;
            text-align: left;
        }

        .feature-item {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 24px;
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
        }

        .feature-item:hover {
            background: var(--card-hover);
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: var(--shadow);
        }

        .feature-item i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 20px;
            display: block;
            text-shadow: var(--glow);
        }

        .feature-item h3 {
            font-size: 1.25rem;
            margin-bottom: 12px;
            color: var(--text-main);
            font-family: var(--orbitron);
        }

        .creators {
            margin-top: 80px;
            padding-top: 40px;
            border-top: 1px solid var(--border-color);
        }

        .creator-cards {
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .creator-card {
            background: var(--glass-strong);
            padding: 40px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            min-width: 280px;
            transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--shadow);
        }

        .creator-card:hover {
            transform: translateY(-10px) scale(1.05);
            border-color: var(--accent);
            box-shadow: var(--glow);
        }

        .creator-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            font-family: var(--orbitron);
            margin-bottom: 8px;
            text-shadow: var(--glow);
        }

        .creator-role {
            font-size: 0.95rem;
            color: var(--text-main);
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .back-btn {
            display: inline-block;
            margin-top: 40px;
            padding: 16px 45px;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
            font-family: var(--orbitron);
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--glow);
        }

        .back-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(191, 50, 241, 0.4);
            filter: brightness(1.1);
        }

        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .theme-icon {
            width: 24px;
            height: 24px;
            pointer-events: none;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .back-link {
            color: var(--text-main);
            text-decoration: none;
            font-weight: bold;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--accent);
            transform: translateX(-5px);
        }

        @media (max-width: 992px) {
            header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px 15px;
                padding: 30px 20px;
                border-radius: 24px;
            }

            h1 {
                font-size: 1.8rem;
                letter-spacing: 2px;
            }

            .creator-card {
                width: 100%;
                min-width: unset;
                padding: 30px;
            }
        }
    </style>
</head>

<body class="dark">

    <header>
        <!-- TOAST CONTAINER -->
        <div id="toast-container" class="toast-container"></div>
        <div class="logo-wrapper">
            <a href="index.php"><img src="imgandgifs/catlogo.png" alt="logo" class="logo"></a>
        </div>

        <nav class="menu-bar">
            <ul class="menu-items" id="menuItems">
                <li><a href="index.php" class="back-link"><i class="fas fa-arrow-left"
                            style="margin-right: 8px;"></i>Back</a></li>
            </ul>
        </nav>

        <div class="profile" style="display:flex; align-items:center; gap:15px;">
            <div id="themeToggle" class="theme-toggle-btn" style="cursor: pointer; background: var(--glass-strong); padding: 5px; border-radius: 12px; border: 1px solid var(--border-color); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                <img src="imgandgifs/sun.svg" alt="Theme" style="width: 25px; height: 25px;">
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!--<a href="message.php" class="msg-btn"-->
                <!--    style="background: #774280; padding: 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; transition: 0.3s;">-->
                <!--    <img src="imgandgifs/message-circle.svg" alt="Message"-->
                <!--        style="width: 22px; height: 22px; pointer-events: none;">-->
                <!--</a>-->
                <!--<a href="profile.php">-->
                <!--<img src=""-->
                <!--        alt="profile"-->
                <!--        style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">-->
                <!--</a>-->
            <?php else: ?>
                <!--<a href="auth.php"><img src="imgandgifs/moving_login.gif" alt="login"-->
                <!--        style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--border-color);"></a>-->
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <main class="main about-content">
            <h1
                style="font-family: var(--orbitron); color: var(--accent); text-transform: uppercase; letter-spacing: 4px; text-shadow: var(--glow); font-size: 2.5rem; margin-bottom: 30px;">
                Unearthing the Undiscovered</h1>

            <section>
                <p>
                    Welcome to <span class="highlight">All Games For You</span>.
                    In an industry dominated by massive blockbusters, countless masterpieces are often overshadowed.
                </p>
                <p>
                    Our mission is to <span class="highlight">curate and showcase</span> exceptional, under-appreciated
                    titles
                    that offer unique experiences. We provide a dedicated platform for these hidden gems to shine.
                </p>
            </section>

            <section class="features-section">
                <h2
                    style="font-family: var(--orbitron); font-size: 1.8rem; margin-top: 20px; color: var(--accent); text-align: center; text-transform: uppercase; letter-spacing: 3px; text-shadow: var(--glow);">
                    Our Platform</h2>
                <div class="features-grid">
                    <div class="feature-item">
                        <i class="fas fa-gem"></i>
                        <h3>Curated Discovery</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 0; color: #888;">Hand-picked selection of
                            high-quality, underrated games across various genres.</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-search"></i>
                        <h3>Advanced Search</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 0; color: #888;">Efficiently locate specific titles
                            or explore new categories with intuitive tools.</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-user-astronaut"></i>
                        <h3>Personalized Experience</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 0; color: #888;">Build your own library of favorites
                            and tailor your browsing preferences.</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-comments"></i>
                        <h3>Community Engagement</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 0; color: #888;">Join the conversation by sharing
                            insights and reviews on the games you discover.</p>
                    </div>
                </div>
            </section>

            <section class="creators">
                <h2
                    style="font-family: var(--orbitron); font-size: 1.8rem; color: var(--accent); text-transform: uppercase; letter-spacing: 3px; text-align: center; text-shadow: var(--glow);">
                    The Architects</h2>
                <p>Architected and developed with passion by:</p>
                <div class="creator-cards">
                    <div class="creator-card">
                        <div class="creator-name">Hernandez Geovany</div>
                        <div class="creator-role">Founder & Lead Dev</div>
                    </div>
                    <div class="creator-card">
                        <div class="creator-name">Bodnar Krisztina</div>
                        <div class="creator-role">Founder & Lead Dev</div>
                    </div>
                </div>
            </section>

            <div class="back-btn-wrapper" style="margin-top: 50px;">
                <a href="index.php" class="back-btn">Return to Home</a>
            </div>
        </main>
    </div>

    <script>
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
    </script>

</body>

</html>