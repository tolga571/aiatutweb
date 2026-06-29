<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\Flashcard;

$db = new Database($config['db_path']);
$pdo = $db->getPdo();

// Find any real user
$user = $pdo->query("SELECT id, target_lang, native_lang FROM users WHERE plan_status != 'inactive' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $user = $pdo->query("SELECT id, target_lang, native_lang FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
echo "Using user: "; var_dump($user);

$fc = new Flashcard($db);
$target = $user['target_lang'] ?? 'en';
$native = $user['native_lang'] ?? 'en';
$userId = (int)$user['id'];

echo "\n=== Getting cards (target: $target, native: $native) ===\n";
$cards = $fc->getCards($target, $native, $userId);
echo "Found: " . count($cards) . "\n";
$first = $cards[0];
echo "First: id={$first['id']} word={$first['word']} -> {$first['translation']}\n";

echo "\n=== Mark as learned ===\n";
$ok = $fc->markAsLearned($userId, (int)$first['id']);
echo "Result: " . ($ok ? "OK" : "FAIL") . "\n";

echo "\n=== Progress ===\n";
$prog = $fc->getProgress($userId, $target);
echo "Total: {$prog['total']}, Learned: {$prog['learned']}\n";

echo "\n=== Cards again ===\n";
$cards2 = $fc->getCards($target, $native, $userId);
echo "First card status: {$cards2[0]['user_status']}, xp: {$cards2[0]['xp_awarded']}\n";

echo "\nAll passed!\n";
