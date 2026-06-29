<?php
$title = __('admin.admins');
ob_start();
?>
<h2><?= __('admin.admin_list') ?></h2>
<a href="?page=admin-export&type=admins" class="btn"><?= __('admin.csv_download') ?></a>
<table>
    <thead>
        <tr><th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.name_col') ?></th><th><?= __('admin.created_at') ?></th></tr>
    </thead>
    <tbody>
        <?php foreach ($admins as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['id']) ?></td>
                <td><?= htmlspecialchars($a['email']) ?></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><?= htmlspecialchars($a['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
