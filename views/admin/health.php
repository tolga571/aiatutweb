<?php
$title = 'Sistem sağlığı';
$pageHeader = 'Sistem sağlığı';
$pagePretitle = 'canlı sürüm, veritabanı, ödeme webhook\'ları, AI ve güvenlik';
$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v) => number_format((float)$v, 0, ',', '.');
$date = fn($v) => $v ? date('d.m.Y H:i', strtotime((string)$v)) : '—';
$ago = function ($v): string {
    if (!$v) return 'hiç';
    $m = (time() - strtotime((string)$v)) / 60;
    return $m < 60 ? (int)$m . ' dk önce' : ($m < 2880 ? (int)($m / 60) . ' saat önce' : (int)($m / 1440) . ' gün önce');
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
    ['Veritabanı', true, $e($database['version'])],
    ['Eksik yapılandırma', !$missingConfig, $missingConfig ? $e(implode(', ', $missingConfig)) : 'yok'],
    ['AI hata oranı (24 sa)', $aiRate <= 5, '%' . number_format($aiRate, 1, ',', '.') . ' · ' . $n($ai['failed'] ?? 0) . '/' . $n($ai['total'] ?? 0)],
    ['2FA\'sı kapalı admin', !$security['admins_without_2fa'], $security['admins_without_2fa'] ? $e(implode(', ', array_column($security['admins_without_2fa'], 'email'))) : 'yok'],
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
            <div class="card-header"><h3 class="card-title"><i class="ti ti-rocket me-1"></i>Canlı sürüm</h3></div>
            <div class="list-group list-group-flush small">
                <?php foreach ([
                    ['Commit', $version['commit'] ? '<code>' . $e($version['commit']) . '</code>' : '<span class="text-secondary">bilinmiyor (yerel)</span>'],
                    ['Commit mesajı', $e($version['commit_message'] ? mb_substr(strtok($version['commit_message'], "\n"), 0, 90) : '—')],
                    ['Ortam', $e($version['environment'])],
                    ['PHP', $e($version['php'])],
                    ['Sunucu', $e($version['server'])],
                ] as [$k, $v]): ?>
                <div class="list-group-item d-flex justify-content-between gap-3"><span class="text-secondary"><?= $k ?></span><span class="text-end text-break"><?= $v ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-database me-1"></i>Veritabanı</h3><span class="card-subtitle ms-2"><?= number_format($database['size'] / 1048576, 1, ',', '.') ?> MB</span></div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($database['tables'] as $t => $c): ?>
                    <div class="col-6 col-sm-4"><div class="text-secondary small"><?= $e($t) ?></div><div class="fw-semibold"><?= $n($c) ?></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="text-secondary small mt-3">Açık oturum: <?= $n($database['active_sessions']) ?> · admin oturumu: <?= $n($database['admin_sessions']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-webhook me-1"></i>Ödeme webhook'ları</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Sağlayıcı</th><th>Son olay</th><th class="text-end">24 sa</th><th class="text-end">7 gün</th><th>Son log satırları</th></tr></thead>
            <tbody>
            <?php foreach (['dodo' => 'Dodo (canlı)', 'paddle' => 'Paddle (test)', 'fastspring' => 'FastSpring'] as $key => $label):
                $w = $webhooks[$key]; ?>
                <tr>
                    <td class="text-nowrap fw-semibold"><?= $label ?></td>
                    <td class="text-nowrap"><?= $date($w['last_at'] ?? null) ?><div class="text-secondary small"><?= $ago($w['last_at'] ?? null) ?></div></td>
                    <td class="text-end"><?= $w['day'] === null ? '—' : $n($w['day']) ?></td>
                    <td class="text-end"><?= $w['week'] === null ? '—' : $n($w['week']) ?></td>
                    <td style="min-width:280px;max-width:520px;">
                        <?php if (!$w['log']): ?><span class="text-secondary small">log yok (her deploy'da sıfırlanır)</span><?php endif; ?>
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
            <div class="card-header"><h3 class="card-title"><i class="ti ti-sparkles me-1"></i>Yapay zekâ (24 saat)</h3><a href="?page=admin-ai-usage" class="ms-auto small">Ayrıntı</a></div>
            <div class="list-group list-group-flush small">
                <?php foreach ([
                    ['İstek', $n($ai['total'] ?? 0)],
                    ['Hatalı', $n($ai['failed'] ?? 0) . ' (%' . number_format($aiRate, 1, ',', '.') . ')'],
                    ['Yedek modele düşen', $n($ai['fallback'] ?? 0)],
                    ['Son başarılı yanıt', $date($ai['last_ok'] ?? null) . ' · ' . $ago($ai['last_ok'] ?? null)],
                    ['Son hata', $date($ai['last_fail'] ?? null)],
                    ['Maliyet', '$' . number_format((float)($ai['cost'] ?? 0), 2, ',', '.')],
                ] as [$k, $v]): ?>
                <div class="list-group-item d-flex justify-content-between gap-3"><span class="text-secondary"><?= $k ?></span><span class="text-end"><?= $e($v) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-lock-access me-1"></i>Güvenlik (24 saat)</h3><a href="?page=admin-audit" class="ms-auto small">İşlem kaydı</a></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php $typeLabels = ['login' => 'Kullanıcı girişi', 'admin-login' => 'Admin girişi', 'register' => 'Kayıt', 'password-reset' => 'Şifre sıfırlama'];
                    foreach ($security['attempts'] as $a): ?>
                        <span class="badge bg-secondary-lt"><?= $e($typeLabels[$a['type']] ?? $a['type']) ?>: <?= $n($a['cnt']) ?> başarısız / <?= $n($a['ips']) ?> IP</span>
                    <?php endforeach; ?>
                    <?php if (!$security['attempts']): ?><span class="text-secondary small"><i class="ti ti-circle-check text-green"></i> Başarısız deneme yok.</span><?php endif; ?>
                </div>
                <?php if ($security['top_ips']): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead><tr><th>IP</th><th>Deneme</th><th>Tür</th><th>Son</th></tr></thead>
                        <tbody>
                        <?php foreach ($security['top_ips'] as $ip): ?>
                            <tr><td><code><?= $e($ip['ip']) ?></code></td><td><?= $n($ip['cnt']) ?></td><td class="small"><?= $e($ip['types']) ?></td><td class="text-nowrap small"><?= $date($ip['last_at']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="text-secondary small mb-1">Son admin girişleri</div>
                <?php foreach ($security['admin_logins'] as $l): ?>
                    <div class="small"><?= $e($l['admin_email']) ?> · <code><?= $e($l['ip']) ?></code> · <?= $date($l['performed_at']) ?> <?= $l['action'] === 'admin_login_2fa' ? '<span class="badge bg-green-lt">2FA</span>' : '<span class="badge bg-yellow-lt">şifre</span>' ?></div>
                <?php endforeach; ?>
                <?php if (!$security['admin_logins']): ?><div class="small text-secondary">Henüz kayıt yok.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
