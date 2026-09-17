<?php
$title = __('admin.users');
ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
    <h2 style="margin:0;"><?= __('admin.user_list') ?></h2>
    <form method="GET" action="" style="display:flex;gap:8px;">
        <input type="hidden" name="page" value="admin-users">
        <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="<?= __('admin.search_email') ?>" class="form-control form-control-sm" style="width:220px;">
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= __('admin.search') ?></button>
    </form>
</div>
<a href="?page=admin-export&type=users" class="btn btn-primary btn-sm mb-2"><?= __('admin.csv_download') ?></a>
<table class="table table-hover table-striped">
    <thead>
        <tr><th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.name_col') ?></th><th><?= __('admin.xp_col') ?></th><th><?= __('admin.payment_col') ?></th><th><?= __('admin.plan_col') ?></th></tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="6" style="text-align:center;color:#9aa0a6;padding:24px;"><?= __('admin.no_results') ?></td></tr>
        <?php endif; ?>
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
<?php if (($totalPages ?? 1) > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:16px;">
    <?php $qs = $search !== '' ? '&q=' . urlencode($search) : ''; ?>
    <?php if ($pageNum > 1): ?>
        <a href="?page=admin-users&p=<?= $pageNum - 1 ?><?= $qs ?>" class="btn btn-sm btn-outline-secondary">&larr; <?= __('admin.prev_page') ?></a>
    <?php endif; ?>
    <span style="align-self:center;color:#9aa0a6;font-size:13px;"><?= __('admin.page_label') ?> <?= $pageNum ?> / <?= $totalPages ?></span>
    <?php if ($pageNum < $totalPages): ?>
        <a href="?page=admin-users&p=<?= $pageNum + 1 ?><?= $qs ?>" class="btn btn-sm btn-outline-secondary"><?= __('admin.next_page') ?> &rarr;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
