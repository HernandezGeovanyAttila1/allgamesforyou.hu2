<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';

echo "<h2>Database Schema Update</h2>";

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add reset_token_hash column
$sql1 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(64) NULL";
if ($conn->query($sql1) === TRUE) {
    echo "Column 'reset_token_hash' checked/created successfully.<br>";
} else {
    echo "Error checking/creating 'reset_token_hash': " . $conn->error . "<br>";
}

// Add reset_token_expires_at column
$sql2 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires_at DATETIME NULL";
if ($conn->query($sql2) === TRUE) {
    echo "Column 'reset_token_expires_at' checked/created successfully.<br>";
} else {
    echo "Error checking/creating 'reset_token_expires_at': " . $conn->error . "<br>";
}

// Add profile_img column
$sql3 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_img VARCHAR(255) DEFAULT '/imgandgifs/login.png'";
if ($conn->query($sql3) === TRUE) {
    echo "Column 'profile_img' checked/created successfully.<br>";
} else {
    echo "Error checking/creating 'profile_img': " . $conn->error . "<br>";
}

// Create reports table if not exists
$sql4 = "CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    headline VARCHAR(255) NOT NULL,
    report TEXT NOT NULL,
    reply TEXT DEFAULT NULL,
    status ENUM('pending', 'answered', 'closed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
if ($conn->query($sql4) === TRUE) {
    echo "Table 'reports' checked/created successfully.<br>";
} else {
    echo "Error checking/creating 'reports': " . $conn->error . "<br>";
}

// Add headline column to reports if it doesn't exist (safety)
$sql5 = "SHOW COLUMNS FROM reports LIKE 'headline'";
$res5 = $conn->query($sql5);
if ($res5 && $res5->num_rows == 0) {
    $conn->query("ALTER TABLE reports ADD COLUMN headline VARCHAR(255) NOT NULL AFTER user_id");
}

echo "<br><b>Database update complete. You can delete this file now.</b>";

$conn->close();
?>