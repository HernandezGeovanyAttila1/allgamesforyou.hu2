<?php
session_start();
if(!isset($_SESSION['user_id'])) exit();

// ---- DATABASE ----
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";

$conn = new mysqli($servername,$db_username,$db_password,$database);
if($conn->connect_error) die("Connection failed");

// ---- SEND MESSAGE ----
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user_id = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    $media_path = null;

    if(isset($_FILES['media']) && $_FILES['media']['size'] > 0){
        $allowed = ['image/jpeg','image/png','image/gif','video/mp4','video/webm'];
        if(in_array($_FILES['media']['type'],$allowed) && $_FILES['media']['size'] <= 10*1024*1024){
            $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
            $file_name = 'uploads/'.uniqid().'_'.time().'.'.$ext;
            if(!is_dir('uploads')) mkdir('uploads',0777,true);
            move_uploaded_file($_FILES['media']['tmp_name'],$file_name);
            $media_path = $file_name;
        }
    }

    // Only allow sending if users are friends
    $check = $conn->query("SELECT * FROM friends WHERE 
        ((user_id=$user_id AND friend_id=$receiver_id) OR (user_id=$receiver_id AND friend_id=$user_id)) 
        AND status='accepted'")->num_rows;

    if($check > 0){
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, media, created_at) VALUES (?,?,?,?,NOW())");
        $stmt->bind_param("iiss",$user_id,$receiver_id,$message,$media_path);
        $stmt->execute();
        $stmt->close();
    }
    exit();
}

// ---- FETCH MESSAGES ----
$friend_id = intval($_GET['friend_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$messages = [];

if($friend_id > 0){
    $stmt = $conn->prepare("SELECT m.*, u.username AS sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.user_id 
        WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) 
        ORDER BY created_at ASC");
    $stmt->bind_param("iiii",$user_id,$friend_id,$friend_id,$user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row=$res->fetch_assoc()){
        $messages[] = [
            'sender' => $row['sender_name'],
            'message' => htmlspecialchars($row['message']),
            'media' => $row['media']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($messages);

