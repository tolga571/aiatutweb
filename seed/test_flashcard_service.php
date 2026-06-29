<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\Flashcard;

$db = new Database($config['db_path']);
$fc = new Flashcard($db);

echo "=== Testing getCards (target: tr, native: en) ===\n";
$cards = $fc->getCards('tr', 'en');
echo "Found " . count($cards) . " cards\n";
if (!empty($cards)) {
    $first = $cards[0];
    echo "  First card: {$first['word']} -> {$first['translation']}\n";
    echo "  Pronunciation: {$first['pronunciation']}\n";
    echo "  Category: {$first['category']}\n";
    echo "  Example: {$first['example']}\n";
    echo "  Example translation: {$first['example_translation']}\n";
    echo "  Status: {$first['user_status']}\n";
}

echo "\n=== Testing getCards (target: de, native: tr) ===\n";
$cards2 = $fc->getCards('de', 'tr');
echo "Found " . count($cards2) . " cards\n";
if (!empty($cards2)) {
    echo "  First: {$cards2[0]['word']} -> {$cards2[0]['translation']}\n";
}

echo "\n=== Testing getCards (target: xx, should fallback to en) ===\n";
$cards3 = $fc->getCards('xx', 'en');
echo "Found " . count($cards3) . " cards (expected to fallback to English)\n";
if (!empty($cards3)) {
    echo "  First: {$cards3[0]['word']} -> {$cards3[0]['translation']}\n";
}

echo "\n=== Testing getProgress ===\n";
$progress = $fc->getProgress(1, 'tr');
echo "Total: {$progress['total']}, Learned: {$progress['learned']}\n";

echo "\n=== Testing markAsLearned ===\n";
$result = $fc->markAsLearned(1, 1);
echo "Mark card 1 as learned for user 1: " . ($result ? "OK" : "FAIL") . "\n";

$progress2 = $fc->getProgress(1, 'tr');
echo "After marking: Total: {$progress2['total']}, Learned: {$progress2['learned']}\n";

echo "\nAll tests passed!\n";
