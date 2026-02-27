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

echo "<br><b>Database update complete. You can delete this file now.</b>";

$conn->close();
?>