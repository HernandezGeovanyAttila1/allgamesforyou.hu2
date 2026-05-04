<?php
session_start();


console.log("FETCH START");


$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$game_id = intval($data["gameId"]);

// Ellenőrizzük, hogy benne van-e
$check = $conn->prepare("SELECT * FROM favourites WHERE user_id=? AND game_id=?");
$check->bind_param("ii", $user_id, $game_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Törlés
    $del = $conn->prepare("DELETE FROM favourites WHERE user_id=? AND game_id=?");
    $del->bind_param("ii", $user_id, $game_id);
    $del->execute();

    echo json_encode(["status" => "removed"]);
} else {
    // Hozzáadás
    $add = $conn->prepare("INSERT INTO favourites (user_id, game_id) VALUES (?, ?)");
    $add->bind_param("ii", $user_id, $game_id);
    $add->execute();

    echo json_encode(["status" => "added"]);
}
