<?php
$servername = "localhost";
$username = "root";  // XAMPP-on általában root
$password = "";      // és nincs jelszó
$dbname = "allgames_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Kapcsolódási hiba: " . $conn->connect_error);
}
?>
