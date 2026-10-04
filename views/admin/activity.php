<?php
$title = t('admin.nav_activity');
$pageHeader = t('admin.nav_activity');
$pagePretitle = t('admin.activity_pretitle', ['n' => number_format($totalCount, 0, ',', '.')]);

$e = fn($v) => htmlspecialchars((string)$v);
$eventLabels = [];
foreach (['subscription_started' => 'green', 'subscription_upgraded' => 'blue', 'subscription_downgraded' => 'yellow', 'downgrade_scheduled' => 'yellow', 'cancellation_requested' => 'orange', 'subscription_canceled' => 'red', 'cancellation_resumed' => 'teal', 'refund_requested' => 'orange', 'account_deleted' => 'red'] as $type => $color) {
    $eventLabels[$type] = [t('admin.event_' . $type), $color];
}
$planLabels = ['inactive' => t('admin.plan_inactive'), 'trial' => t('admin.plan_trial'), 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$providers = ['dodo' => 'Dodo', 'paddle' => 'Paddle (test)', 'fastspring' => 'FastSpring'];
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-activity'], $filters, $o), fn($v) => $v !== '' && $v !== null));

ob_start();
?>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2 ms-auto">
            <input type="hidden" name="page" value="admin-activity">
            <select name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value=""><?= $e(t('admin.all_events')) ?></option>
                <?php foreach ($eventTypes as $t): ?>
                    <option value="<?= $e($t) ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= $e($eventLabels[$t][0] ?? $t) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="provider" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value=""><?= $e(t('admin.all_providers')) ?></option>
                <?php foreach ($providers as $v => $l): ?>
                    <option value="<?= $v ?>"<?= $filters['provider'] === $v ? ' selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filters['type'] !== '' || $filters['provider'] !== ''): ?><a href="?page=admin-activity" class="btn btn-sm btn-ghost-secondary"><?= $e(t('admin.clear')) ?></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th><?= $e(t('admin.col_time')) ?></th><th><?= $e(t('admin.user_col')) ?></th><th><?= $e(t('admin.col_event')) ?></th><th><?= $e(t('admin.col_provider')) ?></th><th>Plan</th><th><?= $e(t('admin.detail')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev):
                [$label, $color] = $eventLabels[$ev['event_type']] ?? [$ev['event_type'], 'secondary']; ?>
                <tr>
                    <td class="text-secondary text-nowrap"><?= date('d.m.Y H:i', strtotime($ev['created_at'])) ?></td>
                    <td class="text-truncate" style="max-width:220px;">
                        <?php if ($ev['user_id'] && $ev['user_email']): ?>
                            <a href="?page=admin-user&amp;id=<?= (int)$ev['user_id'] ?>"><?= $e($ev['user_email']) ?></a>
                        <?php else: ?><span class="text-secondary"><?= $e(t('admin.deleted_user')) ?></span><?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $color ?>-lt"><?= $e($label) ?></span></td>
                    <td><?= $e($providers[$ev['provider'] ?? ''] ?? ($ev['provider'] ?: '—')) ?></td>
                    <td class="text-nowrap"><?= $e($planLabels[$ev['plan'] ?? ''] ?? ($ev['plan'] ?: '—')) ?><?= $ev['billing_interval'] ? ' <span class="text-secondary small">' . $e($ev['billing_interval'] === 'year' ? t('admin.yearly') : t('admin.monthly')) . '</span>' : '' ?></td>
                    <td class="text-secondary small text-truncate" style="max-width:320px;" title="<?= $e($ev['detail']) ?>"><?= $e($ev['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$events): ?><tr><td colspan="6" class="text-center text-secondary py-5"><?= $e(t('admin.no_events')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small"><?= $e(t('admin.page_of', ['p' => $pageNum, 'pages' => $totalPages])) ?></p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i> <?= $e(t('admin.prev_page')) ?></a></li>
            <li class="page-item<?= $pageNum >= $totalPages ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>"><?= $e(t('admin.next_page')) ?> <i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
