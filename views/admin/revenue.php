<?php
$title = t('admin.nav_payments');
$pageHeader = t('admin.nav_payments');
$pagePretitle = t('admin.revenue_pretitle');

$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v, int $dec = 0) => number_format((float)$v, $dec, ',', '.');
$usd = fn($v, int $dec = 2) => '$' . number_format((float)$v, $dec, ',', '.');
$date = fn($v, string $fmt = 'd.m.Y H:i') => $v ? date($fmt, strtotime((string)$v)) : '—';
$ago = function ($v): string {
    $h = (time() - strtotime((string)$v)) / 3600;
    return $h < 1 ? t('admin.just_now') : ($h < 48 ? t('admin.hours_ago', ['n' => (int)$h]) : t('admin.days_ago', ['n' => (int)($h / 24)]));
};
$planLabels = ['inactive' => t('admin.plan_inactive'), 'trial' => t('admin.plan_trial'), 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$provider = fn(array $r) => !empty($r['dodo_subscription_id']) ? 'Dodo' : (!empty($r['fastspring_subscription_id']) ? 'FastSpring' : (!empty($r['paddle_subscription_id']) ? 'Paddle (test)' : t('admin.granted_manually')));
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-payments'], $filters, $o), fn($v) => $v !== '' && $v !== null));
$queueCount = count($queue['refunds']) + count($queue['cancellations']);

$chart = [
    'historyDays' => array_map(fn($r) => date('d.m', strtotime($r['day'])), $history),
    'historyMrr' => array_map(fn($r) => (float)$r['mrr'], $history),
    'historySubs' => array_map(fn($r) => (int)$r['real_subscribers'], $history),
    'months' => array_map(fn($r) => date('m.Y', strtotime($r['month'])), $movements),
    'started' => array_map(fn($r) => (int)$r['started'], $movements),
    'canceled' => array_map(fn($r) => -(int)$r['canceled'], $movements),
    'upgraded' => array_map(fn($r) => (int)$r['upgraded'], $movements),
];

/** Resolve form for one queue item. */
$resolveForm = function (array $r, string $kind) use ($csrf, $e): string {
    $options = $kind === 'refund'
        ? ['refunded' => t('admin.refund_done'), 'declined' => t('admin.declined')]
        : ['canceled' => t('admin.canceled_at_provider'), 'kept' => t('admin.not_canceled')];
    $html = '<form method="POST" action="?page=admin-revenue-action" class="d-flex flex-wrap flex-xxl-nowrap gap-2 align-items-center justify-content-xl-end">'
        . '<input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="id" value="' . (int)$r['id'] . '">'
        . '<input type="hidden" name="kind" value="' . $kind . '"><select name="outcome" class="form-select form-select-sm w-auto" required>'
        . '<option value="">' . $e(t('admin.outcome')) . '</option>';
    foreach ($options as $v => $l) {
        $html .= '<option value="' . $v . '">' . $e($l) . '</option>';
    }
    return $html . '</select><input type="text" name="note" class="form-control form-control-sm" style="max-width:180px" placeholder="' . $e(t('admin.note_optional')) . '">'
        . '<button class="btn btn-sm btn-primary text-nowrap"><i class="ti ti-check me-1"></i>' . $e(t('admin.close_item')) . '</button></form>';
};

ob_start();
?>
<div class="row row-deck row-cards mb-3">
    <?php foreach ([
        [$e(t('admin.real_mrr')), $usd($kpis['mrr']), t('admin.yearly_value', ['v' => $usd($kpis['arr'], 0)]), 'currency-dollar', 'green'],
        [$e(t('admin.real_subscribers')), $n($kpis['real_subscribers']), t('admin.excluding_test', ['n' => $n($kpis['test_subscribers'])]), 'users', 'primary'],
        [$e(t('admin.arpu')), $usd($kpis['arpu']), t('admin.arpu_sub'), 'receipt', 'azure'],
        [$e(t('admin.new_subs_30d')), $n($kpis['new_30d']), 'Dodo', 'trending-up', 'teal'],
        [$e(t('admin.cancels_30d')), $n($kpis['canceled_30d']), t('admin.churn_rate', ['v' => $n($kpis['churn_30d'], 1)]), 'trending-down', 'red'],
        [$e(t('admin.cancel_scheduled')), $n($kpis['cancel_scheduled']), t('admin.ends_period_end'), 'calendar-x', 'orange'],
    ] as [$label, $value, $sub, $icon, $color]): ?>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="card card-sm"><div class="card-body">
            <div class="d-flex align-items-center mb-2"><span class="avatar avatar-sm bg-<?= $color ?>-lt me-2"><i class="ti ti-<?= $icon ?>"></i></span><span class="text-secondary small"><?= $label ?></span></div>
            <div class="h2 mb-1"><?= $value ?></div>
            <div class="kpi-delta text-secondary"><?= $e($sub) ?></div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row row-deck row-cards mb-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.mrr_trend')) ?></h3><span class="card-subtitle ms-2"><?= $e(t('admin.last_90d')) ?></span></div>
            <div class="card-body">
                <?php if (count($history) < 2): ?>
                    <div class="text-secondary small mb-2"><i class="ti ti-info-circle"></i> <?= $e(t('admin.mrr_history_note')) ?></div>
                <?php endif; ?>
                <div id="chart-mrr" style="min-height:260px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.monthly_movement')) ?></h3><span class="card-subtitle ms-2">Dodo</span></div>
            <div class="card-body"><div id="chart-moves" style="min-height:260px;"></div></div>
        </div>
    </div>
</div>

<div class="card mb-3" id="queue">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-checklist me-1"></i><?= $e(t('admin.work_queue')) ?></h3>
        <span class="card-subtitle ms-2"><?= $e(t('admin.work_queue_sub')) ?></span>
        <?php if ($queueCount): ?><span class="badge bg-orange text-white ms-auto"><?= $e(t('admin.n_open', ['n' => $queueCount])) ?></span><?php endif; ?>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ([['refunds', 'refund', t('admin.event_refund_requested'), 'orange', 'receipt-refund'], ['cancellations', 'cancel', t('admin.manual_cancel_needed'), 'red', 'calendar-x']] as [$key, $kind, $label, $color, $icon]):
            foreach ($queue[$key] as $r): ?>
        <div class="list-group-item">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-xl-5">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-<?= $color ?>-lt"><i class="ti ti-<?= $icon ?>"></i></span>
                        <div class="text-truncate">
                            <div><strong><?= $e($label) ?></strong> · <a href="?page=admin-user&amp;id=<?= (int)$r['id'] ?>"><?= $e($r['email']) ?></a></div>
                            <div class="text-secondary small"><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?> · <?= $e($provider($r)) ?> · <?= $date($r['requested_at']) ?> (<?= $ago($r['requested_at']) ?>)</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-7 d-flex justify-content-xl-end">
                    <?= $canEdit ? $resolveForm($r, $kind) : '<span class="text-secondary small">' . $e(t('admin.role_readonly')) . '</span>' ?>
                </div>
            </div>
        </div>
        <?php endforeach; endforeach; ?>
        <?php if (!$queueCount): ?>
            <div class="list-group-item text-secondary"><i class="ti ti-circle-check text-green me-1"></i><?= $e(t('admin.queue_empty')) ?></div>
        <?php endif; ?>
    </div>
    <?php if ($handled): ?>
    <div class="card-footer">
        <div class="text-secondary small mb-2"><?= $e(t('admin.recently_closed')) ?></div>
        <?php foreach ($handled as $h): ?>
            <div class="small mb-1">
                <span class="badge bg-<?= $h['action'] === 'refund_handled' ? 'orange' : 'red' ?>-lt"><?= $e($h['action'] === 'refund_handled' ? t('admin.refund') : t('admin.cancellation')) ?></span>
                <?= $e($h['email'] ?? ('#' . $h['target_user_id'])) ?> — <?= $e($h['detail']) ?>
                <span class="text-secondary">· <?= $date($h['performed_at']) ?> · <?= $e($h['admin_email']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="row row-deck row-cards mb-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.plan_mix')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Plan</th><th><?= $e(t('admin.period')) ?></th><th class="text-end"><?= $e(t('admin.subscribers')) ?></th><th class="text-end">MRR</th><th class="text-end"><?= $e(t('admin.share')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($planMix as $r): ?>
                        <tr>
                            <td><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?></td>
                            <td><?= $e(($r['billing_interval'] ?? 'month') === 'year' ? t('admin.yearly_cap') : t('admin.monthly_cap')) ?></td>
                            <td class="text-end"><?= $n($r['cnt']) ?></td>
                            <td class="text-end"><?= $usd($r['mrr']) ?></td>
                            <td class="text-end text-secondary">%<?= $kpis['mrr'] > 0 ? $n($r['mrr'] / $kpis['mrr'] * 100) : 0 ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$planMix): ?><tr><td colspan="5" class="text-center text-secondary py-4"><?= $e(t('admin.no_real_subs')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.upcoming_renewals')) ?></h3><span class="card-subtitle ms-2"><?= $e(t('admin.d7')) ?></span></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th><?= $e(t('admin.user_col')) ?></th><th>Plan</th><th><?= $e(t('admin.date')) ?></th><th class="text-end"><?= $e(t('admin.amount')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($renewals as $r): ?>
                        <tr>
                            <td class="text-truncate" style="max-width:200px;"><a href="?page=admin-user&amp;id=<?= (int)$r['id'] ?>"><?= $e($r['email']) ?></a></td>
                            <td><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?><?= !empty($r['cancel_requested_at']) ? ' <span class="badge bg-red-lt">' . $e(t('admin.will_cancel')) . '</span>' : '' ?></td>
                            <td class="text-nowrap"><?= $date($r['next_billed_at'], 'd.m.Y') ?></td>
                            <td class="text-end"><?= $usd($r['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$renewals): ?><tr><td colspan="4" class="text-center text-secondary py-4"><?= $e(t('admin.no_renewals')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header flex-wrap gap-2">
        <h3 class="card-title me-auto"><?= $e(t('admin.subscriptions')) ?> <span class="text-secondary fw-normal">(<?= $n($subs['total']) ?>)</span></h3>
        <div class="btn-group">
            <?php foreach (['real' => t('admin.real'), 'test' => 'Test', 'all' => t('admin.lang_filter_all')] as $t => $l): ?>
                <a href="<?= $e($qs(['type' => $t, 'p' => null])) ?>" class="btn btn-sm <?= $filters['type'] === $t ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $e($l) ?></a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="page" value="admin-payments"><input type="hidden" name="type" value="<?= $e($filters['type']) ?>">
            <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <?php foreach (['' => t('admin.all_statuses'), 'active' => t('admin.active'), 'cancel' => t('admin.has_cancel_request'), 'change' => t('admin.plan_change_pending')] as $v => $l): ?>
                    <option value="<?= $v ?>"<?= $filters['status'] === $v ? ' selected' : '' ?>><?= $e($l) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= $e($filters['q']) ?>" class="form-control form-control-sm" placeholder="<?= $e(t('admin.subs_search_ph')) ?>">
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th><?= $e(t('admin.user_col')) ?></th><th>Plan</th><th><?= $e(t('admin.col_provider')) ?></th><th><?= $e(t('admin.subscription_id')) ?></th><th><?= $e(t('admin.next_payment')) ?></th><th><?= $e(t('admin.status')) ?></th><th class="text-end"><?= $e(t('admin.monthly_cap')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($subs['rows'] as $r):
                $isTest = empty($r['dodo_subscription_id']);
                $status = !empty($r['cancel_requested_at'])
                    ? '<span class="badge bg-red-lt">' . $e($r['cancel_method'] === 'manual' ? t('admin.cancel_manual') : t('admin.cancel_planned')) . '</span>'
                    : (!empty($r['pending_plan_change']) ? '<span class="badge bg-yellow-lt">→ ' . $e($planLabels[$r['pending_plan_change']] ?? $r['pending_plan_change']) . '</span>' : '<span class="badge bg-green-lt">' . $e(t('admin.active')) . '</span>');
                if (!empty($r['refund_requested_at'])) { $status .= ' <span class="badge bg-orange-lt">' . $e(t('admin.event_refund_requested')) . '</span>'; } ?>
                <tr>
                    <td class="text-truncate" style="max-width:220px;"><a href="?page=admin-user&amp;id=<?= (int)$r['id'] ?>"><?= $e($r['email']) ?></a></td>
                    <td class="text-nowrap"><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?> <span class="text-secondary small"><?= $e(($r['billing_interval'] ?? 'month') === 'year' ? t('admin.yearly') : t('admin.monthly')) ?></span></td>
                    <td><?= $e($provider($r)) ?><?= $isTest ? ' <span class="badge bg-secondary text-white">TEST</span>' : '' ?></td>
                    <td class="text-secondary small text-truncate" style="max-width:170px;"><code><?= $e($r['dodo_subscription_id'] ?: ($r['fastspring_subscription_id'] ?: ($r['paddle_subscription_id'] ?: '—'))) ?></code></td>
                    <td class="text-nowrap"><?= $date($r['next_billed_at'], 'd.m.Y') ?></td>
                    <td class="text-nowrap"><?= $status ?></td>
                    <td class="text-end<?= $isTest ? ' text-secondary text-decoration-line-through' : '' ?>"><?= $usd($r['monthly']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$subs['rows']): ?><tr><td colspan="7" class="text-center text-secondary py-5"><?= $e(t('admin.no_subs_match')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($subs['pages'] > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small"><?= $e(t('admin.page_of', ['p' => $pageNum, 'pages' => $subs['pages']])) ?></p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i> <?= $e(t('admin.prev_page')) ?></a></li>
            <li class="page-item<?= $pageNum >= $subs['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>"><?= $e(t('admin.next_page')) ?> <i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@7.7.0/dist/apexcharts.min.js"></script>
<script>
(function () {
    var d = <?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var base = {
        chart: { background: 'transparent', foreColor: '#94a3b8', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
        theme: { mode: 'dark' }, grid: { borderColor: 'rgba(148,163,184,.12)', strokeDashArray: 4 },
        dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left' }, tooltip: { theme: 'dark' }
    };
    function merge(a, b) { return Object.assign({}, a, b, { chart: Object.assign({}, a.chart, b.chart || {}) }); }
    new ApexCharts(document.getElementById('chart-mrr'), merge(base, {
        chart: { type: 'area', height: 260 },
        series: [{ name: 'MRR ($)', data: d.historyMrr }, { name: <?= json_encode(t('admin.real_subscribers'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, data: d.historySubs }],
        colors: ['#4ade80', '#6d8bff'], stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: 0 } },
        markers: { size: d.historyDays.length < 3 ? 5 : 0 },
        xaxis: { categories: d.historyDays, tickAmount: Math.min(8, Math.max(1, d.historyDays.length - 1)), labels: { rotate: 0, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
        yaxis: [
            { min: 0, labels: { formatter: function (v) { return '$' + Math.round(v); } } },
            { opposite: true, min: 0, labels: { formatter: function (v) { return Math.round(v); } } }
        ],
        noData: { text: <?= json_encode(t('admin.no_data_yet'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> }
    })).render();
    new ApexCharts(document.getElementById('chart-moves'), merge(base, {
        chart: { type: 'bar', height: 260, stacked: true },
        series: [{ name: <?= json_encode(t('admin.new'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, data: d.started }, { name: <?= json_encode(t('admin.event_subscription_upgraded'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, data: d.upgraded }, { name: <?= json_encode(t('admin.cancellation'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, data: d.canceled }],
        colors: ['#4ade80', '#38bdf8', '#f87171'],
        plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
        xaxis: { categories: d.months, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: function (v) { return Math.abs(Math.round(v)); } } },
        tooltip: { theme: 'dark', y: { formatter: function (v) { return Math.abs(v); } } }
    })).render();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
