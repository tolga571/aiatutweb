<?php
$title = t('admin.nav_audit');
$pageHeader = t('admin.nav_audit');
$pagePretitle = t('admin.audit_pretitle', ['n' => number_format($totalCount, 0, ',', '.')]);
$e = fn($v) => htmlspecialchars((string)$v);
// Badge colour per action; the label is t('admin.audit_<action>').
$colors = [
    'admin_login' => 'secondary',
    'admin_login_2fa' => 'green',
    'admin_backup_code_used' => 'yellow',
    'admin_2fa_enabled' => 'green',
    'admin_2fa_disabled' => 'red',
    'admin_2fa_backup_regenerated' => 'blue',
    'admin_created' => 'blue',
    'admin_deleted' => 'red',
    'user_plan_changed' => 'purple',
    'user_bonus_added' => 'teal',
    'user_usage_reset' => 'teal',
    'user_email_verified' => 'teal',
    'user_password_reset_sent' => 'blue',
    'user_suspended' => 'orange',
    'user_unsuspended' => 'green',
    'user_deleted' => 'red',
    'conversation_viewed' => 'yellow',
    'refund_handled' => 'orange',
    'cancellation_handled' => 'orange',
    'language_updated' => 'blue',
    'language_added' => 'blue',
    'language_ai_translated' => 'purple',
    'language_ai_cleared' => 'red',
    'ui_string_edited' => 'teal',
];
$labels = [];
foreach ($colors as $a => $c) {
    $labels[$a] = [t('admin.audit_' . $a), $c];
}
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-audit'], $filters, $o), fn($v) => $v !== '' && $v !== null && $v !== 0));

ob_start();
?>
<div class="card">
    <div class="card-header">
        <form method="GET" class="d-flex flex-wrap gap-2 ms-auto">
            <input type="hidden" name="page" value="admin-audit">
            <?php if ($filters['user']): ?><input type="hidden" name="user" value="<?= (int)$filters['user'] ?>"><span class="badge bg-primary-lt align-self-center"><?= $e(t('admin.user_col')) ?> #<?= (int)$filters['user'] ?></span><?php endif; ?>
            <select name="action" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value=""><?= $e(t('admin.all_actions')) ?></option>
                <?php foreach ($actions as $a): ?><option value="<?= $e($a) ?>"<?= $filters['action'] === $a ? ' selected' : '' ?>><?= $e($labels[$a][0] ?? $a) ?></option><?php endforeach; ?>
            </select>
            <select name="admin" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value=""><?= $e(t('admin.all_admins')) ?></option>
                <?php foreach ($adminEmails as $a): ?><option value="<?= $e($a) ?>"<?= $filters['admin'] === $a ? ' selected' : '' ?>><?= $e($a) ?></option><?php endforeach; ?>
            </select>
            <?php if (array_filter($filters)): ?><a href="?page=admin-audit" class="btn btn-sm btn-ghost-secondary"><?= $e(t('admin.clear')) ?></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th><?= $e(t('admin.col_time')) ?></th><th>Admin</th><th><?= $e(t('admin.col_action')) ?></th><th><?= $e(t('admin.user_col')) ?></th><th><?= $e(t('admin.detail')) ?></th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                [$label, $color] = $labels[$r['action']] ?? [$r['action'], 'secondary']; ?>
                <tr>
                    <td class="text-secondary text-nowrap"><?= date('d.m.Y H:i', strtotime($r['performed_at'])) ?></td>
                    <td class="text-truncate" style="max-width:200px;"><?= $e($r['admin_email'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $color ?>-lt text-nowrap"><?= $e($label) ?></span></td>
                    <td class="text-truncate" style="max-width:200px;">
                        <?php if ($r['target_user_id']): ?>
                            <?php if ($r['user_email']): ?><a href="?page=admin-user&amp;id=<?= (int)$r['target_user_id'] ?>"><?= $e($r['user_email']) ?></a>
                            <?php else: ?><span class="text-secondary">#<?= (int)$r['target_user_id'] ?> (<?= $e(t('admin.deleted')) ?>)</span><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-secondary small" style="min-width:180px;max-width:320px;"><?= $e($r['detail'] ?? '') ?></td>
                    <td><code class="small"><?= $e($r['ip'] ?? '') ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-5"><?= $e(t('admin.no_records')) ?></td></tr><?php endif; ?>
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
