<?php
require __DIR__.'/autoload.php';
$config = require __DIR__.'/config.php';
use App\Src\Database;

$db = new Database($config['db_url']);
$sql = file_get_contents(__DIR__.'/seed/dummy_data.sql');
$pdo = $db->getPdo();
$pdo->exec($sql);

echo "Dummy data imported.\n";
?>
