<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$db_username = "skdneoaa";
$db_password = "t3YnVb0HN**40f";
$database = "skdneoaa_Felhasznalok";
$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die(json_encode([]));

$res = $conn->query("SELECT g.*, GROUP_CONCAT(gc.category SEPARATOR ',') AS categories FROM games g LEFT JOIN game_categories gc ON g.game_id = gc.game_id GROUP BY g.game_id ORDER BY g.created_at DESC LIMIT 100");
$out = [];
while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode($out);
