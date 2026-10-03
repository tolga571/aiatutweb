<?php
$title = __('admin.conversations');
$pageHeader = __('admin.conversations');
$pagePretitle = t('admin.convs_pretitle', ['n' => number_format($totalCount, 0, ',', '.')]);
$e = fn($v) => htmlspecialchars((string)$v);
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-conversations', 'q' => $search], $o), fn($v) => $v !== '' && $v !== null));

ob_start();
?>
<div class="card">
    <div class="card-header">
        <form method="GET" class="d-flex gap-2 w-100" style="max-width:420px;">
            <input type="hidden" name="page" value="admin-conversations">
            <div class="input-icon flex-fill">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="search" name="q" value="<?= $e($search) ?>" class="form-control" placeholder="<?= $e(t('admin.convs_search')) ?>">
            </div>
            <button class="btn btn-primary"><?= $e(t('admin.search')) ?></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th><?= $e(t('admin.col_conversation')) ?></th><th><?= $e(t('admin.user_col')) ?></th><th><?= $e(t('admin.topic_col')) ?></th><th class="text-end"><?= $e(t('admin.col_messages')) ?></th><th><?= $e(t('admin.col_last')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($convs as $c): ?>
                <tr>
                    <td class="text-nowrap">#<?= (int)$c['id'] ?></td>
                    <td style="min-width:200px;max-width:280px;">
                        <a href="?page=admin-user&amp;id=<?= (int)$c['user_id'] ?>" class="d-block text-truncate"><?= $e($c['user_email']) ?></a>
                        <div class="text-secondary small text-truncate"><?= $e(mb_substr((string)$c['first_message'], 0, 80)) ?></div>
                    </td>
                    <td><?= $e($c['topic_id'] ?: t('admin.topic_free')) ?></td>
                    <td class="text-end"><?= (int)$c['user_messages'] ?></td>
                    <td class="text-secondary text-nowrap"><?= date('d.m.Y H:i', strtotime($c['updated_at'])) ?></td>
                    <td class="text-end"><a href="?page=admin-conversation&amp;conv_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-ghost-primary"><?= $e(t('admin.open')) ?> <i class="ti ti-chevron-right"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$convs): ?><tr><td colspan="6" class="text-center text-secondary py-5"><?= $e(t('admin.convs_none')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small"><?= $e(t('admin.page_of', ['p' => $pageNum, 'pages' => $totalPages])) ?></p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i></a></li>
            <li class="page-item<?= $pageNum >= $totalPages ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>"><i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
