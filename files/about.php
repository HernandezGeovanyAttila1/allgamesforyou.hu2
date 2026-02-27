<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - All Games For You</title>
    <link rel="icon" type="image/png" sizes="128x128" href="/imgandgifs/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
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
        }

        /* body.bright { ... } REMOVED per request */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            transition: color 0.5s ease;
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
        }

        .menu-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            border-bottom: 1px solid #ddd;
            position: sticky;
            top: 0;
            z-index: 1000;
            font-family: Arial, 'Inter', sans-serif;
        }

        .menu-bar .menu-items {
            list-style: none;
            display: flex;
            gap: 25px;
            margin: 0;
            padding: 0;
            margin-left: 20px;
        }

        .menu-bar .menu-items li a {
            text-decoration: none;
            color: var(--text-main);
            padding: 10px 20px;
            border-radius: 12px;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            font-weight: bold;
            font-family: var(--orbitron);
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid transparent;
        }

        .menu-bar .menu-items li a:hover {
            border-color: var(--accent);
            color: var(--accent);
            text-shadow: var(--glow);
            transform: translateY(-2px);
        }

        header img.logo {
            max-width: 150px;
            height: auto;
        }

        .container {
            max-width: 900px;
            margin: 60px auto;
            padding: 50px;
            background: var(--glass);
            backdrop-filter: blur(25px);
            border-radius: 32px;
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-color);
            animation: floatUp 1s cubic-bezier(0.19, 1, 0.22, 1);
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
            font-size: 1.1em;
            line-height: 1.8;
            margin-bottom: 20px;
            color: var(--text-light);
        }

        .highlight {
            color: var(--accent);
            font-weight: bold;
        }

        .creators {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .creators h2 {
            font-family: "Orbitron", system-ui;
            font-size: 1.5em;
            margin-bottom: 15px;
            color: #d9cddb;
        }

        .back-btn {
            display: inline-block;
            margin-top: 40px;
            padding: 14px 40px;
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

        /* ----------- THEME TOGGLE BUTTON ----------- */
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
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1), filter 0.3s ease;
        }
    </style>
</head>

<body class="dark">

    <header>
        <a href="index.php"><img src="imgandgifs/catlogo.png" alt="logo" class="logo"></a>

        <nav class="menu-bar">
            <ul class="menu-items">
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php" style="color:white; background:#b79dc2;">About</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <h1>Unearthing the Undiscovered</h1>

        <p>
            Welcome to <span class="highlight">All Games For You</span>.
        </p>
        <p>
            In an industry dominated by massive blockbusters, countless masterpieces are often overshadowed.
            Our mission is to <span class="highlight">curate and showcase</span> exceptional, under-appreciated titles
            that offer unique experiences.
            We provide a dedicated platform for these hidden gems to shine, ensuring they reach the audience they
            deserve.
        </p>

        <div class="features-section" style="margin-top: 40px; text-align: left;">
            <h2
                style="font-family: 'Orbitron', system-ui; font-size: 1.5em; margin-bottom: 15px; color: #d9cddb; text-align: center;">
                Our Platform</h2>
            <ul style="list-style: none; padding: 0; color: var(--text-light); line-height: 1.8;">
                <li style="margin-bottom: 15px;">
                    <strong class="highlight">Curated Discovery:</strong> Access a hand-picked selection of
                    high-quality, underrated games across various genres.
                </li>
                <li style="margin-bottom: 15px;">
                    <strong class="highlight">Advanced Search:</strong> Efficiently locate specific titles or explore
                    new categories with our intuitive search tools.
                </li>
                <li style="margin-bottom: 15px;">
                    <strong class="highlight">Personalized Experience:</strong> Build your own library of favorites and
                    tailor your browsing preferences.
                </li>
                <li style="margin-bottom: 15px;">
                    <strong class="highlight">Community Engagement:</strong> Join the conversation by sharing insights
                    and reviews on the games you discover.
                </li>
            </ul>
        </div>

        <div class="creators">
            <h2>Founders & Lead Developers</h2>
            <p>This platform was architected and developed by:</p>
            <p style="font-size: 1.3em; font-weight: bold; color: #e170ff; margin-top: 15px;">
                Hernandez Geovany <br> <span
                    style="font-size: 0.8em; color: var(--text-light); font-weight: normal;">&</span> <br> Bodnar
                Krisztina
            </p>
        </div>

        <a href="index.php" class="back-btn">Return to Home</a>
    </div>

    <script>
        const bodyValue = document.body;
        const savedTheme = localStorage.getItem("theme") || "dark";
        bodyValue.classList.add(savedTheme);
    </script>

</body>

</html>