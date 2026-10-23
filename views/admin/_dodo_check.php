<?php
// Where revenue comes from, and anything that doesn't add up with the live Dodo account.
// Needs $dodoState (DodoSync::state()) and $unverified (paid plans whose Dodo id isn't active there).
$ago = $ago ?? function ($v): string {
    $h = (time() - strtotime((string)$v)) / 3600;
    return $h < 1 ? t('admin.just_now') : ($h < 48 ? t('admin.hours_ago', ['n' => (int)$h]) : t('admin.days_ago', ['n' => (int)($h / 24)]));
};
$dodoAgo = !empty($dodoState['ok_at']) ? $ago(date('Y-m-d H:i:s', (int)$dodoState['ok_at'])) : t('admin.dodo_never');
?>
<?php if (!$dodoState || empty($dodoState['ok'])): ?>
<div class="alert alert-danger" role="alert">
    <?= htmlspecialchars(t('admin.dodo_sync_failed', ['error' => $dodoState['error'] ?? '—', 'ago' => $dodoAgo])) ?>
</div>
<?php endif; ?>
<?php if ($unverified > 0): ?>
<div class="alert alert-warning" role="alert">
    <?= htmlspecialchars(t('admin.dodo_unverified', ['n' => $unverified])) ?>
    <a href="?page=admin-payments&amp;type=test" class="alert-link"><?= htmlspecialchars(t('admin.dodo_unverified_link')) ?></a>
</div>
<?php endif; ?>
<?php if ($dodoState && !empty($dodoState['ok'])): ?>
<div class="text-secondary small mb-3"><i class="ti ti-circle-check text-green"></i> <?= htmlspecialchars(t('admin.dodo_checked', ['ago' => $dodoAgo])) ?></div>
<?php endif; ?>
