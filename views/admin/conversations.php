<?php
$title = __('admin.conversations');
ob_start();
?>
<h2><?= __('admin.conv_list') ?></h2>
<table>
    <thead>
        <tr><th><?= __('admin.id') ?></th><th><?= __('admin.user_col') ?></th><th><?= __('admin.topic_col') ?></th><th><?= __('admin.created_at') ?></th><th><?= __('admin.updated_at') ?></th><th><?= __('admin.detail') ?></th></tr>
    </thead>
    <tbody>
        <?php foreach ($convs as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['id']) ?></td>
                <td><?= htmlspecialchars($c['user_email']) ?></td>
                <td><?= htmlspecialchars($c['topic_id'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['created_at']) ?></td>
                <td><?= htmlspecialchars($c['updated_at']) ?></td>
                <td><a href="?page=admin-conversation&conv_id=<?= (int)$c['id'] ?>" class="btn"><?= __('admin.view') ?></a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
