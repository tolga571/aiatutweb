<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

$db = new Database($config['db_url']);
$pdo = $db->getPdo();

echo "Turkish cards:\n";
$tr = $pdo->query("SELECT id, word, language FROM flashcards WHERE language = 'tr' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tr as $r) {
    var_dump($r);
}

echo "\nProgress table:\n";
$p = $pdo->query("SELECT * FROM user_flashcard_progress")->fetchAll(PDO::FETCH_ASSOC);
foreach ($p as $r) {
    var_dump($r);
}

echo "\nDirect JOIN test:\n";
$j = $pdo->query("SELECT f.id, f.word, ufp.status FROM flashcards f LEFT JOIN user_flashcard_progress ufp ON ufp.flashcard_id = f.id WHERE f.language = 'tr' AND (ufp.user_id = 1 OR ufp.user_id IS NULL) LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($j as $r) {
    var_dump($r);
}
