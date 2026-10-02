<?php
$title = __('admin.dashboard');
$pageHeader = __('admin.dashboard');
$pagePretitle = 'Genel bakış · son ' . $range . ' gün';

$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v, int $dec = 0) => number_format((float)$v, $dec, ',', '.');
$usd = fn($v, int $dec = 2) => '$' . number_format((float)$v, $dec, ',', '.');
$date = fn($v) => $v ? date('d.m.Y H:i', strtotime((string)$v)) : '—';
$planLabels = ['inactive' => 'Pasif', 'trial' => 'Deneme', 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$planLabel = function (string $plan) use ($planLabels): string {
    [$base, $test] = array_pad(explode(':', $plan, 2), 2, null);
    return ($planLabels[$base] ?? $base) . ($test ? ' (test)' : '');
};
$eventLabels = [
    'subscription_started' => ['Yeni abonelik', 'green'], 'subscription_upgraded' => ['Yükseltme', 'blue'],
    'subscription_downgraded' => ['Düşürme', 'yellow'], 'downgrade_scheduled' => ['Düşürme planlandı', 'yellow'],
    'cancellation_requested' => ['İptal talebi', 'orange'], 'subscription_canceled' => ['İptal edildi', 'red'],
    'cancellation_resumed' => ['İptal geri alındı', 'teal'], 'refund_requested' => ['İade talebi', 'orange'],
    'account_deleted' => ['Hesap silindi', 'red'],
];

/** Trend badge vs the previous period; $neutral for numbers where "up" isn't good or bad. */
$delta = function (array $k, bool $neutral = false): string {
    $cur = (float)$k['value']; $prev = (float)$k['previous'];
    if ($prev == 0.0) {
        return $cur > 0 ? '<span class="kpi-delta text-secondary">önceki dönem: 0</span>' : '';
    }
    $pct = ($cur - $prev) / $prev * 100;
    $cls = $neutral ? 'text-secondary' : ($pct >= 0 ? 'text-up' : 'text-down');
    $icon = $pct >= 0 ? 'trending-up' : 'trending-down';
    return '<span class="kpi-delta ' . $cls . '"><i class="ti ti-' . $icon . '"></i> ' . ($pct >= 0 ? '+' : '') . number_format($pct, 0, ',', '.') . '%</span>';
};

$todoCount = count($todos['manual_cancellations']) + count($todos['refunds']);
$aiFailRate = $todos['ai_total_24h'] ? $todos['ai_failed_24h'] / $todos['ai_total_24h'] * 100 : 0;

ob_start(); ?>
<div class="btn-group" role="group" aria-label="Tarih aralığı">
    <?php foreach ([7, 30, 90] as $d): ?>
        <a href="?page=admin-dashboard&amp;d=<?= $d ?>" class="btn <?= $range === $d ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $d ?> gün</a>
    <?php endforeach; ?>
</div>
<?php $pageActions = ob_get_clean();

$chartData = [
    'days' => array_map(fn($r) => date('d.m', strtotime($r['day'])), $daily),
    'signups' => array_map(fn($r) => (int)$r['signups'], $daily),
    'active' => array_map(fn($r) => (int)$r['active'], $daily),
    'messages' => array_map(fn($r) => (int)$r['messages'], $daily),
    'aiCost' => array_map(fn($r) => round((float)$r['ai_cost'], 4), $daily),
    'plans' => ['labels' => array_map(fn($r) => $planLabel((string)$r['plan']), $plans), 'values' => array_map(fn($r) => (int)$r['cnt'], $plans)],
    'langs' => ['labels' => array_map(fn($r) => __('languages.' . $r['lang']), $languages), 'values' => array_map(fn($r) => (int)$r['cnt'], $languages)],
];
$funnelSteps = [
    ['Kayıt oldu', $funnel['registered'] ?? 0],
    ['Onboarding tamamladı', $funnel['onboarded'] ?? 0],
    ['Deneme / plan başlattı', $funnel['started'] ?? 0],
    ['İlk mesajı gönderdi', $funnel['messaged'] ?? 0],
    ['Gerçek ödeme (Dodo)', $funnel['paid'] ?? 0],
];

ob_start();
?>
<?php if (empty($admin['totp_enabled_at'])): ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="alert">
    <i class="ti ti-shield-exclamation fs-2"></i>
    <div class="flex-fill">Hesabında <strong>iki adımlı doğrulama kapalı</strong>. Şifren ele geçirilirse panel de ele geçer.</div>
    <a href="?page=admin-2fa" class="btn btn-warning btn-sm">Şimdi aç</a>
</div>
<?php endif; ?>
<!-- KPI cards -->
<div class="row row-deck row-cards mb-3">
    <?php
    $cards = [
        ['Yeni kayıt', $n($kpis['signups']['value']), $delta($kpis['signups']), 'user-plus', 'primary'],
        ['Aktif kullanıcı', $n($kpis['active']['value']), $delta($kpis['active']), 'user-check', 'green'],
        ['Gönderilen mesaj', $n($kpis['messages']['value']), $delta($kpis['messages']), 'message-circle', 'azure'],
        ['AI maliyeti', $usd($kpis['ai_cost']['value']), $delta($kpis['ai_cost'], true), 'sparkles', 'purple'],
    ];
    foreach ($cards as [$label, $value, $deltaHtml, $icon, $color]): ?>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-<?= $color ?>-lt me-2"><i class="ti ti-<?= $icon ?>"></i></span>
                    <div class="text-secondary small"><?= $label ?></div>
                </div>
                <div class="h2 mb-1"><?= $value ?></div>
                <div><?= $deltaHtml ?: '<span class="kpi-delta text-secondary">&nbsp;</span>' ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-yellow-lt me-2"><i class="ti ti-currency-dollar"></i></span>
                    <div class="text-secondary small">Gerçek MRR (Dodo)</div>
                </div>
                <div class="h2 mb-1"><?= $usd($revenue['mrr']) ?></div>
                <div class="kpi-delta text-secondary"><?= $n($revenue['real_subscribers']) ?> gerçek · <?= $n($revenue['test_subscribers']) ?> test abone</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-teal-lt me-2"><i class="ti ti-users"></i></span>
                    <div class="text-secondary small">Toplam kullanıcı</div>
                </div>
                <div class="h2 mb-1"><?= $n($revenue['total_users']) ?></div>
                <div class="kpi-delta text-secondary"><?= $n($revenue['trials']) ?> denemede</div>
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards mb-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Kayıtlar ve aktif kullanıcılar</h3></div>
            <div class="card-body"><div id="chart-users" style="min-height:280px;"></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Yapılacaklar</h3>
                <?php if ($todoCount): ?><span class="badge bg-orange text-white ms-auto"><?= $todoCount ?></span><?php endif; ?>
            </div>
            <div class="list-group list-group-flush overflow-auto" style="max-height:330px;">
                <?php foreach ($todos['refunds'] as $r): ?>
                <a href="?page=admin-payments#queue" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-dot status-dot-animated bg-orange"></span>
                        <div class="text-truncate"><strong>İade talebi</strong> · <?= $e($r['email']) ?><div class="text-secondary small"><?= $e($planLabel((string)$r['plan_status'])) ?> · <?= $date($r['refund_requested_at']) ?></div></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php foreach ($todos['manual_cancellations'] as $r): ?>
                <a href="?page=admin-payments#queue" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-dot status-dot-animated bg-red"></span>
                        <div class="text-truncate"><strong>Elle iptal gerekli</strong> · <?= $e($r['email']) ?><div class="text-secondary small"><?= $e($planLabel((string)$r['plan_status'])) ?> · <?= $date($r['cancel_requested_at']) ?></div></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-dot <?= $aiFailRate > 5 ? 'status-dot-animated bg-red' : 'bg-green' ?>"></span>
                        <div><strong>AI hata oranı (24 sa)</strong><div class="text-secondary small"><?= $n($todos['ai_failed_24h']) ?> / <?= $n($todos['ai_total_24h']) ?> istek · %<?= $n($aiFailRate, 1) ?></div></div>
                    </div>
                </div>
                <div class="list-group-item">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-dot <?= $todos['admin_login_fails_24h'] >= 5 ? 'status-dot-animated bg-red' : 'bg-green' ?>"></span>
                        <div><strong>Başarısız girişler (24 sa)</strong><div class="text-secondary small">Admin: <?= $n($todos['admin_login_fails_24h']) ?> · Kullanıcı: <?= $n($todos['user_login_fails_24h']) ?></div></div>
                    </div>
                </div>
                <?php if (!$todoCount): ?>
                <div class="list-group-item text-secondary small"><i class="ti ti-circle-check text-green"></i> Bekleyen iade veya iptal yok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards mb-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Mesajlar ve AI maliyeti</h3></div>
            <div class="card-body"><div id="chart-messages" style="min-height:280px;"></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Dönüşüm hunisi</h3><span class="card-subtitle ms-2">son <?= $range ?> günde kayıt olanlar</span></div>
            <div class="card-body">
                <?php $top = max(1, (int)$funnelSteps[0][1]);
                foreach ($funnelSteps as $i => [$label, $count]):
                    $pct = $count / $top * 100;
                    $prev = $i > 0 ? (int)$funnelSteps[$i - 1][1] : 0;
                    $step = $i > 0 && $prev > 0 ? $count / $prev * 100 : null; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= $label ?></span>
                        <span class="fw-semibold"><?= $n($count) ?><?= $step !== null ? ' <span class="text-secondary fw-normal">(%' . $n($step) . ')</span>' : '' ?></span>
                    </div>
                    <div class="progress progress-sm"><div class="progress-bar" style="width: <?= round($pct, 1) ?>%; opacity: <?= 1 - $i * .13 ?>;"></div></div>
                </div>
                <?php endforeach; ?>
                <div class="text-secondary small">Parantez: bir önceki adıma göre geçiş oranı.</div>
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards mb-3">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Plan dağılımı</h3></div>
            <div class="card-body"><div id="chart-plans" style="min-height:260px;"></div></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Öğrenilen diller</h3></div>
            <div class="card-body"><div id="chart-langs" style="min-height:260px;"></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Son kayıtlar</h3><a href="?page=admin-users" class="ms-auto small">Tümü</a></div>
            <div class="list-group list-group-flush">
                <?php foreach ($recentSignups as $u):
                    $isTest = $u['has_paid'] && empty($u['dodo_subscription_id']); ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-primary-lt"><?= $e(mb_strtoupper(mb_substr($u['name'] ?: $u['email'], 0, 1))) ?></span>
                        <div class="text-truncate flex-fill" style="min-width:0;">
                            <div class="text-truncate"><?= $e($u['email']) ?></div>
                            <div class="text-secondary small"><?= $date($u['created_at']) ?> · <?= $e(__('languages.' . ($u['target_lang'] ?: 'en'))) ?></div>
                        </div>
                        <span class="badge <?= $isTest ? 'bg-secondary-lt' : 'bg-primary-lt' ?>"><?= $e($planLabels[$u['plan_status']] ?? $u['plan_status']) ?><?= $isTest ? ' · test' : '' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$recentSignups): ?><div class="list-group-item text-secondary">Henüz kayıt yok.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Son abonelik olayları</h3><a href="?page=admin-activity" class="ms-auto small">Tümü</a></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>Olay</th><th>Sağlayıcı</th><th>Plan</th></tr></thead>
            <tbody>
            <?php foreach ($recentEvents as $ev):
                [$evLabel, $evColor] = $eventLabels[$ev['event_type']] ?? [$ev['event_type'], 'secondary']; ?>
                <tr>
                    <td class="text-secondary text-nowrap"><?= $date($ev['created_at']) ?></td>
                    <td class="text-truncate" style="max-width:240px;"><?= $e($ev['email'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $evColor ?>-lt"><?= $e($evLabel) ?></span></td>
                    <td><?= $e(ucfirst((string)($ev['provider'] ?? '—'))) ?><?= ($ev['provider'] ?? '') === 'paddle' ? ' <span class="badge bg-secondary-lt">test</span>' : '' ?></td>
                    <td><?= $e($planLabels[$ev['plan']] ?? ($ev['plan'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentEvents): ?><tr><td colspan="5" class="text-secondary text-center py-4">Henüz olay yok.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@7.7.0/dist/apexcharts.min.js"></script>
<script>
(function () {
    var d = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var base = {
        chart: { background: 'transparent', foreColor: '#94a3b8', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
        theme: { mode: 'dark' },
        grid: { borderColor: 'rgba(148,163,184,.12)', strokeDashArray: 4 },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'left' },
        tooltip: { theme: 'dark' }
    };
    function merge(a, b) { return Object.assign({}, a, b, { chart: Object.assign({}, a.chart, b.chart || {}) }); }
    var everyN = Math.max(1, Math.ceil(d.days.length / 10));
    var xaxis = { categories: d.days, labels: { rotate: 0, formatter: function (v, t, i) { return i === undefined || i % everyN === 0 ? v : ''; } }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } };

    new ApexCharts(document.getElementById('chart-users'), merge(base, {
        chart: { type: 'area', height: 280 },
        series: [{ name: 'Aktif kullanıcı', data: d.active }, { name: 'Yeni kayıt', data: d.signups }],
        colors: ['#6d8bff', '#4ade80'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: 0 } },
        xaxis: xaxis, yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (v) { return Math.round(v); } } }
    })).render();

    new ApexCharts(document.getElementById('chart-messages'), merge(base, {
        chart: { type: 'line', height: 280 },
        series: [{ name: 'Mesaj', type: 'column', data: d.messages }, { name: 'AI maliyeti ($)', type: 'line', data: d.aiCost }],
        colors: ['#38bdf8', '#c084fc'],
        stroke: { width: [0, 3], curve: 'smooth' },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
        xaxis: xaxis,
        yaxis: [
            { min: 0, labels: { formatter: function (v) { return Math.round(v); } } },
            { opposite: true, min: 0, labels: { formatter: function (v) { return '$' + v.toFixed(2); } } }
        ]
    })).render();

    var donut = function (el, data, colors) {
        new ApexCharts(document.getElementById(el), merge(base, {
            chart: { type: 'donut', height: 260 },
            series: data.values, labels: data.labels, colors: colors,
            legend: { position: 'bottom' }, stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Toplam', color: '#94a3b8' } } } } }
        })).render();
    };
    var palette = ['#6d8bff', '#4ade80', '#38bdf8', '#c084fc', '#fbbf24', '#f87171', '#2dd4bf', '#94a3b8', '#f472b6'];
    donut('chart-plans', d.plans, palette);
    donut('chart-langs', d.langs, palette);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
