<?php
$title = __('admin.payments');
ob_start();
?>
<table class="admin-table" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr style="background:#333;color:#fff;">
            <th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.plan_col') ?></th><th><?= __('admin.status') ?></th><th><?= __('admin.created_at') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $pay): ?>
        <tr style="border-bottom:1px solid #444;">
            <td><?php echo htmlspecialchars($pay['id']); ?></td>
            <td><?php echo htmlspecialchars($pay['email']); ?></td>
            <td><?php echo htmlspecialchars($pay['plan_status']); ?></td>
            <td><?php echo $pay['has_paid'] ? __('admin.paid') : __('admin.unpaid'); ?></td>
            <td><?php echo htmlspecialchars($pay['created_at']); ?></td>
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
