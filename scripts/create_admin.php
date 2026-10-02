<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;


// Usage: php scripts/create_admin.php <email> <password> [name]
// No defaults: a known default password once meant anyone who read this
// public script could sign in to an admin created without arguments.
if ($argc < 3 || !filter_var($argv[1], FILTER_VALIDATE_EMAIL) || strlen($argv[2]) < 12) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php <email> <password, 12+ chars> [name]\n");
    exit(1);
}
$email = $argv[1];
$passwordPlain = $argv[2];
$name = $argv[3] ?? 'Admin';

$db = new Database($config['db_url']);

$hash = password_hash($passwordPlain, PASSWORD_DEFAULT);

$existing = $db->fetchOne('SELECT id FROM admins WHERE email = ?', [$email]);
if ($existing) {
    echo "Admin already exists (id={$existing['id']}). No changes made.\n";
    exit;
}

$db->execute('INSERT INTO admins (email, password, name, created_at) VALUES (?, ?, ?, NOW())', [$email, $hash, $name]);

echo "Admin created: {$email}\n";
echo "Login at: ?page=admin-login\n";
