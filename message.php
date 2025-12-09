<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: auth.php?redirect=message.php");
    exit();
}

// ---- DATABASE CONNECTION ----
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";
$conn = new mysqli($servername,$db_username,$db_password,$database);
if($conn->connect_error) die("Connection failed");

// ---- CURRENT USER ----
$user_id = $_SESSION['user_id'];

// ---- FRIENDS ----
$friends = [];
$res = $conn->query("SELECT f.id,f.friend_id,f.status,u.username,u.profile_img
                     FROM friends f
                     JOIN users u ON u.user_id=f.friend_id
                     WHERE f.user_id=$user_id");
while($row = $res->fetch_assoc()) $friends[] = $row;

// ---- ALL USERS ----
$all_users = [];
$res2 = $conn->query("SELECT user_id,username FROM users WHERE user_id!=$user_id");
while($row2 = $res2->fetch_assoc()) $all_users[] = $row2;

// ---- HANDLE FRIEND REQUESTS ----
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['friend_action'])){
    $fid = intval($_POST['friend_id']);
    $action = $_POST['friend_action'];
    if($action=='accept'){
        $stmt=$conn->prepare("UPDATE friends SET status='accepted' WHERE id=?");
        $stmt->bind_param("i",$fid);
        $stmt->execute();
    } elseif($action=='decline'){
        $stmt=$conn->prepare("UPDATE friends SET status='declined' WHERE id=?");
        $stmt->bind_param("i",$fid);
        $stmt->execute();
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Messenger</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:'Poppins',sans-serif;background:#1a0a2b;color:#fff;height:100vh;display:flex;flex-direction:column;}
header{display:flex;align-items:center;padding:12px 20px;background:#6a1b9a;box-shadow:0 2px 8px rgba(0,0,0,0.3);}
header h1{flex:1;font-size:1.5em;margin:0;}
header a{color:#fff;text-decoration:none;font-weight:600;}
.container{flex:1;display:flex;overflow:hidden;}
.sidebar{width:280px;background:#7b1fa2;padding:15px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;}
.sidebar input{padding:8px;border-radius:8px;border:none;outline:none;margin-bottom:5px;}
.friend,.user{padding:10px;background:#9c27b0;border-radius:8px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:0.2s;}
.friend:hover,.user:hover{background:#ba68c8;}
.friend.hidden,.user.hidden{display:none;}
.chat{flex:1;display:flex;flex-direction:column;border-left:2px solid #ba68c8;background:rgba(255,255,255,0.03);}
.chat-messages{flex:1;padding:12px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;}
.chat-message{padding:10px;border-radius:10px;background:rgba(255,255,255,0.08);max-width:70%;word-wrap:break-word;}
.chat-message img,.chat-message video{max-width:250px;border-radius:8px;display:block;margin-top:5px;}
.chat-message.me{align-self:flex-end;background:rgba(106,27,154,0.6);}
.chat-input{display:flex;padding:12px;border-top:2px solid #ba68c8;gap:6px;align-items:center;}
.chat-input input[type=text]{flex:1;padding:10px;border-radius:8px;border:none;outline:none;}
.chat-input input[type=file]{display:none;}
.chat-input label{cursor:pointer;background:#ba68c8;padding:8px 12px;border-radius:8px;}
.chat-input button{padding:8px 14px;border:none;border-radius:8px;background:#ba68c8;color:#fff;cursor:pointer;transition:0.3s;}
.chat-input button:hover{background:#9c27b0;}
.friend span button{margin-left:4px;padding:4px 6px;border:none;border-radius:5px;cursor:pointer;background:#4caf50;color:#fff;}
.friend span button.decline{background:#f44336;}
@media(max-width:768px){.container{flex-direction:column}.sidebar{width:100%;flex-direction:row;overflow-x:auto;gap:6px}.chat{border-left:none;border-top:2px solid #ba68c8;}}
</style>
</head>
<body>

<header>
<h1>Messenger</h1>
<a href="index.php">← Back</a>
</header>

<div class="container">
<div class="sidebar">
<input type="text" id="searchFriends" placeholder="Search friends...">
<input type="text" id="searchUsers" placeholder="Search users...">
<?php foreach($friends as $friend): ?>
<div class="friend" data-id="<?php echo $friend['friend_id']; ?>" data-status="<?php echo $friend['status']; ?>">
<span><?php echo htmlspecialchars($friend['username']); ?></span>
<?php if($friend['status']=='pending'): ?>
<span>
<button class="accept-btn" data-id="<?php echo $friend['id']; ?>">✔</button>
<button class="decline-btn decline" data-id="<?php echo $friend['id']; ?>">✖</button>
</span>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php foreach($all_users as $user): ?>
<div class="user" data-id="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['username']); ?></div>
<?php endforeach; ?>
</div>

<div class="chat">
<div class="chat-messages" id="chatMessages"></div>
<form class="chat-input" id="chatForm" enctype="multipart/form-data">
<input type="text" id="chatInput" placeholder="Type a message..." autocomplete="off">
<label for="chatFile">📎</label>
<input type="file" id="chatFile" accept="image/*,video/*">
<button type="submit">Send</button>
</form>
</div>
</div>

<script>
let selectedFriendId=null;
const chatMessages=document.getElementById('chatMessages');
const friends=document.querySelectorAll('.friend');
const users=document.querySelectorAll('.user');
const chatForm=document.getElementById('chatForm');
const chatInput=document.getElementById('chatInput');
const chatFile=document.getElementById('chatFile');

// -- Friend selection
friends.forEach(f=>{
    f.addEventListener('click',()=>{
        if(f.dataset.status!=='accepted') return alert('Friend request not accepted!');
        selectedFriendId=f.dataset.id;
        loadMessages();
    });
});

// -- User click for requests
users.forEach(u=>{
    u.addEventListener('click',()=>{ alert('Send a friend request to '+u.textContent+' first.'); });
});

// -- Search bars
document.getElementById('searchFriends').addEventListener('input',()=>{
    const val=document.getElementById('searchFriends').value.toLowerCase();
    friends.forEach(f=>f.classList.toggle('hidden',!f.textContent.toLowerCase().includes(val)));
});
document.getElementById('searchUsers').addEventListener('input',()=>{
    const val=document.getElementById('searchUsers').value.toLowerCase();
    users.forEach(u=>u.classList.toggle('hidden',!u.textContent.toLowerCase().includes(val)));
});

// -- Friend requests
document.querySelectorAll('.accept-btn').forEach(btn=>{
    btn.addEventListener('click',async e=>{
        e.stopPropagation();
        await fetch('message.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'friend_action=accept&friend_id='+btn.dataset.id});
        btn.parentElement.remove();
    });
});
document.querySelectorAll('.decline-btn').forEach(btn=>{
    btn.addEventListener('click',async e=>{
        e.stopPropagation();
        await fetch('message.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'friend_action=decline&friend_id='+btn.dataset.id});
        btn.parentElement.remove();
    });
});

// -- Send messages
chatForm.addEventListener('submit',async e=>{
    e.preventDefault();
    if(!selectedFriendId) return alert('Select a friend!');
    const msg=chatInput.value.trim();
    const file=chatFile.files[0];
    const formData=new FormData();
    formData.append('receiver_id',selectedFriendId);
    formData.append('message',msg);
    if(file) formData.append('media',file);

    await fetch('fetch_messages.php',{method:'POST',body:formData});
    chatInput.value=''; chatFile.value='';
    loadMessages();
});

// -- Load messages
async function loadMessages(){
    if(!selectedFriendId) return;
    const res=await fetch('fetch_messages.php?friend_id='+selectedFriendId);
    const data=await res.json();
    chatMessages.innerHTML='';
    data.forEach(m=>{
        const div=document.createElement('div');
        div.className='chat-message';
        if(m.sender_id==<?php echo $user_id; ?>) div.classList.add('me');
        let html=`<strong>${m.sender}:</strong> ${m.message}`;
        if(m.media){
            if(m.media.match(/\.(mp4|webm)$/i)) html+=`<video src="${m.media}" controls></video>`;
            else html+=`<img src="${m.media}">`;
        }
        div.innerHTML=html;
        chatMessages.appendChild(div);
    });
    chatMessages.scrollTop=chatMessages.scrollHeight;
}

// -- Auto-refresh
setInterval(loadMessages,3000);
</script>
</body>
</html>

