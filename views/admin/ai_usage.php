<?php
$title = t('admin.nav_ai_usage');
$pageHeader = t('admin.nav_ai_usage');
$pagePretitle = t('admin.ai_pretitle');
$e = fn($v) => htmlspecialchars((string)$v);
$usd = fn($v, $dec = 2) => '$' . number_format((float)$v, $dec, ',', '.');
$num = fn($v) => number_format((int)$v, 0, ',', '.');
$planLabels = ['trial' => t('admin.plan_trial'), 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium', 'inactive' => t('admin.plan_inactive')];
$periodLabels = ['today' => t('admin.today'), 'week' => t('admin.last_7d'), 'month' => t('admin.this_month')];

ob_start();
?>

<div class="row row-cards mb-4">
    <?php foreach ($periods as $key => $p):
        $okCount = (int)$p['requests'] - (int)$p['failed']; ?>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary"><?= $e($periodLabels[$key]) ?></div>
            <div class="h1 mb-1"><?= $usd($p['cost']) ?></div>
            <div class="text-secondary" style="font-size:13px;">
                <?= $e(t('admin.n_requests', ['n' => $num($p['requests'])])) ?><?= (int)$p['failed'] ? ' · <span style="color:#ff8a80;">' . $e(t('admin.n_failed', ['n' => $num($p['failed'])])) . '</span>' : '' ?><br>
                <?= $okCount ? $e(t('admin.per_reply', ['v' => $usd((float)$p['cost'] / $okCount, 4)])) : '—' ?>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary"><?= $e(t('admin.month_end_estimate')) ?></div>
            <div class="h1 mb-1"><?= $usd($projectedMonthCost) ?></div>
            <div class="text-secondary" style="font-size:13px;"><?= $e(t('admin.pace_note', ['d' => (int)$dayOfMonth, 'm' => (int)$daysInMonth])) ?></div>
        </div></div>
    </div>
</div>

<h3><?= $e(t('admin.by_plan_month')) ?> <span class="text-secondary small fw-normal"><?= $e(t('admin.revenue_real_only')) ?></span></h3>
<div class="table-responsive mb-4">
<table class="table table-hover table-striped">
    <thead><tr><th>Plan</th><th><?= $e(t('admin.period')) ?></th><th><?= $e(t('admin.real_subscribers')) ?></th><th><?= $e(t('admin.est_monthly_revenue')) ?></th><th><?= $e(t('admin.quota_per_user')) ?></th><th><?= $e(t('admin.ai_requests')) ?></th><th><?= $e(t('admin.ai_cost')) ?></th><th><?= $e(t('admin.ai_cost_vs_revenue')) ?></th></tr></thead>
    <tbody>
    <?php if (!$byPlan): ?><tr><td colspan="8" style="text-align:center;color:#9aa0a6;padding:20px;"><?= $e(t('admin.no_plan_usage')) ?></td></tr><?php endif; ?>
    <?php foreach ($byPlan as $r):
        $interval = $r['billing_interval'] === 'year' ? 'year' : 'month';
        $revenue = ($prices[$r['plan']][$interval] ?? 0) * (int)$r['paying_users'];
        $share = $revenue > 0 ? (float)$r['cost'] / $revenue * 100 : null; ?>
        <tr>
            <td><?= $e($planLabels[$r['plan']] ?? $r['plan']) ?></td>
            <td><?= (int)$r['paying_users'] === 0 ? '—' : $e($interval === 'year' ? t('admin.yearly_cap') : t('admin.monthly_cap')) ?></td>
            <td><?= $num($r['paying_users']) ?></td>
            <td><?= $revenue > 0 ? $usd($revenue) : '—' ?></td>
            <td><?= $num($quota->getBaseLimit((string)$r['plan'])) ?></td>
            <td><?= $num($r['requests']) ?></td>
            <td><?= $usd($r['cost']) ?></td>
            <td><?= $share === null ? '—' : '<span style="color:' . ($share > 40 ? '#ff8a80' : ($share > 25 ? '#ffd180' : '#b9f6ca')) . ';">' . number_format($share, 1) . '%</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <h3><?= $e(t('admin.by_model_month')) ?></h3>
        <div class="table-responsive mb-4">
<table class="table table-hover table-striped">
            <thead><tr><th>Model</th><th><?= $e(t('admin.requests')) ?></th><th><?= $e(t('admin.input_tokens')) ?></th><th><?= $e(t('admin.output_tokens')) ?></th><th><?= $e(t('admin.cost')) ?></th></tr></thead>
            <tbody>
            <?php if (!$byModel): ?><tr><td colspan="5" style="text-align:center;color:#9aa0a6;padding:20px;"><?= $e(t('admin.no_requests_yet')) ?></td></tr><?php endif; ?>
            <?php foreach ($byModel as $r): ?>
                <tr><td><?= $e($r['model']) ?></td><td><?= $num($r['requests']) ?></td><td><?= $num($r['prompt_tokens']) ?></td><td><?= $num($r['output_tokens']) ?></td><td><?= $usd($r['cost']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
</div>
    </div>
    <div class="col-12 col-lg-6">
        <h3><?= $e(t('admin.last_14d')) ?></h3>
        <div class="table-responsive mb-4">
<table class="table table-hover table-striped">
            <thead><tr><th><?= $e(t('admin.day')) ?></th><th><?= $e(t('admin.requests')) ?></th><th><?= $e(t('admin.failed')) ?></th><th><?= $e(t('admin.cost')) ?></th></tr></thead>
            <tbody>
            <?php if (!$daily): ?><tr><td colspan="4" style="text-align:center;color:#9aa0a6;padding:20px;"><?= $e(t('admin.no_requests_yet')) ?></td></tr><?php endif; ?>
            <?php foreach ($daily as $r): ?>
                <tr><td style="font-variant-numeric:tabular-nums;"><?= $e($r['day']) ?></td><td><?= $num($r['requests']) ?></td><td><?= (int)$r['failed'] ? '<span style="color:#ff8a80;">' . $num($r['failed']) . '</span>' : '0' ?></td><td><?= $usd($r['cost']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
</div>
    </div>
</div>

<h3><?= $e(t('admin.top_cost_users')) ?></h3>
<div class="table-responsive mb-4">
<table class="table table-hover table-striped">
    <thead><tr><th><?= $e(t('admin.user_col')) ?></th><th>Plan</th><th><?= $e(t('admin.requests')) ?></th><th><?= $e(t('admin.quota')) ?></th><th><?= $e(t('admin.cost')) ?></th></tr></thead>
    <tbody>
    <?php if (!$topUsers): ?><tr><td colspan="5" style="text-align:center;color:#9aa0a6;padding:20px;"><?= $e(t('admin.no_requests_yet')) ?></td></tr><?php endif; ?>
    <?php foreach ($topUsers as $r): ?>
        <tr>
            <td><a href="?page=admin-user&amp;id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?> <?= $e($r['email']) ?></a></td>
            <td><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?></td>
            <td><?= $num($r['requests']) ?></td>
            <td><?= $num($quota->getBaseLimit((string)$r['plan_status'])) ?></td>
            <td><?= $usd($r['cost'], 3) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<p style="color:#9aa0a6;font-size:12px;">
    <?= $e(t('admin.prices_used')) ?>
    <?php foreach ($modelPrices as $m => $pr): ?>
        <?= $e($m) ?> <?= $usd($pr['in']) ?> / <?= $usd($pr['out']) ?>;
    <?php endforeach; ?>
    <?= $e(t('admin.revenue_formula')) ?>
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
