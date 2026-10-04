<?php
$title = t('admin.nav_health');
$pageHeader = t('admin.nav_health');
$pagePretitle = t('admin.health_pretitle');
$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v) => number_format((float)$v, 0, ',', '.');
$date = fn($v) => $v ? date('d.m.Y H:i', strtotime((string)$v)) : '—';
$ago = function ($v): string {
    if (!$v) return t('admin.never');
    $m = (time() - strtotime((string)$v)) / 60;
    return $m < 60 ? t('admin.min_ago', ['n' => (int)$m]) : ($m < 2880 ? t('admin.hours_ago', ['n' => (int)($m / 60)]) : t('admin.days_ago', ['n' => (int)($m / 1440)]));
};
$aiRate = (int)($ai['total'] ?? 0) ? ($ai['failed'] / $ai['total'] * 100) : 0;
$missingConfig = [];
foreach ($config as $group => $items) {
    foreach ($items as [$label, $status]) {
        if ($status === 'missing') $missingConfig[] = $label;
    }
}
// Overall checks shown at the top.
$checks = [
    [$e(t('admin.database')), true, $e($database['version'])],
    [$e(t('admin.missing_config')), !$missingConfig, $missingConfig ? $e(implode(', ', $missingConfig)) : $e(t('admin.none'))],
    [$e(t('admin.ai_error_rate_24h')), $aiRate <= 5, '%' . number_format($aiRate, 1, ',', '.') . ' · ' . $n($ai['failed'] ?? 0) . '/' . $n($ai['total'] ?? 0)],
    [$e(t('admin.admins_without_2fa')), !$security['admins_without_2fa'], $security['admins_without_2fa'] ? $e(implode(', ', array_column($security['admins_without_2fa'], 'email'))) : $e(t('admin.none'))],
];

ob_start();
?>
<div class="row row-cards mb-3">
    <?php foreach ($checks as [$label, $ok, $detail]): ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-sm"><div class="card-body d-flex align-items-center gap-3">
            <span class="avatar <?= $ok ? 'bg-green-lt' : 'bg-red-lt' ?>"><i class="ti ti-<?= $ok ? 'circle-check' : 'alert-triangle' ?>"></i></span>
            <div style="min-width:0;">
                <div class="fw-semibold"><?= $label ?></div>
                <div class="text-secondary small text-break"><?= $detail ?></div>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row row-cards mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-rocket me-1"></i><?= $e(t('admin.live_version')) ?></h3></div>
            <div class="list-group list-group-flush small">
                <?php foreach ([
                    ['Commit', $version['commit'] ? '<code>' . $e($version['commit']) . '</code>' : '<span class="text-secondary">' . $e(t('admin.unknown_local')) . '</span>'],
                    [$e(t('admin.commit_message')), $e($version['commit_message'] ? mb_substr(strtok($version['commit_message'], "\n"), 0, 90) : '—')],
                    [$e(t('admin.environment')), $e($version['environment'])],
                    ['PHP', $e($version['php'])],
                    [$e(t('admin.server')), $e($version['server'])],
                ] as [$k, $v]): ?>
                <div class="list-group-item d-flex justify-content-between gap-3"><span class="text-secondary"><?= $k ?></span><span class="text-end text-break"><?= $v ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-database me-1"></i><?= $e(t('admin.database')) ?></h3><span class="card-subtitle ms-2"><?= number_format($database['size'] / 1048576, 1, ',', '.') ?> MB</span></div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($database['tables'] as $t => $c): ?>
                    <div class="col-6 col-sm-4"><div class="text-secondary small"><?= $e($t) ?></div><div class="fw-semibold"><?= $n($c) ?></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="text-secondary small mt-3"><?= $e(t('admin.sessions_line', ['n' => $n($database['active_sessions']), 'a' => $n($database['admin_sessions'])])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-webhook me-1"></i><?= $e(t('admin.payment_webhooks')) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th><?= $e(t('admin.col_provider')) ?></th><th><?= $e(t('admin.last_event')) ?></th><th class="text-end"><?= $e(t('admin.h24')) ?></th><th class="text-end"><?= $e(t('admin.d7')) ?></th><th><?= $e(t('admin.last_log_lines')) ?></th></tr></thead>
            <tbody>
            <?php foreach (['dodo' => 'Dodo (' . $e(t('admin.live')) . ')', 'paddle' => 'Paddle (test)', 'fastspring' => 'FastSpring'] as $key => $label):
                $w = $webhooks[$key]; ?>
                <tr>
                    <td class="text-nowrap fw-semibold"><?= $label ?></td>
                    <td class="text-nowrap"><?= $date($w['last_at'] ?? null) ?><div class="text-secondary small"><?= $ago($w['last_at'] ?? null) ?></div></td>
                    <td class="text-end"><?= $w['day'] === null ? '—' : $n($w['day']) ?></td>
                    <td class="text-end"><?= $w['week'] === null ? '—' : $n($w['week']) ?></td>
                    <td style="min-width:280px;max-width:520px;">
                        <?php if (!$w['log']): ?><span class="text-secondary small"><?= $e(t('admin.no_log')) ?></span><?php endif; ?>
                        <?php foreach ($w['log'] as $line):
                            $bad = preg_match('/FAILED|REJECTED|error/i', $line); ?>
                            <div class="small text-truncate <?= $bad ? 'text-red' : 'text-secondary' ?>" title="<?= $e($line) ?>"><?= $e($line) ?></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-sparkles me-1"></i><?= $e(t('admin.ai_24h')) ?></h3><a href="?page=admin-ai-usage" class="ms-auto small"><?= $e(t('admin.details')) ?></a></div>
            <div class="list-group list-group-flush small">
                <?php foreach ([
                    [$e(t('admin.requests')), $n($ai['total'] ?? 0)],
                    [$e(t('admin.failed')), $n($ai['failed'] ?? 0) . ' (%' . number_format($aiRate, 1, ',', '.') . ')'],
                    [$e(t('admin.fell_back')), $n($ai['fallback'] ?? 0)],
                    [$e(t('admin.last_ok')), $date($ai['last_ok'] ?? null) . ' · ' . $ago($ai['last_ok'] ?? null)],
                    [$e(t('admin.last_error')), $date($ai['last_fail'] ?? null)],
                    [$e(t('admin.cost')), '$' . number_format((float)($ai['cost'] ?? 0), 2, ',', '.')],
                ] as [$k, $v]): ?>
                <div class="list-group-item d-flex justify-content-between gap-3"><span class="text-secondary"><?= $k ?></span><span class="text-end"><?= $e($v) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-lock-access me-1"></i><?= $e(t('admin.security_24h')) ?></h3><a href="?page=admin-audit" class="ms-auto small"><?= $e(t('admin.nav_audit')) ?></a></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php $typeLabels = ['login' => t('admin.user_signin'), 'admin-login' => t('admin.admin_signin'), 'register' => t('admin.registration'), 'password-reset' => t('admin.password_reset')];
                    foreach ($security['attempts'] as $a): ?>
                        <span class="badge bg-secondary-lt"><?= $e($typeLabels[$a['type']] ?? $a['type']) ?>: <?= $e(t('admin.failed_ips', ['n' => $n($a['cnt']), 'ips' => $n($a['ips'])])) ?></span>
                    <?php endforeach; ?>
                    <?php if (!$security['attempts']): ?><span class="text-secondary small"><i class="ti ti-circle-check text-green"></i> <?= $e(t('admin.no_failed_attempts')) ?></span><?php endif; ?>
                </div>
                <?php if ($security['top_ips']): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead><tr><th>IP</th><th><?= $e(t('admin.attempts')) ?></th><th><?= $e(t('admin.type')) ?></th><th><?= $e(t('admin.col_last')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($security['top_ips'] as $ip): ?>
                            <tr><td><code><?= $e($ip['ip']) ?></code></td><td><?= $n($ip['cnt']) ?></td><td class="small"><?= $e($ip['types']) ?></td><td class="text-nowrap small"><?= $date($ip['last_at']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="text-secondary small mb-1"><?= $e(t('admin.recent_admin_signins')) ?></div>
                <?php foreach ($security['admin_logins'] as $l): ?>
                    <div class="small"><?= $e($l['admin_email']) ?> · <code><?= $e($l['ip']) ?></code> · <?= $date($l['performed_at']) ?> <?= $l['action'] === 'admin_login_2fa' ? '<span class="badge bg-green-lt">2FA</span>' : '<span class="badge bg-yellow-lt">' . $e(t('admin.pw_lower')) . '</span>' ?></div>
                <?php endforeach; ?>
                <?php if (!$security['admin_logins']): ?><div class="small text-secondary"><?= $e(t('admin.no_records_yet')) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
