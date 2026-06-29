<?php
$title = __('admin.conv_detail');
ob_start();
?>
<h2 class="my-4"><?= __('admin.conv_detail') ?></h2>

<a href="?page=admin-conversations" class="btn btn-sm btn-outline-primary mb-3"><?= __('admin.back_to_convs') ?></a>

<?php if (empty($messages)): ?>
    <div class="alert alert-warning" role="alert">
        <?= __('admin.no_messages') ?>
    </div>
<?php else: ?>
    <table class="table table-hover table-striped table-conversation-detail">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th><?= __('admin.role') ?></th>
                <th><?= __('admin.content') ?></th>
                <th><?= __('admin.translation') ?></th>
                <th><?= __('admin.correction') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($messages as $index => $msg): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($msg['role'] ?? '-') ?></td>
                    <td><?= nl2br(htmlspecialchars($msg['content'] ?? '-')) ?></td>
                    <td><?= nl2br(htmlspecialchars($msg['translation'] ?? '-')) ?></td>
                    <td><?= nl2br(htmlspecialchars($msg['correction'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
