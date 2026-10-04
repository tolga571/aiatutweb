<?php
$title = __('admin.users');
$pageHeader = __('admin.users');
$pagePretitle = t('admin.users_pretitle', ['n' => number_format($result['total'], 0, ',', '.')]);
$pageActions = '<a href="?page=admin-export&amp;type=users" class="btn btn-outline-secondary"><i class="ti ti-download me-1"></i>' . htmlspecialchars(t('admin.csv_download_short')) . '</a>';

$e = fn($v) => htmlspecialchars((string)$v);
$planLabels = ['inactive' => t('admin.plan_inactive'), 'trial' => t('admin.plan_trial'), 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$planColors = ['inactive' => 'secondary', 'trial' => 'yellow', 'starter' => 'blue', 'pro' => 'purple', 'active' => 'orange'];
$langs = \App\Src\Language::supportedLangs();
$qs = function (array $override) use ($filters): string {
    return '?' . http_build_query(array_filter(array_merge(['page' => 'admin-users'], $filters, $override), fn($v) => $v !== '' && $v !== null));
};
$select = function (string $name, array $options, string $current) use ($e): string {
    $html = '<select name="' . $name . '" class="form-select" style="min-width:130px" onchange="this.form.submit()">';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . $e($value) . '"' . ((string)$value === $current ? ' selected' : '') . '>' . $e($label) . '</option>';
    }
    return $html . '</select>';
};

ob_start();
?>
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="admin-users">
            <div class="col-12 col-lg-4">
                <label class="form-label small text-secondary"><?= $e(t('admin.search')) ?></label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="q" value="<?= $e($filters['q']) ?>" class="form-control" placeholder="<?= $e(t('admin.users_search_ph')) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary">Plan</label>
                <?= $select('plan', ['' => t('admin.lang_filter_all')] + $planLabels, $filters['plan']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary"><?= $e(t('admin.payment_col')) ?></label>
                <?= $select('pay', ['' => t('admin.lang_filter_all'), 'real' => t('admin.pay_real'), 'test' => 'Test', 'free' => t('admin.pay_free')], $filters['pay']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary"><?= $e(t('admin.learning_lang')) ?></label>
                <?= $select('lang', ['' => t('admin.lang_filter_all')] + array_combine($langs, array_map(fn($l) => \App\Src\Language::displayName($l), $langs)), $filters['lang']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary"><?= $e(t('admin.status')) ?></label>
                <?= $select('status', ['' => t('admin.lang_filter_all'), 'suspended' => t('admin.suspended'), 'unverified' => t('admin.email_unverified')], $filters['status']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary"><?= $e(t('admin.sort')) ?></label>
                <?= $select('sort', ['new' => t('admin.sort_new'), 'old' => t('admin.sort_old'), 'active' => t('admin.last_active'), 'messages' => t('admin.sort_messages')], $filters['sort']) ?>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="ti ti-filter me-1"></i><?= $e(t('admin.apply')) ?></button>
                <?php if (array_filter(array_diff_key($filters, ['sort' => 1]))): ?>
                    <a href="?page=admin-users" class="btn btn-ghost-secondary"><?= $e(t('admin.clear')) ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr><th><?= $e(t('admin.user_col')) ?></th><th>Plan</th><th><?= $e(t('admin.col_language')) ?></th><th class="text-end"><?= $e(t('admin.col_messages')) ?></th><th><?= $e(t('admin.last_active')) ?></th><th><?= $e(t('admin.col_signup')) ?></th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $u):
                $isTest = (int)$u['has_paid'] === 1 && empty($u['dodo_subscription_id']);
                $isReal = (int)$u['has_paid'] === 1 && !empty($u['dodo_subscription_id']);
                $url = '?page=admin-user&amp;id=' . (int)$u['id']; ?>
                <tr style="cursor:pointer" onclick="location.href='?page=admin-user&id=<?= (int)$u['id'] ?>'">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm bg-primary-lt"><?= $e(mb_strtoupper(mb_substr($u['name'] ?: $u['email'], 0, 1))) ?></span>
                            <div class="text-truncate" style="max-width:280px;">
                                <a href="<?= $url ?>" class="text-reset fw-semibold"><?= $e($u['name'] ?: '—') ?></a>
                                <div class="text-secondary small text-truncate">#<?= (int)$u['id'] ?> · <?= $e($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-nowrap">
                        <span class="badge bg-<?= $planColors[$u['plan_status']] ?? 'secondary' ?>-lt"><?= $e($planLabels[$u['plan_status']] ?? $u['plan_status']) ?></span>
                        <?php if ($isTest): ?><span class="badge bg-secondary text-white" title="<?= $e(t('admin.test_badge_help')) ?>">TEST</span><?php endif; ?>
                        <?php if ($isReal): ?><span class="badge bg-green-lt" title="<?= $e(t('admin.real_dodo_sub')) ?>">Dodo</span><?php endif; ?>
                        <?php if (!empty($u['suspended_at'])): ?><span class="badge bg-red-lt"><?= $e(t('admin.suspended')) ?></span><?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= $e(strtoupper((string)$u['native_lang'])) ?> → <?= $e(strtoupper((string)$u['target_lang'])) ?></td>
                    <td class="text-end"><?= number_format((int)$u['message_count'], 0, ',', '.') ?></td>
                    <td class="text-secondary text-nowrap"><?= $u['last_activity_date'] ? date('d.m.Y', strtotime($u['last_activity_date'])) : '—' ?></td>
                    <td class="text-secondary text-nowrap"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-end"><a href="<?= $url ?>" class="btn btn-sm btn-ghost-primary"><?= $e(t('admin.detail')) ?> <i class="ti ti-chevron-right"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$result['rows']): ?>
                <tr><td colspan="7" class="text-center text-secondary py-5"><i class="ti ti-user-search fs-1 d-block mb-2"></i><?= $e(t('admin.no_users_match')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small"><?= $e(t('admin.page_of', ['p' => $pageNum, 'pages' => $result['pages']])) ?></p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i> <?= $e(t('admin.prev_page')) ?></a></li>
            <li class="page-item<?= $pageNum >= $result['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>"><?= $e(t('admin.next_page')) ?> <i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
