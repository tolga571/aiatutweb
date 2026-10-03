<?php
$e = fn($v) => htmlspecialchars((string)$v);
$title = t('admin.security_title');
$pageHeader = $title;
$pagePretitle = t('admin.twofa_long') . ' · ' . ($admin['email'] ?? '');
$codeInput = '<input type="text" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" placeholder="' . $e(t('admin.six_digit_code')) . '" maxlength="12" required style="max-width:170px;letter-spacing:.15em;">';

ob_start();
?>
<div class="row row-cards">
    <div class="col-lg-7">
        <?php if ($newBackupCodes): ?>
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning-lt"><h3 class="card-title"><i class="ti ti-key me-1"></i><?= $e(t('admin.backup_codes_once')) ?></h3></div>
            <div class="card-body">
                <p class="text-secondary"><?= t('admin.backup_codes_help') ?></p>
                <div class="row g-2 mb-3" id="backup-codes">
                    <?php foreach ($newBackupCodes as $bc): ?>
                        <div class="col-6 col-md-3"><code class="d-block text-center py-2 fs-3" style="background:rgba(255,255,255,.04);border-radius:8px;"><?= $e($bc) ?></code></div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(<?= $e(json_encode(implode("\n", $newBackupCodes))) ?>).then(()=>{this.innerHTML='<i class=&quot;ti ti-check me-1&quot;></i>' + <?= $e(json_encode(t('admin.copied'), JSON_UNESCAPED_UNICODE)) ?>})">
                    <i class="ti ti-copy me-1"></i><?= $e(t('admin.copy_all')) ?>
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
                        <h3 class="m-0"><?= $e(t('admin.twofa_on')) ?></h3>
                        <div class="text-secondary"><?= $e(t('admin.twofa_since', ['date' => date('d.m.Y H:i', strtotime($admin['totp_enabled_at'])), 'n' => (int)$backupLeft])) ?></div>
                    </div>
                </div>
                <p class="text-secondary mb-0"><?= $e(t('admin.twofa_on_help')) ?></p>
            </div>
            <div class="card-footer">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1"><?= $e(t('admin.regen_codes')) ?></div>
                        <form method="POST" action="?page=admin-2fa-action" class="d-flex gap-2 flex-wrap">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="regenerate">
                            <?= $codeInput ?><button class="btn btn-outline-primary"><?= $e(t('admin.create')) ?></button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1 text-red"><?= $e(t('admin.twofa_disable')) ?></div>
                        <form method="POST" action="?page=admin-2fa-action" class="d-flex gap-2 flex-wrap" onsubmit="return confirm(<?= $e(json_encode(t('admin.twofa_disable_confirm'), JSON_UNESCAPED_UNICODE)) ?>)">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="disable">
                            <?= $codeInput ?><button class="btn btn-outline-danger"><?= $e(t('admin.turn_off')) ?></button>
                        </form>
                    </div>
                </div>
                <div class="text-secondary small mt-2"><?= $e(t('admin.twofa_code_or_backup')) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-shield-plus me-1"></i><?= $e(t('admin.twofa_enable_title')) ?></h3></div>
            <div class="card-body">
                <ol class="mb-4 ps-3">
                    <li class="mb-2"><?= t('admin.twofa_step1') ?></li>
                    <li class="mb-2"><?= $e(t('admin.twofa_step2')) ?></li>
                    <li><?= $e(t('admin.twofa_step3')) ?></li>
                </ol>
                <div class="row g-4 align-items-center">
                    <div class="col-sm-auto text-center">
                        <div id="qr" class="d-inline-block p-2 bg-white rounded" style="line-height:0;" aria-label="<?= $e(t('admin.twofa_qr')) ?>"></div>
                    </div>
                    <div class="col-sm">
                        <div class="text-secondary small mb-1"><?= $e(t('admin.twofa_manual')) ?></div>
                        <code class="d-block p-2 mb-3 text-break" style="background:rgba(255,255,255,.04);border-radius:8px;letter-spacing:.08em;"><?= $e(trim(chunk_split($setupSecret, 4, ' '))) ?></code>
                        <form method="POST" action="?page=admin-2fa-action" class="d-flex gap-2 flex-wrap">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="enable">
                            <?= $codeInput ?><button class="btn btn-primary"><i class="ti ti-check me-1"></i><?= $e(t('admin.confirm_enable')) ?></button>
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
                <h3 class="card-title"><i class="ti ti-info-circle me-1"></i><?= $e(t('admin.why_important')) ?></h3>
                <p class="text-secondary"><?= $e(t('admin.twofa_why')) ?></p>
                <h3 class="card-title mt-3"><i class="ti ti-device-mobile-off me-1"></i><?= $e(t('admin.lost_phone')) ?></h3>
                <p class="text-secondary mb-0"><?= t('admin.lost_phone_help') ?></p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
