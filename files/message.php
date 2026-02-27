<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ----------- THEME STATES ----------- */
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
            --item-hover: rgba(191, 50, 241, 0.15);
            --item-active: rgba(191, 50, 241, 0.3);
            --scrollbar-thumb: var(--accent);
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
            --item-hover: rgba(155, 89, 182, 0.1);
            --item-active: rgba(155, 89, 182, 0.2);
            --scrollbar-thumb: var(--accent);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: var(--bg-mesh-1);
            color: var(--text-main);
            height: 100vh;
            display: flex;
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

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        /* Layout */
        #app {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* Sidebar */
        aside {
            width: 350px;
            background: var(--glass);
            backdrop-filter: blur(30px) saturate(150%);
            -webkit-backdrop-filter: blur(30px) saturate(150%);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            box-shadow: var(--shadow);
        }

        .sidebar-header {
            padding: 20px;
            background: var(--header-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-family: var(--orbitron);
            background: linear-gradient(45deg, var(--accent), #d946ef);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: var(--glow);
            letter-spacing: 2px;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        h3 {
            margin: 20px 10px 10px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        /* Contact Item */
        .contact-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s;
            margin-bottom: 4px;
        }

        .contact-item:hover {
            background: var(--item-hover);
        }

        .contact-item.active {
            background: var(--item-active);
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            margin-right: 12px;
            overflow: hidden;
            border: 2px solid var(--border-color);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info {
            flex: 1;
        }

        .name {
            font-weight: 500;
            font-size: 0.95rem;
            display: block;
            color: var(--text-main);
        }

        .status {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .actions button {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: 0.2s;
            font-size: 1rem;
        }

        .btn-accept:hover {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .btn-decline:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .btn-add:hover {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        /* Chat Area */
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: transparent;
            position: relative;
            transition: all 0.5s ease;
        }

        /* Empty State */
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Chat Header */
        .chat-header {
            padding: 20px;
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            box-shadow: var(--shadow);
        }

        .chat-header .name {
            font-size: 1.1rem;
            color: var(--text-main);
        }

        /* Messages List */
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 80px 20px 90px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        /* Message Bubble */
        .msg {
            max-width: 70%;
            padding: 10px 16px;
            border-radius: 20px;
            font-size: 0.95rem;
            line-height: 1.4;
            position: relative;
            animation: slideIn 0.2s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .msg.me {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--accent), #774280);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: var(--glow);
        }

        .msg.them {
            align-self: flex-start;
            background: var(--msg-them-bg);
            color: var(--msg-them-text);
            border-bottom-left-radius: 4px;
        }

        .msg img,
        .msg video {
            width: 280px;
            height: 280px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 5px;
            display: block;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .msg .time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 4px;
            text-align: right;
            display: block;
        }

        .msg.them .time {
            text-align: left;
        }

        /* Message Row for Avatar */
        .msg-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 10px;
        }

        .msg-row.me {
            justify-content: flex-end;
        }

        .msg-row.them {
            justify-content: flex-start;
        }

        .msg-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 8px;
            flex-shrink: 0;
            background: #ccc;
        }

        .msg-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Input Area */
        .chat-input-area {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: var(--glass-strong);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: flex-end;
            gap: 10px;
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.2);
        }

        #chatForm {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--input-field-bg);
            padding: 10px 16px;
            border-radius: 30px;
            border: 1px solid var(--input-field-border);
        }

        input[type="text"] {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-main);
            font-size: 1rem;
            outline: none;
        }

        input[type="text"]::placeholder {
            color: var(--text-muted);
        }

        .btn-icon {
            background: transparent;
            border: none;
            color: var(--icon-color);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 4px;
            transition: 0.2s;
        }

        .btn-icon:hover {
            color: #d946ef;
            transform: scale(1.1);
        }

        #preview-container {
            position: absolute;
            bottom: 80px;
            left: 30px;
            background: #18181b;
            padding: 5px;
            border-radius: 10px;
            display: none;
        }

        #preview-img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
        }

        /* Responsive */
        .back-btn {
            display: none;
        }

        @media (max-width: 768px) {
            aside {
                width: 100%;
                position: absolute;
                top: 0;
                bottom: 0;
                z-index: 20;
                transform: translateX(0);
                transition: 0.3s;
            }

            aside.hidden {
                transform: translateX(-100%);
            }

            main {
                width: 100%;
            }

            .back-btn {
                display: block;
                margin-right: 10px;
                cursor: pointer;
            }

            .sidebar-header {
                display: flex;
                justify-content: space-between;
            }

            .chat-input-area {
                padding: 10px;
                background: var(--glass-strong);
                /* Less blur for performance */
            }

            #chatForm {
                padding: 6px 12px;
            }

            /* Adjust Chat for full width on mobile */
            div#chat-view[style*="display:flex"] {
                display: flex !important;
                width: 100vw;
                height: 100vh;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 30;
                /* Above sidebar */
                background: var(--bg-mesh-1);
            }
        }

        .theme-toggle-btn {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            margin-right: 15px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-toggle-btn img {
            width: 30px;
            height: 30px;
            transition: all 0.5s cubic-bezier(0.19, 1, 0.22, 1);
        }
    </style>
</head>

<body>

    <div id="app">
        <!-- Sidebar -->
        <aside>
            <div class="sidebar-header">
                <h1>Messenger</h1>
                <div style="display:flex;align-items:center;">
                    <button id="themeToggle" class="theme-toggle-btn" title="Toggle Theme">
                        <img src="imgandgifs/darklightmode.webp" alt="Toggle" style="filter: drop-shadow(var(--glow));">
                    </button>
                    <a href="index.php"
                        style="text-decoration:none; color:var(--text-main); font-family: var(--orbitron); font-size:0.8rem; padding:8px 16px; border:1px solid var(--border-color); border-radius:12px; transition:0.3s; background: var(--glass); text-transform: uppercase; letter-spacing: 1px;"
                        onmouseover="this.style.borderColor='var(--accent)';this.style.boxShadow='var(--glow)';this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.borderColor='var(--border-color)';this.style.boxShadow='none';this.style.transform='translateY(0)'">
                        Return ↩
                    </a>
                </div>
            </div>

            <div class="sidebar-content">
                <!-- Friend Requests -->
                <div id="section-requests">
                    <div id="list-requests"></div>
                </div>

                <!-- Friends -->
                <h3>Friends</h3>
                <div style="padding:0 10px 10px 10px;">
                    <input type="text" id="searchFriends" placeholder="Search friends..."
                        oninput="filterList('searchFriends', 'list-friends')"
                        style="width:100%; padding:8px; border-radius:8px; border:none; background:var(--input-bg); color:var(--text-main);">
                </div>
                <div id="list-friends"></div>

                <!-- Suggestions -->
                <h3>Suggested</h3>
                <div style="padding:0 10px 10px 10px;">
                    <div style="padding:0 10px 10px 10px;">
                        <input type="text" id="searchUsers" placeholder="Search users..."
                            oninput="filterList('searchUsers', 'list-others')"
                            style="width:100%; padding:8px; border-radius:8px; border:none; background:var(--input-bg); color:var(--text-main);">
                    </div>
                </div>
                <div id="list-others"></div>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main>
            <!-- Empty State -->
            <div id="empty-view" class="empty-state">
                <div style="font-size:3em">👋</div>
                <p>Select a friend to start chatting</p>
            </div>

            <!-- Active Chat -->
            <div id="chat-view" style="display:none; height:100%; flex-direction:column;">
                <div class="chat-header">
                    <button class="btn-icon back-btn" onclick="backToSidebar()">←</button>
                    <div class="avatar" id="chat-avatar" style="width:40px;height:40px;">#</div>
                    <span class="name" id="chat-name">User</span>
                </div>

                <div id="messages" class="messages-list"></div>

                <div class="chat-input-area">
                    <div id="preview-container">
                        <img id="preview-img">
                        <button type="button" id="remove-preview" class="btn-icon"
                            style="position:absolute; top:5px; right:5px; background:rgba(0,0,0,0.6); color:white; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:14px; padding:0;">✖</button>
                    </div>
                    <form id="chatForm">
                        <label for="fileInput" class="btn-icon">📎</label>
                        <input type="file" id="fileInput" hidden accept="image/*,video/*">

                        <input type="text" id="msgInput" placeholder="Type a message..." autocomplete="off">

                        <button type="submit" class="btn-icon">➤</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const state = {
            user: null, // My info
            friends: [],
            others: [],
            others: [],
            selectedFriendId: null,
            lastMessageId: 0,
            pollingInterval: null,
            globalPollingInterval: null,
            lastGlobalId: 0 // New global poller tracker
        };

        // DOM Elements
        const sidebar = document.querySelector('aside');
        const main = document.querySelector('main');
        const lists = {
            friends: document.getElementById('list-friends'),
            requests: document.getElementById('list-requests'),
            others: document.getElementById('list-others')
        };
        const chatView = document.getElementById('chat-view');
        const emptyView = document.getElementById('empty-view');
        const messagesContainer = document.getElementById('messages');
        const chatHeaderName = document.getElementById('chat-name');
        const chatHeaderAvatar = document.getElementById('chat-avatar');

        // State for locking
        let isFetchingChat = false;
        let isSending = false;

        // Init
        async function init() {
            const res = await api('init');
            if (res.status === 'success') {
                state.user = res.me;
                // Request notification permission
                if ("Notification" in window) {
                    Notification.requestPermission();
                }
                // Initial render
                processContacts(res);
                renderSidebar();
                startGlobalPolling(); // Start the global listener
            } else {
                window.location.href = 'login.php';
            }
        }

        function processContacts(data) {
            // Store reference for diffing
            state.prevFriends = JSON.stringify(state.friends);
            state.friends = data.friends;
            state.others = data.others;
        }

        // Render Sidebar
        function renderSidebar() {
            // Save scroll position
            const scrollPos = lists.friends.scrollTop;

            // Clear only if needed, or use smart replacement. 
            // For now, simpler: clear and re-render but optimized.
            lists.friends.innerHTML = '';
            lists.requests.innerHTML = '';
            lists.others.innerHTML = '';

            const accepted = state.friends.filter(f => f.status === 'accepted');
            const pending = state.friends.filter(f => f.status === 'pending');
            const incoming = pending.filter(f => f.is_incoming);

            if (accepted.length === 0) lists.friends.innerHTML = '<div style="padding:10px; opacity:0.5; font-size:0.8em">No friends yet. Add some below!</div>';
            accepted.forEach(f => {
                const div = createContactEl(f, 'chat');
                if (state.selectedFriendId == f.id) div.classList.add('active');
                lists.friends.appendChild(div);
            });

            if (incoming.length > 0) {
                incoming.forEach(f => {
                    lists.requests.appendChild(createContactEl(f, 'request'));
                });
            }

            state.others.forEach(u => {
                lists.others.appendChild(createContactEl(u, 'add'));
            });

            // Restore scroll
            lists.friends.scrollTop = scrollPos;

            // Re-apply search filter if active
            if (searchFriendsInput.value) filterList('searchFriends', 'list-friends');
            if (searchUsersInput.value) filterList('searchUsers', 'list-others');
        }

        function createContactEl(user, type) {
            const d = document.createElement('div');
            d.className = 'contact-item';

            const initials = user.username.substring(0, 2).toUpperCase();
            const avatarContent = user.profile_img ? `<img src="${user.profile_img}" alt="${user.username}">` : initials;

            let buttons = '';
            if (type === 'request') {
                buttons = `
                <button class="btn-accept" onmousedown="event.stopPropagation(); action(event, 'accept', ${user.relationship_id})">✔</button>
                <button class="btn-decline" onmousedown="event.stopPropagation(); action(event, 'decline', ${user.relationship_id})">✖</button>
            `;
            } else if (type === 'add') {
                buttons = `
                <button class="btn-add" onmousedown="event.stopPropagation(); action(event, 'request', ${user.id})">➕</button>
            `;
            }

            d.innerHTML = `
            <div class="avatar">${avatarContent}</div>
            <div class="info">
                <span class="name">${user.username}</span>
                <span class="status">${type === 'chat' ? 'Tap to chat' : (type === 'add' ? 'Suggested' : 'Requesting')}</span>
            </div>
            <div class="actions">${buttons}</div>
        `;

            if (type === 'chat') {
                d.onclick = () => selectFriend(user);
            }

            return d;
        }

        // API Call Wrapper
        async function api(action, payload = {}) {
            const fd = new FormData();
            fd.append('action', action);
            for (let k in payload) fd.append(k, payload[k]);

            const res = await fetch('api.php', { method: 'POST', body: fd });
            return await res.json();
        }

        // Actions
        async function action(e, type, id) {
            if (type === 'request') {
                await api('friend_action', { type: 'request', target_id: id });
            } else {
                await api('friend_action', { type: type, rel_id: id });
            }
            // Force immediate refresh
            const res = await api('init');
            processContacts(res);
            renderSidebar();
        }

        // Chat Functions
        async function selectFriend(user) {
            state.selectedFriendId = user.id;
            state.selectedFriendName = user.username;
            state.lastMessageId = 0;
            isFetchingChat = false;

            chatHeaderName.innerText = user.username;
            const initials = user.username.substring(0, 2).toUpperCase();
            chatHeaderAvatar.innerHTML = user.profile_img ? `<img src="${user.profile_img}">` : initials;

            emptyView.style.display = 'none';
            chatView.style.display = 'flex';
            messagesContainer.innerHTML = '';

            if (window.innerWidth <= 768) sidebar.classList.add('hidden');
            renderSidebar();
            await loadChat(true);
        }

        async function loadChat(forceScroll = false) {
            if (!state.selectedFriendId || isFetchingChat) return;
            isFetchingChat = true;

            try {
                // If it's the first load for this chat, we want instant scroll and no animation
                const isInitialLoad = (messagesContainer.children.length === 0);

                const res = await fetch(`api.php?action=fetch_chat&friend_id=${state.selectedFriendId}&last_id=${state.lastMessageId}`);
                const data = await res.json();

                if (data.status === 'success' && data.messages.length > 0) {
                    // Disable smooth scroll for initial load
                    if (isInitialLoad) messagesContainer.style.scrollBehavior = 'auto';

                    let newMsg = false;
                    data.messages.forEach(m => {
                        if (document.querySelector(`.msg[data-id="${m.id}"]`)) return;

                        // Don't animate if initial load
                        appendMessage(m, false, !isInitialLoad);

                        if (m.id > state.lastMessageId) state.lastMessageId = m.id;
                        if (m.id > (state.lastGlobalId || 0)) {
                            state.lastGlobalId = m.id;
                            localStorage.setItem('lastGlobalId', m.id);
                        }
                        newMsg = true;
                    });

                    if (newMsg) {
                        document.querySelectorAll('.msg.optimistic').forEach(e => {
                            const row = e.closest('.msg-row');
                            if (row) row.remove();
                            else e.remove();
                        });

                        if (forceScroll || isInitialLoad) {
                            scrollToBottom(true); // Instant
                        } else if (messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 100) {
                            scrollToBottom(false); // Smooth
                        }
                    }

                    // Re-enable smooth scroll after a tick, if it was disabled
                    if (isInitialLoad) {
                        requestAnimationFrame(() => {
                            messagesContainer.style.scrollBehavior = 'smooth';
                        });
                    }
                }
            } catch (err) {
                console.error(err);
            } finally {
                isFetchingChat = false;
            }
        }

        // Global Polling for Notifications & Updates
        function startGlobalPolling() {
            if (state.globalPollingInterval) clearInterval(state.globalPollingInterval);

            // Initialize from storage or 0
            state.lastGlobalId = parseInt(localStorage.getItem('lastGlobalId') || 0);
            let isFirstPoll = (state.lastGlobalId === 0);

            state.globalPollingInterval = setInterval(async () => {
                const res = await api('check_updates', { last_id: state.lastGlobalId });

                if (res.status === 'success' && res.updates.length > 0) {
                    let maxId = state.lastGlobalId;

                    res.updates.forEach(m => {
                        if (m.id > maxId) maxId = m.id;

                        // Check if this message belongs to the CURRENTLY OPEN chat
                        // Case A: I received it from selectedFriend (m.sender_id == selectedFriendId)
                        // Case B: I sent it to selectedFriend (m.sender_id == me && m.receiver_id == selectedFriendId)

                        const isFromFriend = (state.selectedFriendId && m.sender_id == state.selectedFriendId);
                        const isFromMeToFriend = (state.selectedFriendId && m.sender_id == state.user.user_id && m.receiver_id == state.selectedFriendId);

                        if (isFromFriend || isFromMeToFriend) {
                            if (m.id > state.lastMessageId) state.lastMessageId = m.id;

                            if (!document.querySelector(`.msg[data-id="${m.id}"]`)) {
                                appendMessage({
                                    id: m.id,
                                    is_me: (m.sender_id == state.user.user_id),
                                    message: m.message,
                                    media: m.media,
                                    time: m.time,
                                    sender_img: m.sender_img
                                });
                                scrollToBottom();

                                // Remove optimistic messages if we received my own message back
                                if (isFromMeToFriend) {
                                    document.querySelectorAll('.msg.optimistic').forEach(e => {
                                        const row = e.closest('.msg-row');
                                        if (row) row.remove();
                                        else e.remove();
                                    });
                                }
                            }
                        } else {
                            // Notify only if it's NOT from me
                            if (!isFirstPoll && m.sender_id != state.user.user_id) {
                                notifyMessage(m);
                            }
                        }
                    });

                    state.lastGlobalId = maxId;
                    localStorage.setItem('lastGlobalId', maxId);
                }

                // After first run, we allow notifications
                isFirstPoll = false;

            }, 3000);
        }

        function notifyMessage(m) {
            if (!("Notification" in window) || Notification.permission !== "granted") return;

            const notification = new Notification(`New message from ${m.sender_name || 'Friend'}`, {
                body: m.message || (m.media ? "[Media]" : ""),
                icon: m.sender_img || "/imgandgifs/logo.png"
            });

            notification.onclick = () => {
                window.focus();
                notification.close();
                // Logic to switch to that friend?
                // We'd need to find them in state.friends and call selectFriend
                const friend = state.friends.find(f => f.id == m.sender_id);
                if (friend) selectFriend(friend);
            };
        }

        function appendMessage(m, optimistic = false, animate = true) {
            // Determine avatar
            let avatarUrl = '';
            if (m.is_me) {
                avatarUrl = state.user.profile_img;
            } else {
                // For received messages, we might have it in `m.sender_img` (from check_updates) 
                // OR we look it up from `state.friends` (from fetch_chat)
                if (m.sender_img) avatarUrl = m.sender_img;
                else {
                    // Fallback
                    const f = state.friends.find(f => f.id == state.selectedFriendId);
                    avatarUrl = f ? f.profile_img : '/imgandgifs/login.png';
                }
            }

            const row = document.createElement('div');
            row.className = `msg-row ${m.is_me ? 'me' : 'them'}`;

            // Avatar HTML
            const avatarHtml = `<div class="msg-avatar"><img src="${avatarUrl}"></div>`;

            // Message Bubble
            const d = document.createElement('div');
            d.className = `msg ${m.is_me ? 'me' : 'them'} ${optimistic ? 'optimistic' : ''}`;

            // Only add animation if requested
            if (animate) d.style.animation = 'slideIn 0.2s ease';
            else d.style.animation = 'none';

            if (optimistic) d.style.opacity = '0.7';
            if (!optimistic) d.setAttribute('data-id', m.id);

            let content = '';
            if (m.message) content += `<div>${m.message}</div>`;
            if (m.media) {
                if (m.media.match(/mp4|webm/)) content += `<video src="${m.media}" controls></video>`;
                else content += `<img src="${m.media}" onload="scrollToBottom()">`; // Auto-scroll on image load
            }
            if (m.time) content += `<span class="time">${m.time}</span>`;

            d.innerHTML = content;

            // Assemble
            if (m.is_me) {
                // Me: Just the message, no avatar (Messenger style)
                row.appendChild(d);
            } else {
                // Them: Avatar then Message
                row.appendChild(document.createRange().createContextualFragment(avatarHtml));
                row.appendChild(d);
            }

            messagesContainer.appendChild(row);
            // We rely on caller to scroll, except for optimistic
            if (optimistic) scrollToBottom();
        }
        function scrollToBottom(instant = false) {
            if (instant) {
                messagesContainer.scrollTo({ top: messagesContainer.scrollHeight, behavior: 'auto' });
            } else {
                messagesContainer.scrollTo({ top: messagesContainer.scrollHeight, behavior: 'smooth' });
            }
        }

        // Sending
        const chatForm = document.getElementById('chatForm');
        const fileInput = document.getElementById('fileInput');
        const msgInput = document.getElementById('msgInput');
        const previewImg = document.getElementById('preview-img');
        const previewCont = document.getElementById('preview-container');
        const removePreviewBtn = document.getElementById('remove-preview');
        const sendBtn = chatForm.querySelector('button[type="submit"]');

        fileInput.onchange = () => {
            if (fileInput.files[0]) {
                previewImg.src = URL.createObjectURL(fileInput.files[0]);
                previewCont.style.display = 'block';
                // Auto-focus input so Enter key sends immediately
                msgInput.focus();
            }
        };

        removePreviewBtn.onclick = () => {
            fileInput.value = '';
            previewImg.src = '';
            previewCont.style.display = 'none';
        };

        chatForm.onsubmit = async (e) => {
            e.preventDefault();
            if (isSending) return; // Prevent double submit

            const msgVal = msgInput.value.trim();
            const fileVal = fileInput.files[0];

            if (!msgVal && !fileVal) return;

            isSending = true;
            sendBtn.style.opacity = '0.5';

            // OPTIMISTIC UPDATE
            const now = new Date();
            const timeStr = now.getHours() + ":" + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();

            let mediaUrl = null;
            if (fileVal) mediaUrl = URL.createObjectURL(fileVal);

            // Show immediately
            appendMessage({
                is_me: true,
                message: msgVal,
                media: mediaUrl,
                time: timeStr
            }, true);
            scrollToBottom();

            // Prepare Send
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('receiver_id', state.selectedFriendId);
            fd.append('message', msgVal);
            if (fileVal) fd.append('media', fileVal);

            // Clear Inputs
            msgInput.value = '';
            fileInput.value = '';
            previewCont.style.display = 'none';

            try {
                // Send
                await fetch('api.php', { method: 'POST', body: fd });
                // Reload to confirm
                await loadChat(false);
            } finally {
                isSending = false;
                sendBtn.style.opacity = '1';
            }
        };



        function backToSidebar() {
            sidebar.classList.remove('hidden');
            state.selectedFriendId = null;
            chatView.style.display = 'none';
            emptyView.style.display = 'flex';
        }

        // Search Logic
        const searchFriendsInput = document.getElementById('searchFriends');
        const searchUsersInput = document.getElementById('searchUsers');

        function filterList(inputId, listId) {
            const val = document.getElementById(inputId).value.toLowerCase();
            const items = document.querySelectorAll(`#${listId} .contact-item`);
            items.forEach(el => {
                const name = el.querySelector('.name').innerText.toLowerCase();
                el.style.display = name.includes(val) ? 'flex' : 'none';
            });
        }

        init();
        // Attach to inputs? Wait, HTML needs IDs first.
        // We will attach event listeners in init



        // Resize handling
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('hidden');
            }
        });

        // Theme logic
        const toggleBtn = document.getElementById("themeToggle");
        const body = document.body;

        function updateThemeUI() {
            const isDark = body.classList.contains('dark');
            const img = toggleBtn.querySelector('img');
            if (img) {
                img.style.transform = isDark ? 'rotate(180deg) scale(1)' : 'rotate(0deg) scale(1.1)';
            }
        }

        const savedTheme = localStorage.getItem("theme") || "dark";
        body.classList.add(savedTheme);
        updateThemeUI();

        toggleBtn.addEventListener("click", () => {
            if (body.classList.contains("dark")) {
                body.classList.replace("dark", "bright");
                localStorage.setItem("theme", "bright");
            } else {
                body.classList.replace("bright", "dark");
                localStorage.setItem("theme", "dark");
            }
            updateThemeUI();
        });

        updateThemeUI();
    </script>
</body>

</html>