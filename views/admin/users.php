<?php
$title = __('admin.users');
ob_start();
?>
<h2><?= __('admin.user_list') ?></h2>
<a href="?page=admin-export&type=users" class="btn btn-primary btn-sm mb-2"><?= __('admin.csv_download') ?></a>
<table class="table table-hover table-striped">
    <thead>
        <tr><th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.name_col') ?></th><th><?= __('admin.xp_col') ?></th><th><?= __('admin.payment_col') ?></th><th><?= __('admin.plan_col') ?></th></tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['xp']) ?></td>
                <td><?= $u['has_paid'] ? __('admin.yes') : __('admin.no') ?></td>
                <td><?= htmlspecialchars($u['plan_status']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
