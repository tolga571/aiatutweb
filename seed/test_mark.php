<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\Flashcard;

$db = new Database($config['db_path']);
$pdo = $db->getPdo();
$fc = new Flashcard($db);

// Direct SQL check
$row = $pdo->query("SELECT * FROM user_flashcard_progress")->fetchAll(PDO::FETCH_ASSOC);
echo "Progress rows: " . count($row) . "\n";
foreach ($row as $r) {
    echo "  user_id={$r['user_id']} card_id={$r['flashcard_id']} status={$r['status']} xp={$r['xp_awarded']}\n";
}

// Test progress
$prog = $fc->getProgress(1, 'tr');
echo "Progress: total={$prog['total']} learned={$prog['learned']}\n";

// Also get cards for user 1
$cards = $fc->getCards('tr', 'en', 1);
foreach ($cards as $c) {
    if ($c['id'] == 1) {
        echo "Card 1 status: {$c['user_status']}\n";
        break;
    }
}
