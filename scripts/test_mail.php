<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

$email = $argv[1] ?? '';
if (empty($email)) {
    echo "Kullanim: php scripts/test_mail.php <email>\n";
    exit(1);
}

$mailer = new \App\Src\Mailer($config);
$result = $mailer->send(
    $email,
    'Jumplearner - Test Maili',
    '<h1>Merhaba!</h1><p>Bu Mailtrap entegrasyonu icin bir test mailidir.</p><p>Eger bu maili goruyorsan, email altyapisi calisiyor demektir.</p>',
    'Merhaba! Bu Mailtrap entegrasyonu icin bir test mailidir.'
);

if ($result) {
    echo "[OK] Test maili basariyla gonderildi: {$email}\n";
} else {
    echo "[HATA] Mail gonderilemedi. MAILTRAP_API_TOKEN kontrol edin.\n";
    exit(1);
}
