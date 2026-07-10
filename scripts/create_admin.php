<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

$db = new Database($config['db_url']);

$email = $argv[1] ?? 'admin@example.com';
$passwordPlain = $argv[2] ?? '12345678';
$name = $argv[3] ?? 'Admin';

$hash = password_hash($passwordPlain, PASSWORD_DEFAULT);

$existing = $db->fetchOne('SELECT id FROM admins WHERE email = ?', [$email]);
if ($existing) {
    echo "Admin already exists (id={$existing['id']}). No changes made.\n";
    exit;
}

$db->execute('INSERT INTO admins (email, password, name, created_at) VALUES (?, ?, ?, NOW())', [$email, $hash, $name]);

echo "Admin created: email='$email', password='$passwordPlain'\n";
echo "Login at: ?page=admin-login\n";
