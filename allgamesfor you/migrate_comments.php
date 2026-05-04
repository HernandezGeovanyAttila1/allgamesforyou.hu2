<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db.php';

echo "<h2>Database Schema Update: Nested Comments</h2>";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if parent_id column exists
$res = $conn->query("SHOW COLUMNS FROM comments LIKE 'parent_id'");
if ($res->num_rows === 0) {
    echo "Adding 'parent_id' column...<br>";
    $sql = "ALTER TABLE comments ADD COLUMN parent_id INT NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "<span style='color:green;'>Column 'parent_id' added successfully.</span><br>";
    } else {
        echo "<span style='color:red;'>Error adding 'parent_id': </span>" . $conn->error . "<br>";
    }
} else {
    echo "<span style='color:blue;'>Column 'parent_id' already exists.</span><br>";
}

echo "<br><b>Database update complete. You can now use the nested comments feature.</b>";
$conn->close();
?>