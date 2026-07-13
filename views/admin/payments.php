<?php
$title = __('admin.payments');
ob_start();
?>
<table class="admin-table" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr style="background:#333;color:#fff;">
            <th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.plan_col') ?></th><th><?= __('admin.status') ?></th><th><?= __('admin.created_at') ?></th><th>Renews</th><th>Paddle Sub ID</th><th>Cancellation</th><th>Pending Change</th><th>Refund</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $pay):
            $needsAction = (!empty($pay['cancel_requested_at']) && ($pay['cancel_method'] ?? '') === 'manual') || !empty($pay['refund_requested_at']);
        ?>
        <tr style="border-bottom:1px solid #444;<?= $needsAction ? 'background:#4a2e00;' : '' ?>">
            <td><?php echo htmlspecialchars($pay['id']); ?></td>
            <td><?php echo htmlspecialchars($pay['email']); ?></td>
            <td><?php echo htmlspecialchars($pay['plan_status']); ?></td>
            <td><?php echo $pay['has_paid'] ? __('admin.paid') : __('admin.unpaid'); ?></td>
            <td><?php echo htmlspecialchars($pay['created_at']); ?></td>
            <td><?php echo htmlspecialchars($pay['next_billed_at'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($pay['paddle_subscription_id'] ?? '—'); ?></td>
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
<p style="margin-top:20px;">
    <a href="?page=admin-export&type=payments" style="background:#28a745;color:#fff;padding:8px 12px;text-decoration:none;"><?= __('admin.csv_download') ?></a>
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
