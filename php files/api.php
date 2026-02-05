<?php
session_start();
require 'db.php';
require_once 'utils.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'init':
        // Fetch User Info
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $me = $stmt->get_result()->fetch_assoc();

        // Fetch Friends (Accepted & Pending)
        // We do this in the refined query below.


        // Let's refine the Friends Query to be easier to consume
        $query = "
            SELECT 
                f.id AS relationship_id,
                f.status,
                f.user_id AS requester_id,
                u.user_id AS friend_id,
                u.username
            FROM friends f
            JOIN users u ON (CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END) = u.user_id
            WHERE f.user_id = ? OR f.friend_id = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();
        $friend_res = $stmt->get_result();
        $my_friends = [];
        $friend_ids = [$user_id]; // Exclude self and friends from "All Users"

        while ($row = $friend_res->fetch_assoc()) {
            $friend_ids[] = $row['friend_id'];
            $my_friends[] = [
                'relationship_id' => $row['relationship_id'],
                'id' => $row['friend_id'],
                'username' => $row['username'],
                'status' => $row['status'],
                'is_incoming' => ($row['status'] === 'pending' && $row['requester_id'] != $user_id)
            ];
        }

        // Fetch All Other Users (for suggestions)
        $others = [];
        $exclude_ids = implode(',', $friend_ids);
        // Safety for empty list
        if (empty($friend_ids))
            $exclude_ids = $user_id;

        $res = $conn->query("SELECT user_id, username FROM users WHERE user_id NOT IN ($exclude_ids) LIMIT 50");
        while ($row = $res->fetch_assoc()) {
            $others[] = ['id' => $row['user_id'], 'username' => $row['username']];
        }

        echo json_encode([
            "status" => "success",
            "me" => $me,
            "friends" => $my_friends,
            "others" => $others
        ]);
        break;

    case 'fetch_chat':
        $friend_id = intval($_GET['friend_id']);
        $last_id = intval($_GET['last_id'] ?? 0);

        $sql = "SELECT m.id, m.sender_id, m.message, m.media, m.created_at 
                FROM messages m
                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                AND m.id > ?
                ORDER BY m.created_at ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $user_id, $friend_id, $friend_id, $user_id, $last_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $msgs = [];
        while ($row = $res->fetch_assoc()) {
            $msgs[] = [
                'id' => $row['id'],
                'is_me' => ($row['sender_id'] == $user_id),
                'message' => htmlspecialchars($row['message']),
                'media' => $row['media'],
                'time' => date('H:i', strtotime($row['created_at']))
            ];
        }
        echo json_encode(["status" => "success", "messages" => $msgs]);
        break;

    case 'send_message':
        $receiver_id = intval($_POST['receiver_id']);
        $message = trim($_POST['message'] ?? '');
        $media_path = null;

        // Validation
        if (empty($message) && empty($_FILES['media']))
            break;

        // Media Upload
        if (isset($_FILES['media']) && $_FILES['media']['size'] > 0) {
            $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
            // Basic validation
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm'];
            if (in_array(strtolower($ext), $allowed)) {
                $filename = 'uploads/' . uniqid() . '.' . $ext;
                if (!is_dir('uploads'))
                    mkdir('uploads', 0777, true);
                move_uploaded_file($_FILES['media']['tmp_name'], $filename);
                $media_path = $filename;

                // Compress with Tinify
                compressImageWithTinyPng($filename);
            }
        }

        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, media, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $user_id, $receiver_id, $message, $media_path);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to send"]);
        }
        break;

    case 'friend_action':
        $type = $_POST['type']; // fetch, accept, decline, request

        if ($type === 'request') {
            $target_id = intval($_POST['target_id']);
            // prevent duplicates
            $stmt = $conn->prepare("INSERT INTO friends (user_id, friend_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $stmt->bind_param("ii", $user_id, $target_id);
            if ($stmt->execute())
                echo json_encode(["status" => "success"]);
            else
                echo json_encode(["status" => "error", "message" => "Could not send request"]);
        } elseif ($type === 'accept') {
            $rel_id = intval($_POST['rel_id']);
            $stmt = $conn->prepare("UPDATE friends SET status='accepted' WHERE id=?");
            $stmt->bind_param("i", $rel_id);
            $stmt->execute();
            echo json_encode(["status" => "success"]);
        } elseif ($type === 'decline') {
            $rel_id = intval($_POST['rel_id']);
            $stmt = $conn->prepare("DELETE FROM friends WHERE id=?");
            $stmt->bind_param("i", $rel_id);
            $stmt->execute();
            echo json_encode(["status" => "success"]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
?>