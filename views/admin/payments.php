<?php
$title = __('admin.payments');
ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
    <h2 style="margin:0;"><?= __('admin.payments') ?></h2>
    <form method="GET" action="" style="display:flex;gap:8px;">
        <input type="hidden" name="page" value="admin-payments">
        <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="<?= __('admin.search_email') ?>" class="form-control form-control-sm" style="width:220px;">
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= __('admin.search') ?></button>
    </form>
</div>
<table class="table table-hover table-striped">
    <thead>
        <tr>
            <th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.plan_col') ?></th><th><?= __('admin.status') ?></th><th><?= __('admin.created_at') ?></th><th>Renews</th><th>Subscription ID</th><th>Cancellation</th><th>Pending Change</th><th>Refund</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="10" style="text-align:center;color:#9aa0a6;padding:24px;"><?= __('admin.no_results') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $pay):
            $needsAction = (!empty($pay['cancel_requested_at']) && ($pay['cancel_method'] ?? '') === 'manual') || !empty($pay['refund_requested_at']);
        ?>
        <tr<?= $needsAction ? ' style="background:#4a2e00;"' : '' ?>>
            <td><?php echo htmlspecialchars($pay['id']); ?></td>
            <td><?php echo htmlspecialchars($pay['email']); ?></td>
            <td><?php echo htmlspecialchars($pay['plan_status']); ?></td>
            <td><?php echo $pay['has_paid'] ? __('admin.paid') : __('admin.unpaid'); ?></td>
            <td><?php echo htmlspecialchars($pay['created_at']); ?></td>
            <td><?php echo htmlspecialchars($pay['next_billed_at'] ?? '—'); ?></td>
            <td><?php if (!empty($pay['dodo_subscription_id'])): ?>Dodo: <?php echo htmlspecialchars($pay['dodo_subscription_id']); ?><?php elseif (!empty($pay['fastspring_subscription_id'])): ?>FastSpring: <?php echo htmlspecialchars($pay['fastspring_subscription_id']); ?><?php else: ?><?php echo htmlspecialchars($pay['paddle_subscription_id'] ?? '—'); ?><?php endif; ?></td>
            <td>
                <?php if (!empty($pay['cancel_requested_at'])): ?>
                    <?php if (($pay['cancel_method'] ?? '') === 'manual'): ?>
                        <strong style="color:#ffb84d;">Manual action needed</strong>
                    <?php else: ?>
                        Scheduled (API) — requested <?php echo htmlspecialchars($pay['cancel_requested_at']); ?>
                    <?php endif; ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td><?php echo !empty($pay['pending_plan_change']) ? 'To: ' . htmlspecialchars($pay['pending_plan_change']) : '—'; ?></td>
            <td>
                <?php if (!empty($pay['refund_requested_at'])): ?>
                    <strong style="color:#ffb84d;">Requested <?php echo htmlspecialchars($pay['refund_requested_at']); ?></strong>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if (($totalPages ?? 1) > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:16px;">
    <?php $qs = $search !== '' ? '&q=' . urlencode($search) : ''; ?>
    <?php if ($pageNum > 1): ?>
        <a href="?page=admin-payments&p=<?= $pageNum - 1 ?><?= $qs ?>" class="btn btn-sm btn-outline-secondary">&larr; <?= __('admin.prev_page') ?></a>
    <?php endif; ?>
    <span style="align-self:center;color:#9aa0a6;font-size:13px;"><?= __('admin.page_label') ?> <?= $pageNum ?> / <?= $totalPages ?></span>
    <?php if ($pageNum < $totalPages): ?>
        <a href="?page=admin-payments&p=<?= $pageNum + 1 ?><?= $qs ?>" class="btn btn-sm btn-outline-secondary"><?= __('admin.next_page') ?> &rarr;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<p style="margin-top:20px;">
    <a href="?page=admin-export&type=payments" style="background:#28a745;color:#fff;padding:8px 12px;text-decoration:none;"><?= __('admin.csv_download') ?></a>
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
