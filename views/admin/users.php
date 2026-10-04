<?php
$title = __('admin.users');
$pageHeader = __('admin.users');
$pagePretitle = number_format($result['total'], 0, ',', '.') . ' kullanıcı';
$pageActions = '<a href="?page=admin-export&amp;type=users" class="btn btn-outline-secondary"><i class="ti ti-download me-1"></i>CSV indir</a>';

$e = fn($v) => htmlspecialchars((string)$v);
$planLabels = ['inactive' => 'Pasif', 'trial' => 'Deneme', 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
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
                <label class="form-label small text-secondary">Ara</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="q" value="<?= $e($filters['q']) ?>" class="form-control" placeholder="E-posta, isim veya #ID">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary">Plan</label>
                <?= $select('plan', ['' => 'Tümü'] + $planLabels, $filters['plan']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary">Ödeme</label>
                <?= $select('pay', ['' => 'Tümü', 'real' => 'Gerçek (Dodo)', 'test' => 'Test', 'free' => 'Ödemesiz'], $filters['pay']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary">Öğrendiği dil</label>
                <?= $select('lang', ['' => 'Tümü'] + array_combine($langs, array_map(fn($l) => __('languages.' . $l), $langs)), $filters['lang']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary">Durum</label>
                <?= $select('status', ['' => 'Tümü', 'suspended' => 'Askıda', 'unverified' => 'E-posta doğrulanmamış'], $filters['status']) ?>
            </div>
            <div class="col-6 col-md-3 col-lg">
                <label class="form-label small text-secondary">Sırala</label>
                <?= $select('sort', ['new' => 'En yeni', 'old' => 'En eski', 'active' => 'Son aktif', 'messages' => 'En çok mesaj'], $filters['sort']) ?>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="ti ti-filter me-1"></i>Uygula</button>
                <?php if (array_filter(array_diff_key($filters, ['sort' => 1]))): ?>
                    <a href="?page=admin-users" class="btn btn-ghost-secondary">Temizle</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead>
                <tr><th>Kullanıcı</th><th>Plan</th><th>Dil</th><th class="text-end">Mesaj</th><th>Son aktif</th><th>Kayıt</th><th></th></tr>
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
                        <?php if ($isTest): ?><span class="badge bg-secondary text-white" title="Ödemesi Dodo'dan değil (Paddle sandbox / elle verilmiş)">TEST</span><?php endif; ?>
                        <?php if ($isReal): ?><span class="badge bg-green-lt" title="Gerçek Dodo aboneliği">Dodo</span><?php endif; ?>
                        <?php if (!empty($u['suspended_at'])): ?><span class="badge bg-red-lt">Askıda</span><?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= $e(strtoupper((string)$u['native_lang'])) ?> → <?= $e(strtoupper((string)$u['target_lang'])) ?></td>
                    <td class="text-end"><?= number_format((int)$u['message_count'], 0, ',', '.') ?></td>
                    <td class="text-secondary text-nowrap"><?= $u['last_activity_date'] ? date('d.m.Y', strtotime($u['last_activity_date'])) : '—' ?></td>
                    <td class="text-secondary text-nowrap"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-end"><a href="<?= $url ?>" class="btn btn-sm btn-ghost-primary">Detay <i class="ti ti-chevron-right"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$result['rows']): ?>
                <tr><td colspan="7" class="text-center text-secondary py-5"><i class="ti ti-user-search fs-1 d-block mb-2"></i>Bu filtrelere uyan kullanıcı yok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small">Sayfa <?= $pageNum ?> / <?= $result['pages'] ?></p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i> Önceki</a></li>
            <li class="page-item<?= $pageNum >= $result['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>">Sonraki <i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
