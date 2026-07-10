<?php
// scripts/create_default_admin.php
// This script creates a default admin account with a valid email and password "12345678".

require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

$db = new Database($config['db_url']);
$pdo = $db->getPdo();

$email = 'admin@example.com'; // valid email for login form (type="email")
$passwordPlain = '12345678';
$hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
$name = 'Default Admin';

// Check if admin already exists to avoid duplicates
$stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$stmt->execute([$email]);
$exists = $stmt->fetchColumn();
if ($exists) {
    echo "Admin already exists (id=$exists). No changes made.\n";
    exit;
}

$insert = $pdo->prepare('INSERT INTO admins (email, password, name, created_at) VALUES (?,?,?,datetime("now"))');
$insert->execute([$email, $hash, $name]);

echo "Default admin created: email='admin@example.com', password='12345678' (hashed in DB).\n";
?>
