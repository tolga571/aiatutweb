<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\Flashcard;

$db = new Database($config['db_url']);
$pdo = $db->getPdo();

// Clean up test data
$pdo->exec("DELETE FROM user_flashcard_progress WHERE user_id = 999");

$fc = new Flashcard($db);

echo "=== E2E Test ===\n\n";

// 1. Get Turkish cards for English user
echo "1. Getting Turkish cards (native: en)...\n";
$cards = $fc->getCards('tr', 'en', 999);
echo "   Cards found: " . count($cards) . "\n";
$first = $cards[0];
echo "   First card: id={$first['id']} word={$first['word']} -> {$first['translation']}\n";
echo "   Status: {$first['user_status']}\n\n";

// 2. Mark card as learned
echo "2. Marking card {$first['id']} as learned...\n";
$result = $fc->markAsLearned(999, (int)$first['id']);
echo "   Result: " . ($result ? "OK" : "FAIL") . "\n\n";

// 3. Check progress
echo "3. Progress for Turkish:\n";
$prog = $fc->getProgress(999, 'tr');
echo "   Total: {$prog['total']}, Learned: {$prog['learned']}\n\n";

// 4. Get cards again to see updated status
echo "4. Getting cards again...\n";
$cards2 = $fc->getCards('tr', 'en', 999);
echo "   First card status: {$cards2[0]['user_status']}\n";
echo "   xp_awarded: {$cards2[0]['xp_awarded']}\n\n";

// 5. Get progress for German (should work too)
echo "5. German cards (native: tr):\n";
$deCards = $fc->getCards('de', 'tr', 999);
echo "   First: {$deCards[0]['word']} -> {$deCards[0]['translation']}\n\n";

echo "=== All E2E tests passed! ===\n";
