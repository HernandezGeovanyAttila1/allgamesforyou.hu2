<?php
session_start();
$loggedInUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT VS DOGS (Premium Battle Edition)</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050510;
            --neon: #ff0055;
            --neon-blue: #00f3ff;
            --neon-green: #00ff66;
            --neon-yellow: #ffee00;
        }

        body, html {
            margin: 0; padding: 0; width: 100%; height: 100%;
            background-color: var(--bg); color: #fff;
            font-family: 'Inter', sans-serif; 
            overflow: hidden; user-select: none;
        }

        h1, h2, h3, .hud, #waveTextOverlay, .btn, .upgrade-card h3 {
            font-family: 'Orbitron', sans-serif;
        }

        #gameContainer {
            width: 100%; height: 100%; position: relative;
            display: flex; justify-content: center; align-items: center;
        }

        canvas {
            width: 100%; height: 100%;
            object-fit: contain;
            image-rendering: auto; /* Smoother scaling */
            background: #111;
            box-shadow: 0 0 30px var(--neon-blue);
        }

        /* Cheap CRT Scanline Overlay using pure CSS */
        .crt-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 10;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%);
            background-size: 100% 4px;
        }

        .ui-layer {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; display: flex; flex-direction: column;
            justify-content: center; align-items: center; z-index: 30; text-align: center;
            background: radial-gradient(circle, rgba(5,5,16,0.7) 0%, rgba(5,5,16,0.95) 100%);
            backdrop-filter: blur(10px);
        }

        .hidden { display: none !important; }

        h1 {
            color: var(--neon); font-size: 48px; margin-bottom: 10px;
            text-shadow: 0 0 10px var(--neon), 0 0 20px var(--neon); font-weight: 900;
            letter-spacing: 2px;
        }

        p {
            font-size: 16px; color: #ccc; margin-bottom: 25px;
            letter-spacing: 1px;
        }

        .blink { animation: blinker 1.5s linear infinite; }
        @keyframes blinker { 50% { opacity: 0.3; } }

        #scoreboard, #startScoreboard {
            background: rgba(0, 0, 0, 0.6); border: 1px solid var(--neon-blue);
            box-shadow: inset 0 0 15px rgba(0, 243, 255, 0.2);
            padding: 20px; border-radius: 8px; pointer-events: auto;
            max-width: 400px; width: 90%; margin-top: 10px;
            backdrop-filter: blur(2px);
        }

        .score-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .score-row span:last-child { color: var(--neon-blue); font-weight: bold; }
        .score-row.header { color: #aaa; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 8px; margin-bottom: 12px; }

        input {
            background: rgba(0,0,0,0.5); border: 2px solid var(--neon);
            color: #fff; font-family: inherit; font-size: 18px;
            padding: 10px; text-align: center; width: 220px;
            outline: none; text-transform: uppercase; margin-bottom: 20px;
            pointer-events: auto; border-radius: 4px;
            transition: all 0.3s ease;
        }
        input:focus {
            box-shadow: 0 0 15px var(--neon);
        }

        .hud {
            position: absolute; top: 15px; left: 20px; right: 20px;
            display: flex; justify-content: space-between; z-index: 25;
            font-size: 18px; font-weight: bold; pointer-events: none;
            text-shadow: 1px 1px 2px #000;
        }

        #hud-health { color: var(--neon); display: flex; align-items: center; gap: 5px; }
        #hud-lvl { color: var(--neon-green); font-size: 16px; margin-top: 5px; }
        #hud-score { color: var(--neon-blue); text-align: right; }
        #hud-wave { position: absolute; top: 0; left: 50%; transform: translateX(-50%); color: #fff; text-shadow: 0 0 10px #fff; }

        .level-progress-bar { width: 180px; height: 10px; border: 1px solid #444; margin-top: 5px; background: rgba(0,0,0,0.5); border-radius: 5px; overflow: hidden; }
        .level-fill { height: 100%; background: linear-gradient(90deg, #00aa44, var(--neon-green)); width: 0%; transition: width 0.3s; }
        .dash-bar { width: 100px; height: 6px; border: 1px solid #444; margin-top: 3px; background: rgba(0,0,0,0.5); border-radius: 3px; overflow: hidden; }
        .dash-fill { height: 100%; background: var(--neon-blue); width: 100%; }

        #backBtn {
            position: absolute; bottom: 20px; left: 20px; color: var(--neon);
            text-decoration: none; font-size: 14px; font-weight: bold;
            border: 1px solid var(--neon); padding: 10px 20px; border-radius: 6px;
            z-index: 100; pointer-events: auto; background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px); transition: all 0.3s ease; text-transform: uppercase;
        }
        #backBtn:hover {
            background: var(--neon); color: #000; box-shadow: 0 0 15px var(--neon);
        }

        .upgrade-card-container { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; justify-content: center; max-width: 900px; }
        .upgrade-card {
            border: 2px solid #555; background: rgba(17, 17, 17, 0.85); padding: 20px;
            border-radius: 4px; width: 220px; cursor: pointer; pointer-events: auto;
            text-align: center; transition: all 0.2s ease; backdrop-filter: blur(8px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        .upgrade-card:hover { border-color: #00ff41; background: rgba(0, 40, 0, 0.8); transform: translateY(-5px); box-shadow: 0 0 20px rgba(0,255,65,0.4); }
        .upgrade-card h3 { font-size: 18px; color: #00ff41; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px; }
        .upgrade-card .buff { font-size: 14px; margin: 0 0 5px 0; color: #fff; font-weight: bold; }
        .upgrade-card .nerf { font-size: 12px; margin: 0; color: #ff0055; opacity: 0.8; }
        .upgrade-card-cost { color: var(--neon-yellow); font-size: 16px; margin-top: 10px; font-weight: 900; }

        .btn {
            background: rgba(17, 17, 17, 0.8); color: var(--neon-yellow); border: 2px solid var(--neon-yellow);
            padding: 12px 25px; cursor: pointer; font-family: inherit; font-size: 18px; font-weight: bold;
            pointer-events: auto; margin-top: 15px; border-radius: 6px; transition: all 0.3s ease;
        }
        .btn:hover { background: var(--neon-yellow); color: #000; box-shadow: 0 0 20px rgba(255,238,0,0.4); }
        
        #waveTextOverlay {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 40; pointer-events: none; font-size: 60px; color: #fff; opacity: 0;
            font-weight: 900; transition: opacity 0.5s; text-shadow: 0 0 30px var(--neon-blue); letter-spacing: 8px;
            font-family: 'Orbitron', 'Courier New', monospace;
        }

        .logo-gif {
            width: 150px; border-radius: 12px; margin-bottom: 20px;
            box-shadow: 0 0 30px rgba(0, 243, 255, 0.4);
            border: 2px solid var(--neon-blue);
        }

        /* Mobile Controls */
        #mobileControls {
            position: absolute; bottom: 0; left: 0; width: 100%; height: 100%; pointer-events: none; display: none; z-index: 1000;
        }
        .joystick-container {
            position: absolute; bottom: 8vmin; left: 8vmin; width: 25vmin; height: 25vmin; min-width: 100px; min-height: 100px;
            background: rgba(255,255,255,0.05); border: 2px solid rgba(0,243,255,0.4); border-radius: 50%;
            pointer-events: auto; touch-action: none;
        }
        .joystick-knob {
            position: absolute; top: 50%; left: 50%; width: 50%; height: 50%;
            background: var(--neon-blue); border-radius: 50%; transform: translate(-50%, -50%);
            pointer-events: none; box-shadow: 0 0 15px var(--neon-blue);
        }
        .shoot-btn-mobile {
            position: absolute; bottom: 8vmin; right: 8vmin; width: 22vmin; height: 22vmin; min-width: 80px; min-height: 80px;
            background: rgba(255,0,85,0.15); border: 3px solid var(--neon); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: white;
            pointer-events: auto; touch-action: none; font-weight: bold; font-size: 18px;
            box-shadow: inset 0 0 15px rgba(255,0,85,0.3);
        }
        .shoot-btn-mobile:active {
            background: rgba(255,0,85,0.5); transform: scale(0.95);
        }

        @media (max-width: 768px) {
            h1 { font-size: 32px; }
            .upgrade-card { width: 140px; padding: 10px; }
            .upgrade-card-container { gap: 8px; }
        }
    </style>
</head>
<body>
    <a href="index.php" id="backBtn">BACK TO VAULT</a>
    <div id="gameContainer">
        <!-- High res internal canvas: 800x600 -->
        <canvas id="gameCanvas" width="800" height="600"></canvas>
        <div class="crt-overlay"></div>
        
        <div id="waveTextOverlay">FLOOR 1</div>
        <div id="bossDialogue" class="hidden" style="position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%); width: 80%; background: rgba(0,0,0,0.8); border: 2px solid var(--neon); color: #fff; padding: 20px; text-align: center; font-family: 'Orbitron', sans-serif; z-index: 50; border-radius: 12px; backdrop-filter: blur(5px);">
            <div id="bossName" style="color: var(--neon); font-weight: bold; margin-bottom: 5px; font-size: 1.2rem;">THE WARDEN</div>
            <div id="bossQuote">"YOU SHALL NOT PASS THE TOWER GATES!"</div>
        </div>

        <div class="hud hidden" id="hud">
            <div>
                <div id="hud-health">&#10084; <span id="hud-hp-val">100</span></div>
                <div id="hud-lvl">LVL: 1</div>
                <div id="hud-bonds" style="color: var(--neon-yellow); font-size: 18px; font-weight: 900; margin-top: 8px; text-shadow: 0 0 10px var(--neon-yellow);">WAR BONDS: 0</div>
                <div class="level-progress-bar"><div id="hud-xp" class="level-fill"></div></div>
                <div style="font-size: 10px; color: var(--neon-blue); margin-top: 5px;">DASH</div>
                <div class="dash-bar"><div id="hud-dash-fill" class="dash-fill"></div></div>
            </div>
            <div id="hud-wave">FLOOR 1</div>
            <div>
                <div id="hud-score">SCORE: 00000</div>
            </div>
            <div id="hud-shop" class="hidden" style="position: absolute; top: 70px; left: 50%; transform: translateX(-50%); color: var(--neon-yellow); font-size: 16px; text-shadow: 0 0 10px var(--neon-yellow);">
                BUNKER OPEN
            </div>
        </div>

        <!-- Shop Screen -->
        <div id="shopScreen" class="ui-layer hidden" style="background: radial-gradient(circle, rgba(0,20,0,0.9) 0%, rgba(0,5,0,0.98) 100%); pointer-events: auto;">
            <div style="width: 100%; position: absolute; top: 0; background: #00ff41; color: #000; padding: 10px; font-weight: 900; letter-spacing: 5px; font-size: 24px; font-family: 'Orbitron', sans-serif;">REARMAMENT TERMINAL v4.2</div>
            <div style="padding: 60px 40px 40px; display: flex; flex-direction: column; align-items: center; border: 4px solid #00ff41; border-radius: 20px; box-shadow: 0 0 50px rgba(0,255,65,0.2);">
                <p style="color: #00ff41; font-family: 'Courier New', monospace; font-size: 14px; margin-bottom: 5px; text-shadow: 0 0 5px #00ff41;">[LINK_ESTABLISHED] [FUNDS_DETECTED] [UPGRADE_READY]</p>
                <div id="shopBonds" style="color: var(--neon-yellow); font-size: 20px; font-weight: 900; margin-bottom: 25px; text-shadow: 0 0 10px var(--neon-yellow);">CREDIT_BALANCE: 0</div>
                <div id="shopOptions" class="upgrade-card-container"></div>
                <button class="btn" style="border-color: #00ff41; color: #00ff41; margin-top: 40px; padding: 15px 50px; text-transform: uppercase;" onclick="closeShop()">Force_Wave_Start [EXIT]</button>
            </div>
            <div class="crt-overlay" style="opacity: 0.1; mix-blend-mode: overlay;"></div>
        </div>

        <!-- Start Screen -->
        <div id="startScreen" class="ui-layer">
            <img src="/imgandgifs/cat_gaming.gif" alt="Gaming Cat" class="logo-gif">
            <h1>CAT REVENGE</h1>
            <p style="color: var(--neon-yellow); font-weight: bold; letter-spacing: 2px;">TOWER ASCENSION EDITION</p>
            <p>Climb the Dog Tower | Avenge Your Kind</p>
            <div style="max-width: 500px; font-size: 14px; color: #888; font-family: 'Courier New', monospace; margin-bottom: 20px; line-height: 1.4; border-left: 2px solid var(--neon-blue); padding-left: 10px; text-align: left;">
                <span style="color: var(--neon-blue);">> ARCHIVE FILE D-77:</span> The Great Feline Purge of 20XX left our species scattered. The Canines, led by the cybernetic Dog Lords, built the Tower of Bone to consolidate their power. You are Subject C-47, the last artificially enhanced combat operative. Infiltrate the Tower. Destroy the Dog Lords. Reclaim the earth.
            </div>
            
            <div id="startScoreboard">
                <div class="score-row header"><span>OPERATIVE</span><span>SCORE</span></div>
                <div id="startScoreList"><div style="text-align:center; padding: 10px; color:#666;">ACCESSING DATABASE...</div></div>
            </div>
            <button class="btn blink" style="margin-top:25px; padding: 15px 40px; font-size: 20px;" onclick="setupGame()">INITIALIZE SYSTEM</button>
        </div>

        <!-- Level Up Screen -->
        <div id="levelUpScreen" class="ui-layer hidden" style="background: rgba(0,0,0,0.9); pointer-events: auto;">
            <h1 style="color: var(--neon-green);">MUTATION READY</h1>
            <p>CHOOSE ONE</p>
            <div id="upgradeOptions" class="upgrade-card-container"></div>
        </div>

        <!-- Game Over Screen -->
        <div id="gameOverScreen" class="ui-layer hidden" style="background: rgba(0,0,0,0.9); pointer-events: auto;">
            <h1>GAME OVER</h1>
            <p id="finalScoreDisplay">FINAL SCORE: 0</p>
            <p id="finalRoundDisplay">ROUND: 1</p>
            <p id="finalCoinsDisplay" style="color: var(--neon-yellow); font-weight: bold;">WAR BONDS: 0</p>
            
            <div id="nameInputSection">
                <p>ENTER 3 INITIALS:</p>
                <input type="text" id="playerName" maxlength="3" placeholder="AAA">
                <button class="btn" onclick="submitScore(false)">SUBMIT</button>
            </div>

            <div id="scoreboard" class="hidden">
                <div class="score-row header"><span>NAME</span><span>SCORE</span></div>
                <div id="scoreList"><div style="text-align:center; padding: 10px; color:#666;">LOADING...</div></div>
                <button class="btn" style="margin-top:20px;" onclick="location.reload()">RESTART</button>
            </div>
        </div>

        <!-- Mobile Controls -->
        <div id="mobileControls">
            <div class="joystick-container" id="joyContainer"><div class="joystick-knob" id="joyKnob"></div></div>
            <div class="shoot-btn-mobile" id="shootBtnMobile" style="bottom: 25vmin; right: 5vmin; width: 18vmin; height: 18vmin;">FIRE</div>
            <div class="shoot-btn-mobile" id="dashBtnMobile" style="bottom: 5vmin; right: 25vmin; width: 18vmin; height: 18vmin; background: rgba(0,243,255,0.15); border-color: var(--neon-blue); box-shadow: inset 0 0 15px rgba(0,243,255,0.3);">DASH</div>
        </div>
    </div>

    <script>
        const loggedInUser = "<?= $loggedInUser ?>";

        // Performance optimizations
        // 1. Lower canvas resolution (600x450 internally)
        // 2. Used Primitive shapes instead of image loading.
        // 3. Object Pooling to avoid GC pauses.
        // 4. Simple collision checks and basic wall boundaries without a fully heavy flow-field.

        // 4. Simple collision checks and basic wall boundaries without a fully heavy flow-field.

        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d', { alpha: false });
        const GAME_W = 800;
        const GAME_H = 600;
        const WORLD_W = 2000;
        const WORLD_H = 2000;
        
        // UI Nodes
        const startScreen = document.getElementById('startScreen');
        const gameOverScreen = document.getElementById('gameOverScreen');
        const levelUpScreen = document.getElementById('levelUpScreen');
        const shopScreen = document.getElementById('shopScreen');
        const hud = document.getElementById('hud');
        const hudHealth = document.getElementById('hud-health');
        const hudLevel = document.getElementById('hud-lvl');
        const hudXp = document.getElementById('hud-xp');
        const hudScore = document.getElementById('hud-score');
        const hudWave = document.getElementById('hud-wave');
        const waveTextOverlay = document.getElementById('waveTextOverlay');
        const upgradeOptions = document.getElementById('upgradeOptions');
        const shopOptions = document.getElementById('shopOptions');
        const hudBonds = document.getElementById('hud-bonds');
        const hudShop = document.getElementById('hud-shop');
        
        let state = 'START';
        let currentRound = 1;
        let score = 0;
        let isShopOpen = false;
        let shopTimer = 0;
        const SHOP_DURATION = 20;

        const keys = {};
        const mouse = { x: GAME_W / 2, y: GAME_H / 2, down: false };
        const touch = { active: false, moveX: 0, moveY: 0, shootHold: false, dashHold: false };

        // Music Engine
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        // SFX Engine
        const Sfx = {
            play(type) {
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const now = audioCtx.currentTime;
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                
                if (type === 'shoot') {
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(800, now);
                    osc.frequency.exponentialRampToValueAtTime(100, now + 0.1);
                    gain.gain.setValueAtTime(0.05, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);
                    osc.start(); osc.stop(now + 0.1);
                } else if (type === 'explosion') {
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(150, now);
                    osc.frequency.exponentialRampToValueAtTime(40, now + 0.3);
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.3);
                    osc.start(); osc.stop(now + 0.3);
                    // Add some noise-like crunch
                    const noise = audioCtx.createOscillator();
                    const nGain = audioCtx.createGain();
                    noise.type = 'triangle';
                    noise.frequency.setValueAtTime(60, now);
                    nGain.gain.setValueAtTime(0.2, now);
                    nGain.gain.exponentialRampToValueAtTime(0.001, now + 0.2);
                    noise.connect(nGain); nGain.connect(audioCtx.destination);
                    noise.start(); noise.stop(now + 0.2);
                } else if (type === 'hit') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(200, now);
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.05);
                    osc.start(); osc.stop(now + 0.05);
                } else if (type === 'lvl') {
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(440, now);
                    osc.frequency.exponentialRampToValueAtTime(880, now + 0.2);
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
                    osc.start(); osc.stop(now + 0.4);
                } else if (type === 'coin') {
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(1200, now);
                    osc.frequency.exponentialRampToValueAtTime(1600, now + 0.05);
                    gain.gain.setValueAtTime(0.05, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);
                    osc.start(); osc.stop(now + 0.1);
                }
            }
        };

        let musicPlaying = false;
        let sequenceInterval;

        function playMusic() {
            if (musicPlaying) return;
            if (audioCtx.state === 'suspended') audioCtx.resume();
            musicPlaying = true;
            const bassA = [110, 110, 110, 110, 130.81, 130.81, 110, 110];
            const bassB = [82.41, 82.41, 98, 98, 110, 110, 123.47, 123.47];
            const leadA = [440, 0, 523.25, 0, 587.33, 0, 440, 659.25];
            let step = 0;
            sequenceInterval = setInterval(() => {
                if (!musicPlaying) { clearInterval(sequenceInterval); return; }
                const isSectionB = (step % 64) >= 32;
                const currentStep = step % 64;
                // Bass
                let osc = audioCtx.createOscillator();
                let gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.type = 'triangle';
                let freq = isSectionB ? bassB[step % 8] : bassA[step % 8];
                osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);
                osc.start(); osc.stop(audioCtx.currentTime + 0.1);
                // Lead
                if (currentStep % 2 === 0) {
                    let lOsc = audioCtx.createOscillator(); let lGain = audioCtx.createGain();
                    lOsc.connect(lGain); lGain.connect(audioCtx.destination);
                    lOsc.type = isSectionB ? 'sawtooth' : 'square';
                    let lFreq = isSectionB ? 220 * (1 + (step % 16) / 8) : leadA[step % 8];
                    lOsc.frequency.setValueAtTime(lFreq, audioCtx.currentTime);
                    lGain.gain.setValueAtTime(0.02, audioCtx.currentTime);
                    lGain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
                    lOsc.start(); lOsc.stop(audioCtx.currentTime + 0.12);
                }
                step++;
            }, 140);
        }
        function stopMusic() { musicPlaying = false; clearInterval(sequenceInterval); }

        // Input Listeners
        window.addEventListener('keydown', e => {
            keys[e.code] = true;
            if(e.code === 'Space') e.preventDefault(); // Prevent scrolling
        });
        window.addEventListener('keyup', e => keys[e.code] = false);

        canvas.addEventListener('mousemove', e => {
            const rect = canvas.getBoundingClientRect();
            mouse.x = (e.clientX - rect.left) * (GAME_W / rect.width);
            mouse.y = (e.clientY - rect.top) * (GAME_H / rect.height);
        });
        canvas.addEventListener('mousedown', () => mouse.down = true);
        window.addEventListener('mouseup', () => mouse.down = false);

        // Mobile Touch
        if (('ontouchstart' in window) || navigator.maxTouchPoints > 0) {
            document.getElementById('mobileControls').style.display = 'block';
        }
        
        let joyTouchId = null;
        const joyContainer = document.getElementById('joyContainer');
        const joyKnob = document.getElementById('joyKnob');
        const shootBtnMobile = document.getElementById('shootBtnMobile');
        const dashBtnMobile = document.getElementById('dashBtnMobile');

        joyContainer.addEventListener('touchstart', e => {
            if (joyTouchId !== null) return;
            const t = e.changedTouches[0];
            joyTouchId = t.identifier;
            updateJoystick(t);
        });
        window.addEventListener('touchmove', e => {
            if (joyTouchId === null) return;
            for (let t of e.changedTouches) {
                if (t.identifier === joyTouchId) { updateJoystick(t); e.preventDefault(); break; }
            }
        }, { passive: false });
        window.addEventListener('touchend', e => {
            for (let t of e.changedTouches) {
                if (t.identifier === joyTouchId) {
                    joyTouchId = null; touch.active = false; touch.moveX = 0; touch.moveY = 0;
                    joyKnob.style.transform = `translate(-50%, -50%)`; break;
                }
            }
        });
        function updateJoystick(t) {
            const rect = joyContainer.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2; const centerY = rect.top + rect.height / 2;
            let dx = t.clientX - centerX; let dy = t.clientY - centerY;
            const dist = Math.sqrt(dx*dx + dy*dy);
            if(dist > 0) {
                touch.active = true;
                const cappedDist = Math.min(dist, 40);
                touch.moveX = (dx/dist) * (cappedDist/40);
                touch.moveY = (dy/dist) * (cappedDist/40);
                joyKnob.style.transform = `translate(calc(-50% + ${(dx/dist)*cappedDist}px), calc(-50% + ${(dy/dist)*cappedDist}px))`;
            }
        }
        shootBtnMobile.addEventListener('touchstart', e => { touch.shootHold = true; e.preventDefault(); });
        shootBtnMobile.addEventListener('touchend', e => { touch.shootHold = false; e.preventDefault(); });
        
        dashBtnMobile.addEventListener('touchstart', e => { touch.dashHold = true; e.preventDefault(); });
        dashBtnMobile.addEventListener('touchend', e => { touch.dashHold = false; e.preventDefault(); });

        // POTATO OBJECT POOLING SYSTEM
        class Pool {
            constructor(createFn, maxSize) {
                this.pool = [];
                this.active = [];
                for(let i=0; i<maxSize; i++) this.pool.push(createFn());
            }
            get() {
                if(this.pool.length > 0) {
                    const obj = this.pool.pop();
                    this.active.push(obj);
                    return obj;
                }
                return null;
            }
            free(obj, index) {
                this.active.splice(index, 1);
                this.pool.push(obj);
            }
        }

        const createEnemy = () => ({ x: 0, y: 0, hp: 10, maxHp: 10, speed: 100, size: 20, col: '#ff0000', dmg: 10, coins: 1, type: 'basic', fireTimer: 0, specialTimer: 0, shields: [], archetype: '' });
        const createBullet = () => ({ x: 0, y: 0, vx: 0, vy: 0, life: 1, col: '#00f3ff', dmg: 10, pierce: 1, bounce: 0 });
        const createParticle = () => ({ x: 0, y: 0, vx: 0, vy: 0, life: 1, col: '#fff', size: 2 });
        const createCoin = () => ({ x: 0, y: 0, val: 1 });
        const createFloatingText = () => ({ x: 0, y: 0, text: '', col: '#fff', life: 1, vx: 0, vy: 0 });

        const enemyPool = new Pool(createEnemy, 150); 
        const bulletPool = new Pool(createBullet, 150);
        const enemyBulletPool = new Pool(createBullet, 100); 
        const particlePool = new Pool(createParticle, 200); 
        const coinPool = new Pool(createCoin, 100);
        const floatTextPool = new Pool(createFloatingText, 30);

        let player = {
            x: WORLD_W/2, y: WORLD_H/2, size: 16,
            speed: 260, hp: 100, maxHp: 100,
            cooldown: 0, fireRate: 0.18, bulletDmg: 20, bulletSpd: 650,
            lvl: 1, xp: 0, xpReq: 100,
            coins: 0, spread: 1, pierce: 1, bounce: 0, vampirism: 0, poison: 0,
            col: '#00f3ff',
            dashTimer: 0, dashCooldown: 0, dashMaxCooldown: 1.5, dashSpdMult: 3.5,
            armor: 0,
            magnet: 60,
            critChance: 0,
            critMult: 2,
            poison: 0,
            stretchX: 1, stretchY: 1,
            hasAuraKill: false, regen: 0, auraVisualTimer: 0,
            hasBot: false, botDmg: 10, botRate: 0.5, botTimer: 0, botX: 0, botY: 0
        };

        const mapWalls = [
            // Boundary walls
            { x: -100, y: -100, w: WORLD_W + 200, h: 100 }, // Top
            { x: -100, y: WORLD_H, w: WORLD_W + 200, h: 100 }, // Bottom
            { x: -100, y: -100, w: 100, h: WORLD_H + 200 }, // Left
            { x: WORLD_W, y: -100, w: 100, h: WORLD_H + 200 }, // Right
            // Internal obstacles
            { x: 400, y: 400, w: 150, h: 150 },
            { x: WORLD_W - 600, y: WORLD_H - 600, w: 150, h: 150 },
            { x: 400, y: WORLD_H - 600, w: 80, h: 300 },
            { x: WORLD_W / 2 - 100, y: 250, w: 200, h: 80 }
        ];

        let camera = { x: 0, y: 0 };
        let screenshake = 0;
        let lastTime = 0;
        let enemiesToSpawn = 0;
        let spawnTimer = 0;

        const ALL_UPGRADES = [
            { id: 'dmg', name: 'HOLLOW POINTS', buff: '+25 Damage', nerf: '-15% Fire Rate' },
            { id: 'rate', name: 'RAPID FIRE', buff: '+30% Fire Rate', nerf: '-25% Damage' },
            { id: 'spd', name: 'LIGHTWEIGHT', buff: '+40 Speed', nerf: '-15 Max HP' },
            { id: 'hp', name: 'ARMOR PLATES', buff: '+50 Max HP', nerf: '-10 Speed' },
            { id: 'spread', name: 'EXTRA BARREL', buff: '+1 Bullet', nerf: '-15 Damage' },
            { id: 'pierce', name: 'AP ROUNDS', buff: '+1 Pierce', nerf: '-5 Damage' },
            { id: 'vampire', name: 'VAMPIRE FANGS', buff: '+2 HP on Kill', nerf: '-15 Max HP' },
            { id: 'dash', name: 'DASH ENGINE', buff: '-20% Dash Cooldown', nerf: '+5% Fire Rate' },
            { id: 'armor', name: 'KEVLAR VEST', buff: '+2 Flat Defense', nerf: '-10 Speed' },
            { id: 'magnet', name: 'NANOBOTS', buff: '+80 Pickup Range', nerf: '-5% Damage' },
            { id: 'crit', name: 'EYE OF TIGER', buff: '+15% Crit Chance', nerf: '-5% Speed' },
            { id: 'bounce_up', name: 'RUBBER ROUNDS', buff: '+1 Bounce', nerf: '-10% Damage' },
            { id: 'sniper', name: 'SNIPER SCOPE', buff: '+80 Damage', nerf: '-30% Fire Rate' },
            { id: 'berzerk', name: 'BERZERK CHIP', buff: '+50% Speed', nerf: '-20% Max HP' },
            { id: 'glass', name: 'GLASS CANNON', buff: '+150 Damage', nerf: '-50% Armor' },
            { id: 'wide', name: 'WIDE SPREAD', buff: '+2 Bullets', nerf: '-30 Damage' },
            { id: 'aura_kill', name: 'DEATH AURA', buff: 'Dash kills nearby', nerf: '+8s Dash Cooldown' },
            { id: 'regen', name: 'NANO MENDING', buff: '+1 HP / sec', nerf: '-10 Max HP' },
            { id: 'juggernaut', name: 'JUGGERNAUT', buff: '+100 Max HP', nerf: '-40 Speed' },
            { id: 'bullet_speed', name: 'HIGH VELOCITY', buff: '+300 Bullet Spd', nerf: '-5% Crit Chance' }
        ];

        const ALL_SHOP_ITEMS = [
            { id: 'heal', name: 'MEDKIT', cost: 10, desc: 'Heal 50 HP' },
            { id: 'dmg_shop', name: 'WEAPON OIL', cost: 30, desc: '+10 Damage, -3% Speed' },
            { id: 'rate_shop', name: 'AUTOSEAR', cost: 40, desc: '+15% Fire Rate, -5 Max HP' },
            { id: 'bot_buy', name: 'ROBOT COMPANION', cost: 100, desc: 'A flying bot that shoots enemies', once: true },
            { id: 'bot_dmg', name: 'BOT OVERCLOCK', cost: 50, desc: '+15 Bot Damage', reqBot: true },
            { id: 'bot_rate', name: 'BOT FIRMWARE', cost: 50, desc: '+20% Bot Fire Rate', reqBot: true },
            { id: 'max_hp', name: 'TITANIUM PLATING', cost: 60, desc: '+20 Max HP' },
            { id: 'armor_shop', name: 'SHIELD CAPACITOR', cost: 80, desc: '+1 Armor' }
        ];
        let currentShopItems = [];

        function rectIntersect(r1, r2) {
            return !(r2.x >= r1.x + r1.w || r2.x + r2.w <= r1.x || r2.y >= r1.y + r1.h || r2.y + r2.h <= r1.y);
        }

        function setupGame() {
            startScreen.classList.add('hidden');
            gameOverScreen.classList.add('hidden');
            hud.classList.remove('hidden');
            document.getElementById('nameInputSection').classList.remove('hidden');
            document.getElementById('scoreboard').classList.add('hidden');

            // Reset Pools
            [enemyPool, bulletPool, particlePool, coinPool].forEach(p => {
                while(p.active.length > 0) p.free(p.active[0], 0);
            });

            player.hp = 100; player.maxHp = 100; player.lvl = 1; player.xp = 0; player.xpReq = 100;
            player.bulletDmg = 20; player.fireRate = 0.2; player.spread = 1; player.pierce = 1; player.speed = 250;
            player.bounce = 0; player.vampirism = 0;
            player.magnet = 60; player.critChance = 0; player.critMult = 2; player.armor = 0;
            player.coins = 0; score = 0; currentRound = 1;
            player.hasAuraKill = false; player.dashMaxCooldown = 1.5; player.dashCooldown = 0; player.regen = 0; player.auraVisualTimer = 0;
            player.hasBot = false; player.botDmg = 15; player.botRate = 0.8; player.botTimer = 0; player.botX = WORLD_W/2; player.botY = WORLD_H/2;
            player.x = WORLD_W/2; player.y = WORLD_H/2;
            isShopOpen = false; hudShop.classList.add('hidden'); hudShop.classList.remove('temp-bunker');

            state = 'PLAYING';
            startWave();
            playMusic();
            lastTime = performance.now();
            requestAnimationFrame(gameLoop);
        }

        function startWave() {
            enemiesToSpawn = 10 + currentRound * 5;
            spawnTimer = 0;
            hudWave.innerText = `FLOOR ${currentRound}`;
            waveTextOverlay.innerText = `FLOOR ${currentRound}`;
            waveTextOverlay.style.opacity = 1;
            setTimeout(() => waveTextOverlay.style.opacity = 0, 2000);
            updateHUD();
        }

        function showBossDialogue(name, quote) {
            const diag = document.getElementById('bossDialogue');
            document.getElementById('bossName').innerText = name;
            document.getElementById('bossQuote').innerText = quote;
            diag.classList.remove('hidden');
            setTimeout(() => diag.classList.add('hidden'), 5000);
        }

        function triggerLevelUp() {
            state = 'LEVELUP';
            player.lvl++; player.xp -= player.xpReq; player.xpReq = Math.floor(player.xpReq * 1.5);
            levelUpScreen.classList.remove('hidden');
            upgradeOptions.innerHTML = '';
            
            const shuffled = [...ALL_UPGRADES].sort(() => 0.5 - Math.random()).slice(0, 3);
            shuffled.forEach(u => {
                const card = document.createElement('div'); card.className = 'upgrade-card';
                card.innerHTML = `<h3>${u.name}</h3><p class="buff">${u.buff}</p><p class="nerf">${u.nerf}</p>`;
                card.onclick = () => selectUpgrade(u.id);
                upgradeOptions.appendChild(card);
            });
        }

        function selectUpgrade(id) {
            if(id==='dmg') { player.bulletDmg += 25; player.fireRate *= 1.15; }
            if(id==='rate') { player.fireRate = Math.max(0.04, player.fireRate * 0.75); player.bulletDmg *= 0.75; }
            if(id==='spd') { player.speed += 40; player.maxHp = Math.max(20, player.maxHp - 15); if(player.hp > player.maxHp) player.hp = player.maxHp; }
            if(id==='hp') { player.maxHp += 50; player.hp += 50; player.speed = Math.max(70, player.speed - 10); }
            if(id==='spread') { player.spread++; player.bulletDmg = Math.max(5, player.bulletDmg - 15); }
            if(id==='pierce') { player.pierce++; player.bulletDmg = Math.max(5, player.bulletDmg - 5); }
            if(id==='vampire') { player.vampirism += 2; player.maxHp = Math.max(20, player.maxHp - 15); if(player.hp > player.maxHp) player.hp = player.maxHp; }
            if(id==='dash') { player.dashMaxCooldown *= 0.8; player.fireRate *= 1.05; }
            if(id==='armor') { player.armor += 2; player.speed = Math.max(70, player.speed - 10); }
            if(id==='magnet') { player.magnet += 80; player.bulletDmg *= 0.95; }
            if(id==='crit') { player.critChance += 0.15; player.speed *= 0.95; }
            if(id==='bounce_up') { player.bounce++; player.bulletDmg *= 0.9; }
            if(id==='sniper') { player.bulletDmg += 80; player.fireRate *= 1.3; }
            if(id==='berzerk') { player.speed *= 1.5; player.maxHp = Math.max(20, player.maxHp - 20); if(player.hp > player.maxHp) player.hp = player.maxHp; }
            if(id==='glass') { player.bulletDmg += 150; player.armor -= 5; }
            if(id==='wide') { player.spread += 2; player.bulletDmg = Math.max(5, player.bulletDmg - 30); }
            if(id==='aura_kill') { player.hasAuraKill = true; player.dashMaxCooldown += 8; }
            if(id==='regen') { player.regen += 1; player.maxHp = Math.max(20, player.maxHp - 10); if(player.hp > player.maxHp) player.hp = player.maxHp; }
            if(id==='juggernaut') { player.maxHp += 100; player.hp += 100; player.speed = Math.max(70, player.speed - 40); }
            if(id==='bullet_speed') { player.bulletSpd += 300; player.critChance = Math.max(0, player.critChance - 0.05); }
            
            levelUpScreen.classList.add('hidden'); hud.classList.remove('hidden');
            Sfx.play('lvl');
            state = 'PLAYING'; lastTime = performance.now(); updateHUD();
            requestAnimationFrame(gameLoop);
        }

        function openBunker() {
            isShopOpen = true; shopTimer = SHOP_DURATION;
            hudShop.classList.remove('hidden');
            shopScreen.classList.remove('hidden');

            let validItems = ALL_SHOP_ITEMS.filter(item => {
                if(item.once && player.hasBot && item.id === 'bot_buy') return false;
                if(item.reqBot && !player.hasBot) return false;
                return true;
            });
            validItems.sort(() => 0.5 - Math.random());
            currentShopItems = validItems.slice(0, 3); // 3 random options

            renderShop();
        }

        function renderShop() {
            shopOptions.innerHTML = '';
            document.getElementById('shopBonds').innerText = `CREDIT_BALANCE: ${player.coins}`;
            currentShopItems.forEach(item => {
                const card = document.createElement('div'); card.className = 'upgrade-card';
                card.innerHTML = `<h3>${item.name}</h3><div class="upgrade-card-cost">${item.cost} BONDS</div><p class="buff">${item.desc}</p>`;
                card.onclick = () => buyShopItem(item);
                shopOptions.appendChild(card);
            });
        }

        function buyShopItem(item) {
            if (player.coins >= item.cost) {
                player.coins -= item.cost;
                if(item.id === 'heal') player.hp = Math.min(player.maxHp, player.hp + 50);
                if(item.id === 'dmg_shop') { player.bulletDmg += 10; player.speed *= 0.97; }
                if(item.id === 'rate_shop') { player.fireRate *= 0.85; player.maxHp = Math.max(20, player.maxHp - 5); if(player.hp > player.maxHp) player.hp = player.maxHp; }
                if(item.id === 'bot_buy') { player.hasBot = true; player.botX = player.x; player.botY = player.y; item.cost += 50; currentShopItems = currentShopItems.filter(i => i.id !== 'bot_buy'); }
                if(item.id === 'bot_dmg') { player.botDmg += 15; item.cost += 20; }
                if(item.id === 'bot_rate') { player.botRate *= 0.8; item.cost += 20; }
                if(item.id === 'max_hp') { player.maxHp += 20; player.hp += 20; item.cost += 20; }
                if(item.id === 'armor_shop') { player.armor += 1; item.cost += 50; }
                updateHUD();
                renderShop(); // Refresh UI in case of multi-buys
            }
        }

        function closeShop() {
            isShopOpen = false;
            state = 'PLAYING';
            shopScreen.classList.add('hidden');
            hudShop.classList.add('hidden');
            hudShop.classList.remove('temp-bunker');
            hud.classList.remove('hidden');
            currentRound++; 
            startWave(); 
            lastTime = performance.now();
            requestAnimationFrame(gameLoop);
        }

        function gameOver() {
            state = 'GAMEOVER';
            stopMusic();
            gameOverScreen.classList.remove('hidden');
            document.getElementById('finalScoreDisplay').innerText = `FINAL SCORE: ${score}`;
            document.getElementById('finalRoundDisplay').innerText = `FLOOR REACHED: ${currentRound}`;
            document.getElementById('finalCoinsDisplay').innerText = `WAR BONDS: ${player.coins}`;
            
            if (loggedInUser !== "") {
                document.getElementById('nameInputSection').classList.add('hidden');
                document.getElementById('scoreboard').classList.remove('hidden');
                submitScore(true);
            }
        }

        function spawnFloatingText(x, y, text, col='#fff') {
            let ft = floatTextPool.get();
            if(ft) {
                ft.x = x; ft.y = y; ft.text = text; ft.col = col;
                ft.life = 0.8; ft.vx = (Math.random()-0.5)*40; ft.vy = -60;
            }
        }

        function spawnExplosion(x, y, color) {
            screenshake = Math.min(10, screenshake + 5);
            Sfx.play('explosion');
            for(let i=0; i<10; i++) {
                let p = particlePool.get();
                if(p) {
                    let ang = Math.random() * Math.PI*2; let spd = 100 + Math.random()*200;
                    p.x = x; p.y = y; p.vx = Math.cos(ang)*spd; p.vy = Math.sin(ang)*spd;
                    p.life = 0.3 + Math.random()*0.5; p.col = color;
                    p.size = 2 + Math.random()*4;
                }
            }
        }

        function gameLoop(timestamp) {
            if (state === 'SHOP') {
                const dt = Math.min((timestamp - lastTime) / 1000, 0.1);
                lastTime = timestamp;
                shopTimer -= dt;
                hudShop.innerText = `[SECURE_BUNKER_LINK]: ${Math.ceil(shopTimer)}s`;
                if(shopTimer <= 0) { closeShop(); return; }
                requestAnimationFrame(gameLoop);
                return;
            }
            if (state !== 'PLAYING') return;
            const dt = Math.min((timestamp - lastTime) / 1000, 0.1);
            lastTime = timestamp;

            // Spawning Logic
            if(enemiesToSpawn > 0 && enemyPool.active.length < 80) { // Limit active enemies
                spawnTimer -= dt;
                if(spawnTimer <= 0) {
                    let e = enemyPool.get();
                    if(e) {
                        let isBoss = currentRound % 10 === 0 && enemiesToSpawn === 1;
                        let edge = Math.random();
                        if(edge < 0.25) { e.x = 20; e.y = Math.random() * (WORLD_H - 40) + 20; }
                        else if(edge < 0.5) { e.x = WORLD_W - 20; e.y = Math.random() * (WORLD_H - 40) + 20; }
                        else if(edge < 0.75) { e.x = Math.random() * (WORLD_W - 40) + 20; e.y = 20; }
                        else { e.x = Math.random() * (WORLD_W - 40) + 20; e.y = WORLD_H - 20; }
                        
                        if(isBoss) {
                            e.type = 'boss'; 
                            e.hp = e.maxHp = Math.floor(1000 * Math.pow(currentRound, 1.2)); // Harder HP scaling
                            e.size = 72; e.speed = 120 + currentRound * 2; e.dmg = 50 + currentRound * 5; e.coins = 50;
                            e.specialTimer = 1.0;
                            e.summonedMinions = false;
                            
                            // Archetype selection
                            const types = ['Vindicator', 'Guardian', 'Summoner', 'Enforcer'];
                            e.archetype = types[(Math.floor(currentRound / 10) - 1) % types.length] || types[0];
                            e.col = '#ff0055'; 

                            if(e.archetype === 'Vindicator') {
                                e.col = '#ff4400';
                                showBossDialogue("BLADE-MASTER VIX", "\"THE TOWER OF BONE STANDS ON THE ASHES OF YOUR KIND! I AM THE BLADE THAT ENDED THE FELINE REBELLION!\"");
                            } else if(e.archetype === 'Guardian') {
                                e.col = '#00f3ff'; e.speed = 70;
                                showBossDialogue("WARDEN BARKUS", "\"I AM THE SHIELD OF THE DOG LORDS. NO CAT HAS EVER BREACHED MY GATES AND LIVED!\"");
                                e.shields = [0, Math.PI]; // Current angles of shields
                            } else if(e.archetype === 'Summoner') {
                                e.col = '#b500ff';
                                showBossDialogue("ALPHA-PACK LEADER REX", "\"YOU THINK YOU CAN DEFY US? MY PACK DEVOURED YOUR CREATORS. WE WILL DEVOUR YOU TOO.\"");
                            } else if(e.archetype === 'Enforcer') {
                                e.col = '#ffee00';
                                showBossDialogue("GUNNER RUFUS", "\"CYBERNETIC ENHANCEMENTS ONLINE. LETHAL FORCE AUTHORIZED. ERADICATING ROGUE FELINE ASSET.\"");
                            }
                        } else {
                            let r = Math.random();
                            let isElite = r < 0.15; // 15% Elite chance
                            if(r < 0.15) { e.type = 'speedster'; e.size = 14; e.speed = 130 + currentRound*6; e.hp = e.maxHp = 6 + currentRound*3; e.col = '#b500ff'; e.dmg = 10; e.coins = 0; }
                            else if(r < 0.25) { e.type = 'tank'; e.size = 28; e.speed = 65 + currentRound*3; e.hp = e.maxHp = 35 + currentRound*10; e.col = '#8b4513'; e.dmg = 20; e.coins = 3; }
                            else if(r < 0.4) { e.type = 'ranger'; e.size = 18; e.speed = 90 + currentRound*4; e.hp = e.maxHp = 10 + currentRound*4; e.col = '#00ff66'; e.dmg = 15; e.coins = 2; e.fireTimer = 2; }
                            else if(r < 0.5) { e.type = 'charger'; e.size = 22; e.speed = 50 + currentRound*2; e.hp = e.maxHp = 20 + currentRound*5; e.col = '#ff0055'; e.dmg = 25; e.coins = 2; }
                            else if(r < 0.6) { e.type = 'sniper'; e.size = 16; e.speed = 60 + currentRound*3; e.hp = e.maxHp = 8 + currentRound*3; e.col = '#00f3ff'; e.dmg = 30; e.coins = 2; e.fireTimer = 3; }
                            else if(r < 0.7) { e.type = 'brute'; e.size = 35; e.speed = 40 + currentRound*2; e.hp = e.maxHp = 60 + currentRound*12; e.col = '#444'; e.dmg = 40; e.coins = 5; }
                            else { e.type = 'basic'; e.size = 20; e.speed = 85 + currentRound*4; e.hp = e.maxHp = 12 + currentRound*5; e.col = '#ff9900'; e.dmg = 12; e.coins = 1; }
                            
                            if(isElite) {
                                e.hp = e.maxHp *= 3; e.size *= 1.3; e.col = '#ffee00'; e.coins += 2; e.type = 'elite';
                            }
                        }
                        
                        enemiesToSpawn--; spawnTimer = Math.max(0.1, 0.3 - currentRound*0.01);
                    }
                }
            } else if (enemiesToSpawn <= 0 && enemyPool.active.length === 0) {
                state = 'SHOP';
                openBunker();
                return;
            }

            // (Previous isShopOpen block removed as it's now handled by the SHOP state above)

            // Player Movement Input
            let dx = 0, dy = 0;
            if(touch.active) { dx = touch.moveX; dy = touch.moveY; }
            else {
                if(keys['KeyW'] || keys['ArrowUp']) dy -= 1;
                if(keys['KeyS'] || keys['ArrowDown']) dy += 1;
                if(keys['KeyA'] || keys['ArrowLeft']) dx -= 1;
                if(keys['KeyD'] || keys['ArrowRight']) dx += 1;
                if(dx!==0 && dy!==0) { let len = Math.sqrt(dx*dx+dy*dy); dx/=len; dy/=len; }
            }

            // Player Dash/Aura Logic
            player.dashCooldown -= dt;
            player.dashTimer -= dt;
            if(player.auraVisualTimer > 0) player.auraVisualTimer -= dt;
            let currentSpd = player.speed;
            if((keys['Space'] || touch.dashHold) && player.dashCooldown <= 0 && (dx!==0 || dy!==0 || player.hasAuraKill)) {
                if(player.hasAuraKill) {
                    player.dashTimer = 0.2; 
                    player.dashCooldown = player.dashMaxCooldown;
                    screenshake = 20;
                    Sfx.play('explosion');
                    spawnExplosion(player.x, player.y, '#b500ff');
                    player.auraVisualTimer = 0.4;
                    for(let i=enemyPool.active.length-1; i>=0; i--) {
                        let e = enemyPool.active[i];
                        let dist = Math.sqrt((e.x-player.x)**2 + (e.y-player.y)**2);
                        if(dist < 150) { // Aura radius
                            e.hp -= 10000;
                            spawnFloatingText(e.x, e.y, "OBLITERATED", '#b500ff');
                            spawnExplosion(e.x, e.y, '#b500ff');
                            
                            // Process Kill
                            if(e.hp <= 0) {
                                spawnFloatingText(e.x, e.y - 20, `+${e.maxHp}XP`, '#00f3ff');
                                score += e.maxHp; player.xp += e.maxHp;
                                Sfx.play('coin');
                                if (player.vampirism > 0) player.hp = Math.min(player.maxHp, player.hp + player.vampirism);
                                if(e.coins > 0) {
                                    for(let cc=0; cc<e.coins; cc++) {
                                        let c = coinPool.get();
                                        if(c) { c.x = e.x + (Math.random()*20-10); c.y = e.y + (Math.random()*20-10); }
                                    }
                                }
                                enemyPool.free(e, i);
                                updateHUD();
                                if(player.xp >= player.xpReq) { triggerLevelUp(); return; } // Break loop on level up
                            }
                        }
                    }
                } else {
                    player.dashTimer = 0.2; player.dashCooldown = player.dashMaxCooldown;
                    screenshake = 8;
                }
            }
            if(player.dashTimer > 0 && !player.hasAuraKill) currentSpd *= player.dashSpdMult;

            let nx = player.x + dx * currentSpd * dt;
            let ny = player.y + dy * currentSpd * dt;
            
            // Wall slide checks AABB
            let pRect = {x: nx - player.size/2, y: player.y - player.size/2, w: player.size, h: player.size};
            if(!mapWalls.some(w => rectIntersect(pRect, w))) player.x = nx;
            pRect = {x: player.x - player.size/2, y: ny - player.size/2, w: player.size, h: player.size};
            if(!mapWalls.some(w => rectIntersect(pRect, w))) player.y = ny;
            
            // Clamp strictly
            player.x = Math.max(0, Math.min(WORLD_W, player.x));
            player.y = Math.max(0, Math.min(WORLD_H, player.y));

            // Player Shooting
            player.cooldown -= dt;
            if((mouse.down || touch.shootHold) && player.cooldown <= 0) {
                let wx, wy;
                if(touch.active && touch.shootHold) {
                    wx = player.x + touch.moveX * 100; wy = player.y + touch.moveY * 100;
                } else {
                    wx = mouse.x + camera.x; wy = mouse.y + camera.y;
                }
                let ang = Math.atan2(wy - player.y, wx - player.x);
                let spreadAmt = 0.15;
                let startAng = ang - (Math.floor(player.spread/2) * spreadAmt);
                if(player.spread%2===0) startAng += spreadAmt/2;

                for(let i=0; i<player.spread; i++) {
                    let b = bulletPool.get();
                    if(b) {
                        let finalAng = startAng + i * spreadAmt;
                        b.x = player.x; b.y = player.y;
                        b.vx = Math.cos(finalAng) * player.bulletSpd;
                        b.vy = Math.sin(finalAng) * player.bulletSpd;
                        b.life = 1.0; b.dmg = player.bulletDmg; b.pierce = player.pierce; b.bounce = player.bounce || 0;
                    }
                }
                player.cooldown = player.fireRate;
                Sfx.play('shoot');
                player.stretchX = 0.8; player.stretchY = 1.2;
            }
            
            // Squash and Stretch Lerp
            player.stretchX += (1 - player.stretchX) * 0.1;
            player.stretchY += (1 - player.stretchY) * 0.1;

            // Bot Update & Shooting
            if(player.hasBot) {
                let targetX = player.x + Math.cos(Date.now() * 0.002) * 50;
                let targetY = player.y + Math.sin(Date.now() * 0.002) * 50;
                player.botX += (targetX - player.botX) * 0.05;
                player.botY += (targetY - player.botY) * 0.05;

                player.botTimer -= dt;
                if(player.botTimer <= 0 && enemyPool.active.length > 0) {
                    let closestE = null;
                    let closestD = Infinity;
                    for(let e of enemyPool.active) {
                        let d = Math.sqrt((e.x - player.botX)**2 + (e.y - player.botY)**2);
                        if(d < closestD) { closestD = d; closestE = e; }
                    }
                    if(closestE && closestD < 400) {
                        let b = bulletPool.get();
                        if(b) {
                            let ang = Math.atan2(closestE.y - player.botY, closestE.x - player.botX);
                            b.x = player.botX; b.y = player.botY;
                            b.vx = Math.cos(ang) * 800;
                            b.vy = Math.sin(ang) * 800;
                            b.life = 1.0; b.dmg = player.botDmg; b.pierce = 1; b.bounce = 0;
                            b.col = '#00ff66';
                        }
                        player.botTimer = player.botRate;
                        Sfx.play('shoot');
                    }
                }
            }

            // Camera Setup
            camera.x = Math.max(0, Math.min(WORLD_W - GAME_W, player.x - GAME_W/2));
            camera.y = Math.max(0, Math.min(WORLD_H - GAME_H, player.y - GAME_H/2));

            // Screenshake
            if(screenshake > 0) {
                camera.x += (Math.random() - 0.5) * screenshake;
                camera.y += (Math.random() - 0.5) * screenshake;
                screenshake *= 0.9;
                if(screenshake < 0.5) screenshake = 0;
            }

            // ==== MAIN PHYSICS/UPDATE ====
            
            // Coins
            for(let i=coinPool.active.length-1; i>=0; i--) {
                let c = coinPool.active[i];
                let dist = Math.sqrt((c.x-player.x)**2 + (c.y-player.y)**2);
                if(dist < player.magnet) { // Magnet radius
                    player.coins += c.val; updateHUD(); coinPool.free(c, i); 
                    Sfx.play('coin'); continue;
                }
            }

            // Enemies
            for(let i=enemyPool.active.length-1; i>=0; i--) {
                let e = enemyPool.active[i];
                

                // Movement Logic
                let distToPlayer = Math.sqrt((player.x-e.x)**2 + (player.y-e.y)**2);
                let currentESpeed = e.speed;
                if (e.hp < e.maxHp * 0.3) currentESpeed *= 1.5; 

                if (e.type === 'charger') {
                    if (distToPlayer < 150) currentESpeed *= 2.5; // Speed up when close
                }

                // Ranger/Sniper Logic: Stay back and shoot
                let canMove = true;
                if(e.type === 'ranger' || e.type === 'sniper') {
                    let keepDist = e.type === 'sniper' ? 350 : 200;
                    if(distToPlayer < keepDist) canMove = false;
                    e.fireTimer -= dt;
                    if(e.fireTimer <= 0) {
                        let b = enemyBulletPool.get();
                        if(b) {
                            let ang = Math.atan2(player.y - e.y, player.x - e.x);
                            b.x = e.x; b.y = e.y;
                            if (e.type === 'sniper') {
                                b.vx = Math.cos(ang) * 600; b.vy = Math.sin(ang) * 600;
                                b.life = 2.0; b.dmg = e.dmg; b.col = '#00f3ff'; b.pierce = 3;
                            } else {
                                b.vx = Math.cos(ang) * 350; b.vy = Math.sin(ang) * 350;
                                b.life = 2.0; b.dmg = e.dmg; b.col = '#00ff66'; b.pierce = 1;
                            }
                        }
                        e.fireTimer = e.type === 'sniper' ? 3.0 : 2.0;
                    }
                }

                // Boss Logic
                if(e.type === 'boss') {
                    e.specialTimer -= dt;
                    
                    // ENRAGE & COUNTERS
                    if(e.hp < e.maxHp * 0.4) { // Enrage at 40% HP
                        e.specialTimer -= dt * 0.8; 
                        currentESpeed *= 1.4;
                    }

                    // NEW STORY MECHANIC: Boss desperation summon at 50%
                    if(e.hp <= e.maxHp * 0.5 && !e.summonedMinions) {
                        e.summonedMinions = true;
                        spawnFloatingText(e.x, e.y - 60, "TOWER PROTOCOL: ELITE GUARD", '#ff0055');
                        showBossDialogue("SYSTEM", `WARNING: CRITICAL DAMAGE DETECTED. RELEASING ELITE GUARD TO PROTECT THE TOWER COMMANDER.`);
                        screenshake = 25;
                        for(let i=0; i<4; i++) {
                            let add = enemyPool.get();
                            if(add) {
                                add.x = e.x + (Math.random()-0.5)*150; add.y = e.y + (Math.random()-0.5)*150;
                                add.type = 'elite'; add.size = 25; add.speed = 150; add.hp = add.maxHp = 200 * currentRound; add.col = '#ffee00'; add.dmg = 25; add.coins = 5;
                            }
                        }
                    }

                    if(e.archetype === 'Vindicator') {
                        // COUNTER SPEED: If player is moving fast or dashing, Vix speeds up significantly
                        let pSpd = Math.sqrt(dx*dx + dy*dy) * player.speed;
                        if(player.dashTimer > 0 || pSpd > 300) { e.speed = 220; e.col = '#fff'; } 
                        else { e.speed = 110; e.col = '#ff4400'; }

                        if(e.specialTimer <= 0) {
                            let angToP = Math.atan2(player.y - e.y, player.x - e.x);
                            e.x += Math.cos(angToP) * 180; e.y += Math.sin(angToP) * 180;
                            spawnExplosion(e.x, e.y, e.col); 
                            e.specialTimer = 0.6; // BUFFED
                        }
                    } else if(e.archetype === 'Guardian') {
                        // COUNTER BURST: Internal damage buffer
                        if(!e.dmgBuffer) e.dmgBuffer = 0;
                        e.dmgBuffer = Math.max(0, e.dmgBuffer - dt * 200); // Decays
                        if(e.dmgBuffer > 500) { // If player deals too much burst
                            e.specialTimer = 0.1; // Trigger immediate blast
                            spawnFloatingText(e.x, e.y - 40, "REACTIVE_SHIELD", '#00f3ff');
                            e.dmgBuffer = 0;
                        }

                        for(let s=0; s<e.shields.length; s++) e.shields[s] += dt * 4; // Spin faster
                        if(e.specialTimer <= 0) {
                            for(let i=0; i<8; i++) {
                                let b = enemyBulletPool.get();
                                if(b) {
                                    let ang = (i / 8) * Math.PI * 2;
                                    b.x = e.x; b.y = e.y; b.vx = Math.cos(ang) * 250; b.vy = Math.sin(ang) * 250;
                                    b.life = 2.0; b.dmg = e.dmg; b.col = e.col;
                                }
                            }
                            e.specialTimer = 2.0; // BUFFED
                        }
                    } else if(e.archetype === 'Summoner') {
                        if(e.specialTimer <= 0) {
                            // COUNTER QUANTITY: Summons scale with player Bullet Spread/Pierce
                            let count = 4 + (player.spread + player.pierce); // BUFFED
                            for(let i=0; i<count; i++) {
                                let add = enemyPool.get();
                                if(add) {
                                    add.x = e.x + (Math.random()-0.5)*100; add.y = e.y + (Math.random()-0.5)*100;
                                    add.type = 'speedster'; add.size = 14; add.speed = 180; add.hp = add.maxHp = 15; add.col = '#b500ff'; add.dmg = 10;
                                }
                            }
                            spawnExplosion(e.x, e.y, '#b500ff');
                            e.specialTimer = 2.5; // BUFFED
                        }
                    } else if(e.archetype === 'Enforcer') {
                        // COUNTER DISTANCE: If player is too far, fire long-range beams
                        let dist = Math.sqrt((player.x-e.x)**2 + (player.y-e.y)**2);
                        if(dist > 400 && e.specialTimer <= 0.2) {
                            let b = enemyBulletPool.get();
                            if(b) {
                                let ang = Math.atan2(player.y-e.y, player.x-e.x);
                                b.x = e.x; b.y = e.y; b.vx = Math.cos(ang) * 1000; b.vy = Math.sin(ang) * 1000;
                                b.life = 1.0; b.dmg = e.dmg * 2.0; b.col = '#fff';
                            }
                        }

                        if(e.specialTimer <= 0) {
                            let angToP = Math.atan2(player.y - e.y, player.x - e.x);
                            for(let i=-2; i<=2; i++) {
                                let b = enemyBulletPool.get();
                                if(b) {
                                    let ang = angToP + (i * 0.2);
                                    b.x = e.x; b.y = e.y; b.vx = Math.cos(ang) * 500; b.vy = Math.sin(ang) * 500;
                                    b.life = 2.0; b.dmg = e.dmg; b.col = e.col;
                                }
                            }
                            e.specialTimer = 0.5; // BUFFED
                        }
                    }
 else {
                        // Fallback/Legacy Boss logic
                        if(e.specialTimer <= 0) {
                            for(let i=0; i<12; i++) {
                                let b = enemyBulletPool.get();
                                if(b) {
                                    let ang = (i / 12) * Math.PI * 2;
                                    b.x = e.x; b.y = e.y;
                                    b.vx = Math.cos(ang) * 250; b.vy = Math.sin(ang) * 250;
                                    b.life = 3.0; b.dmg = e.dmg * 0.8; b.col = '#ff0055'; b.pierce = 1;
                                }
                            }
                            e.specialTimer = 4;
                        }
                    }
                }

                let evx = 0, evy = 0;
                if(canMove) {
                    let ang = Math.atan2(player.y - e.y, player.x - e.x);
                    evx = Math.cos(ang) * currentESpeed * dt;
                    evy = Math.sin(ang) * currentESpeed * dt;
                } else if((e.type === 'ranger' && distToPlayer < 200) || (e.type === 'sniper' && distToPlayer < 300)) {
                    // Flee logic for rangers and snipers
                    let ang = Math.atan2(e.y - player.y, e.x - player.x);
                    evx = Math.cos(ang) * currentESpeed * dt;
                    evy = Math.sin(ang) * currentESpeed * dt;
                }
                
                // Wall slide
                let hitWallX = false, hitWallY = false;
                let eRectX = {x: e.x + evx - e.size/2, y: e.y - e.size/2, w: e.size, h: e.size};
                if(!mapWalls.some(w => rectIntersect(eRectX, w))) { e.x += evx; } else { hitWallX = true; }
                let eRectY = {x: e.x - e.size/2, y: e.y + evy - e.size/2, w: e.size, h: e.size};
                if(!mapWalls.some(w => rectIntersect(eRectY, w))) { e.y += evy; } else { hitWallY = true; }
                
                // Potato workaround: if stuck, wiggle
                if(hitWallX && hitWallY) { e.x += (Math.random() - 0.5) * 5; e.y += (Math.random() - 0.5) * 5; }

                // Hit player
                let distToP = Math.sqrt((e.x-player.x)**2 + (e.y-player.y)**2);
                if(distToP < (player.size + e.size/2)) {
                    if (player.dashTimer <= 0) { // Invulnerable during dash
                        let damage = Math.max(1, e.dmg - player.armor);
                        player.hp -= damage; updateHUD();
                        Sfx.play('hit');
                        spawnExplosion(player.x, player.y, '#ff0000');
                        document.body.style.backgroundColor = '#440000';
                        setTimeout(() => document.body.style.backgroundColor = 'var(--bg)', 50);
                        if(player.hp <= 0) { gameOver(); return; }
                    }
                    // Bounce enemy back
                    let bounceAng = Math.atan2(e.y - player.y, e.x - player.x);
                    e.x += Math.cos(bounceAng) * 40; e.y += Math.sin(bounceAng) * 40;
                }
            }

            // Bullets vs Enemies vs Walls using AABB for perf
            for(let i=bulletPool.active.length-1; i>=0; i--) {
                let b = bulletPool.active[i];
                b.x += b.vx * dt; b.y += b.vy * dt; b.life -= dt;
                
                let bRect = {x: b.x-4, y: b.y-4, w: 8, h: 8};
                let hitWallHorizontal = false;
                let hitWallVertical = false;

                // Basic bounce logic against AABB walls
                for(let w of mapWalls) {
                    if(rectIntersect(bRect, w)) {
                        // determine side of bounce roughly
                        let prevX = b.x - b.vx * dt;
                        if(prevX + 4 <= w.x || prevX - 4 >= w.x + w.w) hitWallHorizontal = true;
                        else hitWallVertical = true;
                    }
                }

                if(b.life <= 0 || hitWallHorizontal || hitWallVertical) {
                    if((hitWallHorizontal || hitWallVertical) && b.bounce > 0) {
                        b.bounce--;
                        if(hitWallHorizontal) b.vx *= -1;
                        if(hitWallVertical) b.vy *= -1;
                        if(!hitWallHorizontal && !hitWallVertical) { b.vx *= -1; b.vy *= -1; }
                    } else {
                        bulletPool.free(b, i); continue;
                    }
                }

                for(let j=enemyPool.active.length-1; j>=0; j--) {
                    let e = enemyPool.active[j];
                    
                    // Shield Check for Guardian Boss
                    if(e.type === 'boss' && e.archetype === 'Guardian') {
                        let blocked = false;
                        for(let ang of e.shields) {
                            let sx = e.x + Math.cos(ang) * 45;
                            let sy = e.y + Math.sin(ang) * 45;
                            if(Math.sqrt((b.x-sx)**2 + (b.y-sy)**2) < 20) { blocked = true; break; }
                        }
                        if(blocked) { spawnExplosion(b.x, b.y, '#00f3ff'); bulletPool.free(b, i); break; }
                    }

                    if(Math.sqrt((b.x-e.x)**2 + (b.y-e.y)**2) < (e.size/2 + 6)) {
                        let finalDmg = b.dmg;
                        
                        // BOSS COUNTER: Guardian Damage Buffer
                        if(e.type === 'boss' && e.archetype === 'Guardian') {
                            if(!e.dmgBuffer) e.dmgBuffer = 0;
                            e.dmgBuffer += finalDmg;
                        }

                        let isCrit = Math.random() < player.critChance;
                        if(isCrit) finalDmg *= player.critMult;

                        e.hp -= finalDmg;
                        spawnFloatingText(e.x, e.y, Math.floor(finalDmg).toString(), isCrit ? '#ffec00' : '#fff');
                        spawnExplosion(b.x, b.y, isCrit ? '#ffee00' : b.col);
                        if(isCrit) screenshake = 5;
                        b.pierce--;
                        if(e.hp <= 0) {
                            spawnFloatingText(e.x, e.y - 20, `+${e.maxHp}XP`, '#00f3ff');
                            score += e.maxHp; player.xp += e.maxHp;
                            Sfx.play('coin');
                            if (player.vampirism > 0) player.hp = Math.min(player.maxHp, player.hp + player.vampirism);
                            if(e.coins > 0) {
                                for(let cc=0; cc<e.coins; cc++) {
                                    let c = coinPool.get();
                                    if(c) { c.x = e.x + (Math.random()*20-10); c.y = e.y + (Math.random()*20-10); }
                                }
                            }
                            enemyPool.free(e, j);
                            updateHUD();
                            if(player.xp >= player.xpReq) { triggerLevelUp(); return; } // Break loop on level up
                        }
                        if(b.pierce <= 0) { bulletPool.free(b, i); break; }
                    }
                }
            }

            // Enemy Bullets vs Player
            for(let i=enemyBulletPool.active.length-1; i>=0; i--) {
                let b = enemyBulletPool.active[i];
                b.x += b.vx * dt; b.y += b.vy * dt; b.life -= dt;
                
                if(b.life <= 0) { enemyBulletPool.free(b, i); continue; }

                // Wall collision
                let hitW = false;
                let bRect = {x: b.x-4, y: b.y-4, w: 8, h: 8};
                for(let w of mapWalls) { if(rectIntersect(bRect, w)) { hitW = true; break; } }
                if(hitW) { enemyBulletPool.free(b, i); continue; }

                // Player collision
                let dist = Math.sqrt((b.x-player.x)**2 + (b.y-player.y)**2);
                if(dist < (player.size + 4)) {
                    if(player.dashTimer <= 0) {
                        let dmg = Math.max(1, b.dmg - player.armor);
                        player.hp -= dmg; updateHUD();
                        spawnExplosion(player.x, player.y, '#ff0000');
                        document.body.style.backgroundColor = '#440000';
                        setTimeout(() => document.body.style.backgroundColor = 'var(--bg)', 50);
                        if(player.hp <= 0) { gameOver(); return; }
                    }
                    enemyBulletPool.free(b, i);
                }
            }

            // Particles
            for(let i=particlePool.active.length-1; i>=0; i--) {
                let p = particlePool.active[i];
                p.x += p.vx * dt; p.y += p.vy * dt; p.life -= dt;
                if(p.life <= 0) particlePool.free(p, i);
            }

            // ==== RENDER ====
            ctx.fillStyle = '#111'; ctx.fillRect(0, 0, GAME_W, GAME_H);
            
            // Map Walls
            ctx.fillStyle = '#222';
            mapWalls.forEach(w => {
                if(w.x < camera.x+GAME_W && w.x+w.w > camera.x && w.y < camera.y+GAME_H && w.y+w.h > camera.y)
                    ctx.fillRect(w.x - camera.x, w.y - camera.y, w.w, w.h);
            });

            // Background grid
            ctx.strokeStyle = 'rgba(0, 243, 255, 0.05)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            let cOffX = Math.floor(camera.x) % 100;
            let cOffY = Math.floor(camera.y) % 100;
            for(let x = -cOffX; x < GAME_W; x += 100) { ctx.moveTo(x, 0); ctx.lineTo(x, GAME_H); }
            for(let y = -cOffY; y < GAME_H; y += 100) { ctx.moveTo(0, y); ctx.lineTo(GAME_W, y); }
            ctx.stroke();

            // Floor texture (subtle dots)
            ctx.fillStyle = 'rgba(255, 255, 255, 0.02)';
            for(let i=0; i<10; i++) {
                for(let j=0; j<10; j++) {
                    let dotX = (i * 200 - camera.x % 200 + 200) % 200;
                    let dotY = (j * 200 - camera.y % 200 + 200) % 200;
                    ctx.fillRect(dotX, dotY, 2, 2);
                }
            }
            ctx.stroke();

            // Coins
            ctx.fillStyle = '#ffee00';
            coinPool.active.forEach(c => {
                ctx.beginPath(); ctx.arc(c.x - camera.x, c.y - camera.y, 4, 0, Math.PI*2); ctx.fill();
            });

            // Enemies (Procedural Pixels)
            enemyPool.active.forEach(e => {
                let ex = e.x - camera.x; let ey = e.y - camera.y;
                if(ex < -50 || ex > GAME_W + 50 || ey < -50 || ey > GAME_H + 50) return;

                ctx.save();
                ctx.translate(ex, ey);
                let ang = Math.atan2(player.y - e.y, player.x - e.x);
                if (Math.abs(ang) > Math.PI / 2) { ctx.scale(-1, 1); }
                
                // Dog Shape
                ctx.fillStyle = e.col;
                if(e.type === 'elite') { ctx.shadowBlur = 10; ctx.shadowColor = '#ffee00'; }
                
                let s = e.size;
                let walkCycle = Math.sin(Date.now() * 0.01) * 2;
                
                // Body
                ctx.roundRect(-s/2, -s/2 + walkCycle, s, s, 6); ctx.fill();
                
                // Snout (Dog feature)
                ctx.fillStyle = 'rgba(0,0,0,0.15)';
                ctx.fillRect(-s/6, 2 + walkCycle, s/2, s/3);

                // Floppy Ears
                ctx.fillStyle = e.col;
                ctx.beginPath();
                ctx.moveTo(-s/2, -s/2 + walkCycle); ctx.lineTo(-s/2-5, s/4 + walkCycle); ctx.lineTo(-s/4, -s/2 + walkCycle); ctx.fill();
                ctx.beginPath();
                ctx.moveTo(s/2, -s/2 + walkCycle); ctx.lineTo(s/2+5, s/4 + walkCycle); ctx.lineTo(s/4, -s/2 + walkCycle); ctx.fill();
                
                ctx.shadowBlur = 0;
                // Eyes
                ctx.fillStyle = e.type === 'elite' ? '#ff0000' : '#000';
                ctx.fillRect(-s/4, -s/6 + walkCycle, s/8, s/8);
                ctx.fillRect(s/8, -s/6 + walkCycle, s/8, s/8);

                // Flash white on hit
                // Boss Specific Elements
                if(e.type === 'boss') {
                    if(e.archetype === 'Guardian') {
                        ctx.fillStyle = 'rgba(0, 243, 255, 0.4)';
                        e.shields.forEach(ang => {
                            let sx = ex + Math.cos(ang) * 45;
                            let sy = ey + Math.sin(ang) * 45;
                            ctx.beginPath(); ctx.arc(sx, sy, 12, 0, Math.PI*2); ctx.fill();
                            ctx.strokeStyle = '#00f3ff'; ctx.stroke();
                        });
                    }
                    // Health mini bar
                    ctx.fillStyle = '#000'; ctx.fillRect(-s/2, -s/2 - 15, s, 6);
                    ctx.fillStyle = e.col; ctx.fillRect(-s/2, -s/2 - 15, s * (e.hp/e.maxHp), 6);
                }
                ctx.restore();
            });

            // Bot Rendering
            if(player.hasBot) {
                let bx = player.botX - camera.x; let by = player.botY - camera.y;
                ctx.save();
                ctx.translate(bx, by);
                ctx.fillStyle = '#444';
                ctx.beginPath(); ctx.arc(0, 0, 8, 0, Math.PI*2); ctx.fill();
                ctx.fillStyle = '#00ff66';
                ctx.beginPath(); ctx.arc(0, 0, 4, 0, Math.PI*2); ctx.fill();
                ctx.shadowBlur = 10; ctx.shadowColor = '#00ff66';
                // drone wings
                ctx.fillStyle = '#00f3ff';
                ctx.fillRect(-12, -2, 4, 4); ctx.fillRect(8, -2, 4, 4);
                ctx.restore();
            }

            // Player (Procedural Pixels)
            let drawPx = player.x - camera.x; let drawPy = player.y - camera.y;
            let lookDir = (touch.active && touch.shootHold ? touch.moveX : mouse.x - drawPx);
            
            ctx.save(); ctx.translate(drawPx, drawPy);
            if (lookDir < 0) ctx.scale(-1, 1);
            ctx.scale(player.stretchX, player.stretchY);
            
            // Neon Glow
            ctx.shadowBlur = 15; ctx.shadowColor = player.col;

            // Dash Trail
            if(player.dashTimer > 0) {
                ctx.globalAlpha = 0.3; ctx.fillStyle = player.col;
                ctx.fillRect(-player.size-10, -player.size, player.size*2, player.size*2);
                ctx.globalAlpha = 1;
            }

            // Cat Body
            ctx.fillStyle = player.col;
            let val = player.size;
            ctx.fillRect(-val, -val, val*2, val*2);
            // Cat Ears
            ctx.beginPath();
            ctx.moveTo(-val, -val); ctx.lineTo(-val-2, -val-6); ctx.lineTo(-val+6, -val); ctx.fill();
            ctx.moveTo(val, -val); ctx.lineTo(val+2, -val-6); ctx.lineTo(val-6, -val); ctx.fill();
            // Tail
            ctx.fillRect(val, 0, 8, 2);
            // Eyes
            ctx.shadowBlur = 0;
            ctx.fillStyle = player.dashTimer > 0 ? '#ffee00' : '#fff';
            ctx.fillRect(2, -4, 5, 5); ctx.fillRect(2, 2, 5, 5);
            ctx.restore();

            // Aura Visual Effect
            if(player.auraVisualTimer > 0) {
                let t = 1 - (player.auraVisualTimer / 0.4); // 0 to 1
                ctx.save();
                ctx.beginPath();
                ctx.arc(player.x - camera.x, player.y - camera.y, t * 150, 0, Math.PI*2);
                ctx.strokeStyle = `rgba(181, 0, 255, ${1 - t})`;
                ctx.lineWidth = 15;
                ctx.stroke();
                ctx.fillStyle = `rgba(181, 0, 255, ${(1 - t) * 0.3})`;
                ctx.fill();
                ctx.restore();
            }

            // Bullets
            ctx.fillStyle = '#00f3ff';
            bulletPool.active.forEach(b => {
                ctx.fillRect(b.x - 3 - camera.x, b.y - 3 - camera.y, 6, 6);
            });

            // Enemy Bullets
            enemyBulletPool.active.forEach(b => {
                ctx.fillStyle = b.col;
                ctx.fillRect(b.x - 4 - camera.x, b.y - 4 - camera.y, 8, 8);
            });

            // Particles
            particlePool.active.forEach(p => {
                ctx.fillStyle = p.col;
                ctx.fillRect(p.x - p.size/2 - camera.x, p.y - p.size/2 - camera.y, p.size, p.size);
            });

            // Mini-Map
            const mmS = 0.06; const mmW = WORLD_W * mmS; const mmH = WORLD_H * mmS;
            const mmX = GAME_W - mmW - 20; const mmY = 20;
            ctx.fillStyle = 'rgba(0,0,0,0.5)'; ctx.fillRect(mmX, mmY, mmW, mmH);
            ctx.strokeStyle = 'rgba(0, 243, 255, 0.3)'; ctx.strokeRect(mmX, mmY, mmW, mmH);
            // Player on map
            ctx.fillStyle = '#00f3ff'; ctx.fillRect(mmX + player.x*mmS - 2, mmY + player.y*mmS - 2, 4, 4);
            // Enemies on map
            ctx.fillStyle = '#ff0000';
            enemyPool.active.forEach(e => {
                if(e.type === 'boss') ctx.fillStyle = '#ff0055'; else ctx.fillStyle = '#ff0000';
                ctx.fillRect(mmX + e.x*mmS - 1, mmY + e.y*mmS - 1, 2, 2);
            });

            // Floating Texts
            for(let i=floatTextPool.active.length-1; i>=0; i--) {
                let ft = floatTextPool.active[i];
                ft.x += ft.vx * dt; ft.y += ft.vy * dt; ft.life -= dt;
                if(ft.life <= 0) { floatTextPool.free(ft, i); continue; }
                ctx.fillStyle = ft.col; ctx.font = 'bold 12px Orbitron';
                ctx.fillText(ft.text, ft.x - camera.x, ft.y - camera.y);
            }

            // Crosshair
            if(!touch.active) {
                ctx.strokeStyle = '#00f3ff'; ctx.lineWidth=2;
                ctx.beginPath();
                ctx.moveTo(mouse.x - 10, mouse.y); ctx.lineTo(mouse.x + 10, mouse.y);
                ctx.moveTo(mouse.x, mouse.y - 10); ctx.lineTo(mouse.x, mouse.y + 10);
                ctx.stroke();
            }

            if(player.regen > 0 && player.hp < player.maxHp) { 
                player.hp = Math.min(player.maxHp, player.hp + player.regen * dt); 
                document.getElementById('hud-hp-val').innerText = `${Math.floor(player.hp)}`;
            }
            const dashFill = document.getElementById('hud-dash-fill');
            const dashPerc = Math.max(0, 1 - (player.dashCooldown / player.dashMaxCooldown));
            dashFill.style.width = `${dashPerc * 100}%`;
            dashFill.style.background = dashPerc >= 1 ? (player.hasAuraKill ? '#b500ff' : 'var(--neon-blue)') : '#444';

            requestAnimationFrame(gameLoop);
        }

        function updateHUD() {
            document.getElementById('hud-hp-val').innerText = `${Math.max(0, Math.floor(player.hp))}`;
            hudLevel.innerText = `LVL: ${player.lvl}`;
            hudXp.style.width = `${(player.xp / player.xpReq) * 100}%`;
            hudScore.innerText = `SCORE: ${score.toString().padStart(5, '0')}`;
            hudBonds.innerText = `WAR BONDS: ${player.coins}`;
            
            // Dash Bar updated dynamically in gameLoop
        }

        function getHighScores() {
            fetch('workinggame_api.php')
                .then(r => r.json())
                .then(d => {
                    const htm = d.scores.map(s => `<div class="score-row"><span>${s.name}</span><span>${s.score}</span></div>`).join('');
                    document.getElementById('startScoreList').innerHTML = htm || 'NO SCORES YET';
                    document.getElementById('scoreList').innerHTML = htm || 'NO SCORES YET';
                }).catch(e => console.error("Error fetching scores", e));
        }

        function submitScore(isLoggedIn) {
            let n;
            if(isLoggedIn) { n = loggedInUser; }
            else {
                n = document.getElementById('playerName').value;
                if(!n) n = "AAA";
                n = n.substring(0,3).toUpperCase();
            }
            
            document.getElementById('nameInputSection').classList.add('hidden');
            document.getElementById('scoreboard').classList.remove('hidden');

            fetch('workinggame_api.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ name: n, score: score })
            }).then(() => getHighScores())
              .catch(e => console.error("Error saving score", e));
        }

        // Init scores on load
        getHighScores();

        // High refresh rate resize handler for canvas CSS bounds
        window.addEventListener('resize', () => {
            const container = document.getElementById('gameContainer');
            const ar = GAME_W / GAME_H;
            let w = window.innerWidth; let h = window.innerHeight;
            if (w / h > ar) { w = h * ar; } else { h = w / ar; }
            canvas.style.width = w + 'px'; canvas.style.height = h + 'px';
        });
        window.dispatchEvent(new Event('resize'));
    </script>
</body>
</html>