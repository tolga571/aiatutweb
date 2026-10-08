<?php
$title = __('admin.nav_reports');
$pageHeader = __('admin.nav_reports');
$pagePretitle = t('admin.reports_pretitle', ['n' => number_format($counts['open'] ?? 0, 0, ',', '.')]);
$e = fn($v) => htmlspecialchars((string)$v);
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-reports', 'status' => $status], $o), fn($v) => $v !== '' && $v !== null));
$reasonLabel = fn(string $r) => t('admin.report_reason_' . $r) === 'admin.report_reason_' . $r ? $r : t('admin.report_reason_' . $r);
$badge = ['open' => 'bg-orange', 'reviewed' => 'bg-green', 'dismissed' => 'bg-secondary'];
/** One small POST form posting to admin-report-action. */
$action = fn(int $id, string $to, string $label, string $cls) =>
    '<form method="POST" action="?page=admin-report-action" class="d-inline">'
    . '<input type="hidden" name="csrf" value="' . $e($csrf) . '">'
    . '<input type="hidden" name="id" value="' . $id . '">'
    . '<input type="hidden" name="status" value="' . $e($to) . '">'
    . '<input type="hidden" name="back" value="' . $e($status) . '">'
    . '<button class="btn btn-sm ' . $cls . '">' . $e($label) . '</button></form>';

ob_start();
?>
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <?php foreach (['open', 'reviewed', 'dismissed', 'all'] as $s): ?>
            <li class="nav-item">
                <a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="<?= $e($qs(['status' => $s, 'p' => null])) ?>">
                    <?= $e(t('admin.report_status_' . $s)) ?>
                    <?php if ($s !== 'all'): ?><span class="badge ms-2"><?= (int)($counts[$s] ?? 0) ?></span><?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th><?= $e(t('admin.col_date')) ?></th><th><?= $e(t('admin.user_col')) ?></th><th><?= $e(t('admin.report_reason')) ?></th><th><?= $e(t('admin.report_reply')) ?></th><th><?= $e(t('admin.col_status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reports as $r): ?>
                <tr>
                    <td class="text-secondary text-nowrap">#<?= (int)$r['id'] ?><div class="small"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></div><div class="small"><?= $e($r['source']) ?></div></td>
                    <td style="min-width:180px;max-width:240px;">
                        <?php if ($r['user_id']): ?><a href="?page=admin-user&amp;id=<?= (int)$r['user_id'] ?>" class="d-block text-truncate"><?= $e($r['user_email'] ?: '#' . $r['user_id']) ?></a><?php else: ?><span class="text-secondary"><?= $e(t('admin.report_user_deleted')) ?></span><?php endif; ?>
                    </td>
                    <td class="text-nowrap"><strong><?= $e($reasonLabel((string)$r['reason'])) ?></strong><?php if ($r['note'] !== ''): ?><div class="text-secondary small text-wrap" style="max-width:220px;"><?= $e($r['note']) ?></div><?php endif; ?></td>
                    <td style="min-width:260px;">
                        <div class="text-wrap small" style="max-height:7.5em;overflow:auto;"><?= nl2br($e($r['message_snapshot'])) ?></div>
                        <?php if ($r['conversation_id']): ?><a href="?page=admin-conversation&amp;conv_id=<?= (int)$r['conversation_id'] ?>" class="small"><?= $e(t('admin.report_open_conv')) ?> <i class="ti ti-chevron-right"></i></a><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $badge[$r['status']] ?? 'bg-secondary' ?> text-white"><?= $e(t('admin.report_status_' . $r['status'])) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($r['status'] === 'open'): ?>
                            <?= $action((int)$r['id'], 'reviewed', t('admin.report_mark_reviewed'), 'btn-success') ?>
                            <?= $action((int)$r['id'], 'dismissed', t('admin.report_dismiss'), 'btn-outline-secondary') ?>
                        <?php else: ?>
                            <?= $action((int)$r['id'], 'open', t('admin.report_reopen'), 'btn-ghost-secondary') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reports): ?><tr><td colspan="6" class="text-center text-secondary py-5"><?= $e(t('admin.reports_none')) ?></td></tr><?php endif; ?>
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
