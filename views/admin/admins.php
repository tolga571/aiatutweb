<?php
$title = __('admin.admins');
$isFullAdmin = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);
ob_start();
?>
<h2><?= __('admin.admin_list') ?></h2>

<?php if (!empty($adminsError)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($adminsError) ?></div>
<?php endif; ?>

<a href="?page=admin-export&type=admins" class="btn btn-primary btn-sm mb-2"><?= __('admin.csv_download') ?></a>

<table class="table table-hover table-striped">
    <thead>
        <tr>
            <th><?= __('admin.id') ?></th><th><?= __('admin.email_col') ?></th><th><?= __('admin.name_col') ?></th><th><?= __('admin.role_col') ?></th><th><?= __('admin.created_at') ?></th>
            <?php if ($isFullAdmin): ?><th></th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($admins as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['id']) ?></td>
                <td><?= htmlspecialchars($a['email']) ?></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td>
                    <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;<?= $a['role'] === 'admin' ? 'background:#1f5f8f;color:#eaf5ff;' : 'background:#444;color:#ccc;' ?>">
                        <?= $a['role'] === 'admin' ? __('admin.role_full') : __('admin.role_viewer') ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($a['created_at']) ?></td>
                <?php if ($isFullAdmin): ?>
                <td>
                    <?php if ((int)$a['id'] !== $currentAdminId): ?>
                    <form method="POST" action="?page=admin-delete-admin" onsubmit="return confirm('<?= htmlspecialchars(__('admin.confirm_remove')) ?>');" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= __('admin.remove') ?></button>
                    </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($isFullAdmin): ?>
<div style="max-width:420px;margin-top:24px;padding:16px;border:1px solid #444;border-radius:8px;">
    <h3 style="margin-top:0;"><?= __('admin.add_admin') ?></h3>
    <form method="POST" action="?page=admin-create-admin">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group mb-2">
            <label class="form-label"><?= __('admin.email') ?></label>
            <input type="email" name="email" required class="form-control">
        </div>
        <div class="form-group mb-2">
            <label class="form-label"><?= __('admin.name_col') ?></label>
            <input type="text" name="name" class="form-control">
        </div>
        <div class="form-group mb-2">
            <label class="form-label"><?= __('admin.password') ?></label>
            <input type="password" name="password" required minlength="8" class="form-control">
        </div>
        <div class="form-group mb-3">
            <label class="form-label"><?= __('admin.role_col') ?></label>
            <select name="role" class="form-control">
                <option value="viewer"><?= __('admin.role_viewer') ?></option>
                <option value="admin"><?= __('admin.role_full') ?></option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= __('admin.add_admin') ?></button>
    </form>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
