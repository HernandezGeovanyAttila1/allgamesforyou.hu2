<?php
require 'db.php';
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "Tables:\n";
    while ($row = $result->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

$tables = ['messages', 'message', 'friends', 'friendships'];
foreach ($tables as $table) {
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        echo "\nTable: $table\n";
        while ($row = $res->fetch_assoc()) {
            echo implode(" | ", $row) . "\n";
        }
    } else {
        echo "\nTable $table does not exist.\n";
    }
}
?>
