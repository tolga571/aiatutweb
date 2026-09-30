<?php
$title = 'AI usage & cost';
$e = fn($v) => htmlspecialchars((string)$v);
$usd = fn($v, $dec = 2) => '$' . number_format((float)$v, $dec);
$num = fn($v) => number_format((int)$v);
$planLabels = ['trial' => 'Trial', 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$periodLabels = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'This month'];

ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
    <h2 style="margin:0;">AI usage &amp; cost</h2>
    <span style="color:#9aa0a6;font-size:13px;">Gemini API, paid-tier prices. Logged per request since this page shipped.</span>
</div>

<div class="row row-cards mb-4">
    <?php foreach ($periods as $key => $p):
        $okCount = (int)$p['requests'] - (int)$p['failed']; ?>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary"><?= $periodLabels[$key] ?></div>
            <div class="h1 mb-1"><?= $usd($p['cost']) ?></div>
            <div class="text-secondary" style="font-size:13px;">
                <?= $num($p['requests']) ?> requests<?= (int)$p['failed'] ? ' · <span style="color:#ff8a80;">' . $num($p['failed']) . ' failed</span>' : '' ?><br>
                <?= $okCount ? $usd((float)$p['cost'] / $okCount, 4) . ' per reply' : '—' ?>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary">Projected this month</div>
            <div class="h1 mb-1"><?= $usd($projectedMonthCost) ?></div>
            <div class="text-secondary" style="font-size:13px;">At the pace of the first <?= (int)$dayOfMonth ?> of <?= (int)$daysInMonth ?> days</div>
        </div></div>
    </div>
</div>

<h3>By plan — this month</h3>
<table class="table table-hover table-striped mb-4">
    <thead><tr><th>Plan</th><th>Billing</th><th>Paying users</th><th>Est. monthly revenue</th><th>Quota / user</th><th>AI requests</th><th>AI cost</th><th>AI cost / revenue</th></tr></thead>
    <tbody>
    <?php if (!$byPlan): ?><tr><td colspan="8" style="text-align:center;color:#9aa0a6;padding:20px;">No plans or usage yet.</td></tr><?php endif; ?>
    <?php foreach ($byPlan as $r):
        $interval = $r['billing_interval'] === 'year' ? 'year' : 'month';
        $revenue = ($prices[$r['plan']][$interval] ?? 0) * (int)$r['paying_users'];
        $share = $revenue > 0 ? (float)$r['cost'] / $revenue * 100 : null; ?>
        <tr>
            <td><?= $e($planLabels[$r['plan']] ?? $r['plan']) ?></td>
            <td><?= (int)$r['paying_users'] === 0 ? '—' : ($interval === 'year' ? 'Yearly' : 'Monthly') ?></td>
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

<div class="row">
    <div class="col-12 col-lg-6">
        <h3>By model — this month</h3>
        <table class="table table-hover table-striped mb-4">
            <thead><tr><th>Model</th><th>Requests</th><th>Tokens in</th><th>Tokens out</th><th>Cost</th></tr></thead>
            <tbody>
            <?php if (!$byModel): ?><tr><td colspan="5" style="text-align:center;color:#9aa0a6;padding:20px;">No requests yet.</td></tr><?php endif; ?>
            <?php foreach ($byModel as $r): ?>
                <tr><td><?= $e($r['model']) ?></td><td><?= $num($r['requests']) ?></td><td><?= $num($r['prompt_tokens']) ?></td><td><?= $num($r['output_tokens']) ?></td><td><?= $usd($r['cost']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="col-12 col-lg-6">
        <h3>Last 14 days</h3>
        <table class="table table-hover table-striped mb-4">
            <thead><tr><th>Day</th><th>Requests</th><th>Failed</th><th>Cost</th></tr></thead>
            <tbody>
            <?php if (!$daily): ?><tr><td colspan="4" style="text-align:center;color:#9aa0a6;padding:20px;">No requests yet.</td></tr><?php endif; ?>
            <?php foreach ($daily as $r): ?>
                <tr><td style="font-variant-numeric:tabular-nums;"><?= $e($r['day']) ?></td><td><?= $num($r['requests']) ?></td><td><?= (int)$r['failed'] ? '<span style="color:#ff8a80;">' . $num($r['failed']) . '</span>' : '0' ?></td><td><?= $usd($r['cost']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<h3>Top users by AI cost — this month</h3>
<table class="table table-hover table-striped mb-4">
    <thead><tr><th>User</th><th>Plan</th><th>Requests</th><th>Quota</th><th>Cost</th></tr></thead>
    <tbody>
    <?php if (!$topUsers): ?><tr><td colspan="5" style="text-align:center;color:#9aa0a6;padding:20px;">No requests yet.</td></tr><?php endif; ?>
    <?php foreach ($topUsers as $r): ?>
        <tr>
            <td>#<?= (int)$r['id'] ?> <?= $e($r['email']) ?></td>
            <td><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?></td>
            <td><?= $num($r['requests']) ?></td>
            <td><?= $num($quota->getBaseLimit((string)$r['plan_status'])) ?></td>
            <td><?= $usd($r['cost'], 3) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p style="color:#9aa0a6;font-size:12px;">
    Prices used (USD per 1M tokens, in / out incl. thinking):
    <?php foreach ($modelPrices as $m => $pr): ?>
        <?= $e($m) ?> <?= $usd($pr['in']) ?> / <?= $usd($pr['out']) ?>;
    <?php endforeach; ?>
    Revenue is list price × current paying users, before payment-provider fees.
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
