<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Global & Reset */
        :root {
            /* Default Dark Mode */
            --bg-body: #0f0f13;
            --text-main: #e0e0e0;
            --bg-sidebar: #1a1a20;
            --border-color: #2a2a35;
            --header-bg: rgba(26, 26, 32, 0.95);
            --input-bg: rgba(255, 255, 255, 0.1);
            --text-muted: #71717a;
            --item-hover: #27272a;
            --item-active: #3f3f46;
            --scrollbar-thumb: #333;
            --chat-bg: #0f0f13;
            --chat-header-bg: rgba(15, 15, 19, 0.8);
            --msg-them-bg: #27272a;
            --msg-them-text: #e4e4e7;
            --input-area-bg: #0f0f13;
            --input-field-bg: #27272a;
            --input-field-border: #3f3f46;
            --icon-color: #a855f7;
        }

        body.bright {
            --bg-body: #fdfdfd;
            --text-main: #1f1f1f;
            --bg-sidebar: #f4f4f5;
            --border-color: #e4e4e7;
            --header-bg: rgba(244, 244, 245, 0.95);
            --input-bg: rgba(0, 0, 0, 0.05);
            --text-muted: #a1a1aa;
            --item-hover: #e4e4e7;
            --item-active: #d4d4d8;
            --scrollbar-thumb: #ccc;
            --chat-bg: #ffffff;
            --chat-header-bg: rgba(255, 255, 255, 0.8);
            --msg-them-bg: #f4f4f5;
            --msg-them-text: #1f1f1f;
            --input-area-bg: #ffffff;
            --input-field-bg: #f4f4f5;
            --input-field-border: #e4e4e7;
            --icon-color: #9333ea;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            overflow: hidden;
            transition: background 0.3s, color 0.3s;
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
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: background 0.3s, border-color 0.3s;
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
            background: linear-gradient(45deg, #a855f7, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            background: var(--chat-bg);
            position: relative;
            transition: background 0.3s;
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
            padding: 15px 20px;
            background: var(--chat-header-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            transition: background 0.3s, border-color 0.3s;
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
            background: linear-gradient(135deg, #a855f7, #d946ef);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .msg.them {
            align-self: flex-start;
            background: var(--msg-them-bg);
            color: var(--msg-them-text);
            border-bottom-left-radius: 4px;
        }

        .msg img,
        .msg video {
            max-width: 100%;
            border-radius: 12px;
            margin-top: 5px;
            display: block;
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

        /* Input Area */
        .chat-input-area {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: var(--input-area-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: flex-end;
            gap: 10px;
            transition: background 0.3s, border-color 0.3s;
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
        }

        .theme-toggle-btn {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 10px;
            transition: 0.2s;
        }

        .theme-toggle-btn:hover {
            background: var(--item-hover);
            color: var(--text-main);
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
                        💡
                    </button>
                    <a href="index.php"
                        style="text-decoration:none; color:var(--text-muted); font-size:0.9rem; padding:8px 12px; border:1px solid var(--border-color); border-radius:8px; transition:0.2s;"
                        onmouseover="this.style.borderColor='#a855f7';this.style.color='var(--text-main)'"
                        onmouseout="this.style.borderColor='var(--border-color)';this.style.color='var(--text-muted)'">
                        Exit ↩
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
                    <button class="btn-icon back-btn" onclick="backToSidebar()" style="display:none">←</button>
                    <div class="avatar" style="width:32px;height:32px;font-size:0.8em">#</div>
                    <span class="name" id="chat-name">User</span>
                </div>

                <div id="messages" class="messages-list"></div>

                <div class="chat-input-area">
                    <div id="preview-container">
                        <img id="preview-img">
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
            selectedFriendId: null,
            lastMessageId: 0,
            pollingInterval: null
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

        // State for locking
        let isFetchingChat = false;
        let isSending = false;

        // Init
        async function init() {
            const res = await api('init');
            if (res.status === 'success') {
                state.user = res.me;
                // Initial render
                processContacts(res);
                renderSidebar();
                startPolling();
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

            const avatar = user.username.substring(0, 2).toUpperCase();

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
            <div class="avatar">${avatar}</div>
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
            state.lastMessageId = 0;
            isFetchingChat = false;

            chatHeaderName.innerText = user.username;
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
                const res = await fetch(`api.php?action=fetch_chat&friend_id=${state.selectedFriendId}&last_id=${state.lastMessageId}`);
                const data = await res.json();

                if (data.status === 'success' && data.messages.length > 0) {
                    let newMsg = false;
                    data.messages.forEach(m => {
                        // DEDUPLICATION: Check if message ID already exists
                        if (document.querySelector(`.msg[data-id="${m.id}"]`)) return;

                        appendMessage(m);
                        if (m.id > state.lastMessageId) state.lastMessageId = m.id;
                        newMsg = true;
                    });

                    if (newMsg) {
                        // Remove optimistic messages once we have real ones
                        document.querySelectorAll('.msg.optimistic').forEach(e => e.remove());

                        if (forceScroll) scrollToBottom();
                        else if (messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 100) scrollToBottom();
                    }
                }
            } catch (err) {
                console.error(err);
            } finally {
                isFetchingChat = false;
            }
        }

        function appendMessage(m, optimistic = false) {
            const d = document.createElement('div');
            d.className = `msg ${m.is_me ? 'me' : 'them'} ${optimistic ? 'optimistic' : ''}`;
            if (optimistic) d.style.opacity = '0.7';
            if (!optimistic) d.setAttribute('data-id', m.id);

            let content = '';
            if (m.message) content += `<div>${m.message}</div>`;
            if (m.media) {
                if (m.media.match(/mp4|webm/)) content += `<video src="${m.media}" controls></video>`;
                else content += `<img src="${m.media}">`;
            }

            d.innerHTML = `
            ${content}
            <span class="time">${m.time} ${optimistic ? '...' : ''}</span>
        `;
            messagesContainer.appendChild(d);
        }

        function scrollToBottom() {
            messagesContainer.scrollTo({ top: messagesContainer.scrollHeight, behavior: 'smooth' });
        }

        // Sending
        const chatForm = document.getElementById('chatForm');
        const fileInput = document.getElementById('fileInput');
        const msgInput = document.getElementById('msgInput');
        const previewImg = document.getElementById('preview-img');
        const previewCont = document.getElementById('preview-container');
        const sendBtn = chatForm.querySelector('button[type="submit"]');

        fileInput.onchange = () => {
            if (fileInput.files[0]) {
                previewImg.src = URL.createObjectURL(fileInput.files[0]);
                previewCont.style.display = 'block';
            }
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

        // Polling
        function startPolling() {
            // Friends Polling
            setInterval(async () => {
                try {
                    const res = await api('init');
                    const newFriends = JSON.stringify(res.friends);
                    if (newFriends !== state.prevFriends) {
                        processContacts(res);
                        renderSidebar();
                    }
                } catch (e) { }
            }, 1000);

            // Chat Polling
            setInterval(async () => {
                if (state.selectedFriendId) await loadChat();
            }, 1000);
        }

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

        init();

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