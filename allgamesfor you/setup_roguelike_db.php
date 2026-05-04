<?php
require_once 'db.php';

// Script to create the highscores table for the roguelike game

$sql = "CREATE TABLE IF NOT EXISTS workinggame_highscores (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    player_name VARCHAR(50) NOT NULL,
    score INT(11) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'workinggame_highscores' created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

echo "<br><a href='workinggame.php'>Go to Game</a>";

$conn->close();
?>
