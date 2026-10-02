<?php
$title = 'Hesap güvenliği';
$pageHeader = 'Hesap güvenliği';
$pagePretitle = 'İki adımlı doğrulama (2FA) · ' . ($admin['email'] ?? '');
$e = fn($v) => htmlspecialchars((string)$v);
$codeInput = '<input type="text" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" placeholder="6 haneli kod" maxlength="12" required style="max-width:170px;letter-spacing:.15em;">';

ob_start();
?>
<div class="row row-cards">
    <div class="col-lg-7">
        <?php if ($newBackupCodes): ?>
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning-lt"><h3 class="card-title"><i class="ti ti-key me-1"></i>Yedek kodların — sadece bir kez gösteriliyor</h3></div>
            <div class="card-body">
                <p class="text-secondary">Telefonunu kaybedersen bu kodlarla giriş yapabilirsin. Her kod <strong>bir kez</strong> kullanılır. Şimdi bir şifre yöneticisine kaydet.</p>
                <div class="row g-2 mb-3" id="backup-codes">
                    <?php foreach ($newBackupCodes as $bc): ?>
                        <div class="col-6 col-md-3"><code class="d-block text-center py-2 fs-3" style="background:rgba(255,255,255,.04);border-radius:8px;"><?= $e($bc) ?></code></div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(<?= $e(json_encode(implode("\n", $newBackupCodes))) ?>).then(()=>{this.innerHTML='<i class=&quot;ti ti-check me-1&quot;></i>Kopyalandı'})">
                    <i class="ti ti-copy me-1"></i>Hepsini kopyala
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$setupSecret): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="avatar avatar-lg bg-green-lt"><i class="ti ti-shield-check fs-1"></i></span>
                    <div>
                        <h3 class="m-0">İki adımlı doğrulama açık</h3>
                        <div class="text-secondary"><?= $e(date('d.m.Y H:i', strtotime($admin['totp_enabled_at']))) ?> tarihinden beri · <?= (int)$backupLeft ?> yedek kod kaldı</div>
                    </div>
                </div>
                <p class="text-secondary mb-0">Her girişte şifrenden sonra authenticator uygulamandaki 6 haneli kod istenir.</p>
            </div>
            <div class="card-footer">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1">Yeni yedek kodlar oluştur</div>
                        <form method="POST" action="?page=admin-2fa-action" class="d-flex gap-2 flex-wrap">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="regenerate">
                            <?= $codeInput ?><button class="btn btn-outline-primary">Oluştur</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1 text-red">2FA'yı kapat</div>
                        <form method="POST" action="?page=admin-2fa-action" class="d-flex gap-2 flex-wrap" onsubmit="return confirm('İki adımlı doğrulama kapatılsın mı? Hesabın sadece şifreyle korunacak.')">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="disable">
                            <?= $codeInput ?><button class="btn btn-outline-danger">Kapat</button>
                        </form>
                    </div>
                </div>
                <div class="text-secondary small mt-2">Her ikisi için uygulamadaki güncel kodu ya da bir yedek kodu gir.</div>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-shield-plus me-1"></i>İki adımlı doğrulamayı aç</h3></div>
            <div class="card-body">
                <ol class="mb-4 ps-3">
                    <li class="mb-2">Telefonuna bir authenticator uygulaması kur: <strong>Google Authenticator</strong>, Microsoft Authenticator, Authy veya 1Password.</li>
                    <li class="mb-2">Uygulamada "QR kodu tara" de ve aşağıdaki kodu okut.</li>
                    <li>Uygulamanın gösterdiği 6 haneli kodu aşağıya yazıp onayla.</li>
                </ol>
                <div class="row g-4 align-items-center">
                    <div class="col-sm-auto text-center">
                        <div id="qr" class="d-inline-block p-2 bg-white rounded" style="line-height:0;" aria-label="2FA QR kodu"></div>
                    </div>
                    <div class="col-sm">
                        <div class="text-secondary small mb-1">QR okutamıyor musun? Bu anahtarı elle gir:</div>
                        <code class="d-block p-2 mb-3 text-break" style="background:rgba(255,255,255,.04);border-radius:8px;letter-spacing:.08em;"><?= $e(trim(chunk_split($setupSecret, 4, ' '))) ?></code>
                        <form method="POST" action="?page=admin-2fa-action" class="d-flex gap-2 flex-wrap">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="enable">
                            <?= $codeInput ?><button class="btn btn-primary"><i class="ti ti-check me-1"></i>Onayla ve aç</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
        <script>
            (function () {
                var qr = qrcode(0, 'M');
                qr.addData(<?= json_encode($setupUri, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
                qr.make();
                document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2 });
            })();
        </script>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title"><i class="ti ti-info-circle me-1"></i>Neden önemli?</h3>
                <p class="text-secondary">Admin paneli kullanıcıların e-postalarına, sohbetlerine ve aboneliklerine erişiyor. 2FA açıkken şifren sızsa bile telefonun olmadan kimse giremez.</p>
                <h3 class="card-title mt-3"><i class="ti ti-device-mobile-off me-1"></i>Telefonu kaybedersen</h3>
                <p class="text-secondary mb-0">Yedek kodlardan biriyle giriş yap, sonra 2FA'yı kapatıp yeni telefonla tekrar aç. Yedek kodlar da yoksa Railway'deki veritabanında <code>admins</code> tablosunda kendi satırının <code>totp_enabled_at</code> alanını boşaltman gerekir.</p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
