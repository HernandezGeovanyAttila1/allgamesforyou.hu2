<?php
session_start();
if(!isset($_SESSION['user_id'])) exit();

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";
$conn = new mysqli($servername,$db_username,$db_password,$database);

$query = $_GET['query'] ?? '';
$query = $conn->real_escape_string($query);
$user_id = $_SESSION['user_id'];

$res = $conn->query("SELECT user_id, username 
                     FROM users 
                     WHERE username LIKE '%$query%' AND user_id != $user_id 
                     LIMIT 10");
$results=[];
while($row=$res->fetch_assoc()) $results[]=$row;
echo json_encode($results);

