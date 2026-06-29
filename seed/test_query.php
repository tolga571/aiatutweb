<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

$db = new Database($config['db_path']);
$pdo = $db->getPdo();

$sql = "SELECT COUNT(*) as total,
               SUM(CASE WHEN ufp.status = 'learned' THEN 1 ELSE 0 END) as learned,
               GROUP_CONCAT(ufp.status) as statuses
        FROM flashcards f
        LEFT JOIN user_flashcard_progress ufp ON ufp.flashcard_id = f.id AND ufp.user_id = 1
        WHERE f.language = 'tr'";

$r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
var_dump($r);

echo "\n--- Direct check ---\n";
$r2 = $pdo->query("SELECT * FROM user_flashcard_progress WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);
var_dump($r2);
