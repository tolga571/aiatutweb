<?php
// Read-only configuration status. Values come from Railway environment
// variables; change them there (Railway → service → Variables), not here.
$title = t('admin.nav_settings');
$pageHeader = t('admin.nav_settings');
$pagePretitle = t('admin.settings_pretitle');
$e = fn($v) => htmlspecialchars((string)$v);
$badge = [
    'ok' => ['bg-green-lt', 'ti-circle-check', t('admin.cfg_set')],
    'warn' => ['bg-yellow-lt', 'ti-alert-triangle', t('admin.cfg_check')],
    'missing' => ['bg-red-lt', 'ti-circle-x', t('admin.cfg_missing')],
    'info' => ['bg-secondary-lt', 'ti-info-circle', ''],
];

ob_start();
?>
<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="ti ti-info-circle fs-2"></i>
    <div><?= t('admin.cfg_readonly_note') ?></div>
</div>
<div class="row row-cards">
    <?php foreach ($groups as $group => $items): ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><?= $e($group) ?></h3></div>
            <div class="list-group list-group-flush">
                <?php foreach ($items as [$label, $status, $value]):
                    [$cls, $icon, $text] = $badge[$status]; ?>
                <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-break" style="min-width:0;"><?= $e($label) ?></span>
                    <span class="d-flex align-items-center gap-2 text-end" style="min-width:0;">
                        <?php if ($value !== null): ?><code class="text-break small"><?= $e($value) ?></code><?php endif; ?>
                        <?php if ($value === null || $status !== 'info'): ?>
                            <span class="badge <?= $cls ?>"><i class="ti <?= $icon ?> me-1"></i><?= $value === null && $status === 'info' ? t('admin.cfg_not_set') : ($text ?: '') ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
