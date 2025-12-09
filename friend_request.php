
<?php
session_start();
if(!isset($_SESSION['user_id'])) exit();
$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";
$conn = new mysqli($servername,$db_username,$db_password,$database);

$data=json_decode(file_get_contents('php://input'),true);
$me=$_SESSION['user_id'];

if($data['action']=='request'){
    $fid=intval($data['friend_id']);
    $conn->query("INSERT INTO friends (user_id, friend_id, status) VALUES ($me,$fid,'pending')");
} elseif(in_array($data['action'],['accept','decline'])){
    $row_id=intval($data['friend_row_id']);
    if($data['action']=='accept'){
        $conn->query("UPDATE friends SET status='accepted' WHERE id=$row_id");
        // Also create reciprocal friend
        $row=$conn->query("SELECT friend_id FROM friends WHERE id=$row_id")->fetch_assoc();
        $friend=$row['friend_id'];
        $conn->query("INSERT INTO friends (user_id, friend_id, status) VALUES ($friend,$me,'accepted')");
    } else {
        $conn->query("DELETE FROM friends WHERE id=$row_id");
    }
}
