<?php
// workinggame_api.php - Handles highscore submissions and retrieval
require_once 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Ensure the table exists
$tableSql = "CREATE TABLE IF NOT EXISTS workinggame_highscores (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    player_name VARCHAR(50) NOT NULL,
    score INT(11) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableSql);

if ($method === 'GET') {
    // Fetch top 3 scores
    $sql = "SELECT player_name, score, created_at FROM workinggame_highscores ORDER BY score DESC LIMIT 3";
    $result = $conn->query($sql);
    
    $scores = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $scores[] = [
                'name' => htmlspecialchars($row['player_name']),
                'score' => (int)$row['score'],
                'date' => date('M j, Y', strtotime($row['created_at']))
            ];
        }
    }
    
    echo json_encode(['success' => true, 'scores' => $scores]);
    exit;

} elseif ($method === 'POST') {
    // Submit new score
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['name']) && isset($data['score'])) {
        $name = trim($data['name']);
        
        // Default name if empty
        if (empty($name)) {
            $name = "AAA";
        }
        
        $score = (int)$data['score'];
        
        $stmt = $conn->prepare("INSERT INTO workinggame_highscores (player_name, score) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $score);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing name or score']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);
?>
