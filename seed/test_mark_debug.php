<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

$db = new Database($config['db_url']);
$pdo = $db->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if user 999 exists
$user = $pdo->query("SELECT id FROM users WHERE id = 999")->fetch();
echo "User 999: " . ($user ? "exists" : "not found") . "\n";

// Try the insert outside transaction
try {
    $stmt = $pdo->prepare("
        INSERT INTO user_flashcard_progress (user_id, flashcard_id, status, xp_awarded, updated_at)
        VALUES (?, ?, 'learned', 1, CURRENT_TIMESTAMP)
        ON CONFLICT(user_id, flashcard_id)
        DO UPDATE SET status = 'learned', xp_awarded = xp_awarded + 1, updated_at = CURRENT_TIMESTAMP
    ");
    $result = $stmt->execute([999, 351]);
    echo "Insert result: " . ($result ? "OK" : "FAIL") . "\n";
    echo "Row count: " . $stmt->rowCount() . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
