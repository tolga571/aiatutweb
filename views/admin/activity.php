<?php
$title = 'Olay akışı';
$pageHeader = 'Olay akışı';
$pagePretitle = number_format($totalCount, 0, ',', '.') . ' abonelik ve hesap olayı';

$e = fn($v) => htmlspecialchars((string)$v);
$eventLabels = [
    'subscription_started'    => ['Yeni abonelik', 'green'],
    'subscription_upgraded'   => ['Yükseltme', 'blue'],
    'subscription_downgraded' => ['Düşürme', 'yellow'],
    'downgrade_scheduled'     => ['Düşürme planlandı', 'yellow'],
    'cancellation_requested'  => ['İptal talebi', 'orange'],
    'subscription_canceled'   => ['İptal edildi', 'red'],
    'cancellation_resumed'    => ['İptal geri alındı', 'teal'],
    'refund_requested'        => ['İade talebi', 'orange'],
    'account_deleted'         => ['Hesap silindi', 'red'],
];
$planLabels = ['inactive' => 'Pasif', 'trial' => 'Deneme', 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$providers = ['dodo' => 'Dodo', 'paddle' => 'Paddle (test)', 'fastspring' => 'FastSpring'];
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-activity'], $filters, $o), fn($v) => $v !== '' && $v !== null));

ob_start();
?>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2 ms-auto">
            <input type="hidden" name="page" value="admin-activity">
            <select name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="">Tüm olaylar</option>
                <?php foreach ($eventTypes as $t): ?>
                    <option value="<?= $e($t) ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= $e($eventLabels[$t][0] ?? $t) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="provider" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="">Tüm sağlayıcılar</option>
                <?php foreach ($providers as $v => $l): ?>
                    <option value="<?= $v ?>"<?= $filters['provider'] === $v ? ' selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filters['type'] !== '' || $filters['provider'] !== ''): ?><a href="?page=admin-activity" class="btn btn-sm btn-ghost-secondary">Temizle</a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>Olay</th><th>Sağlayıcı</th><th>Plan</th><th>Detay</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev):
                [$label, $color] = $eventLabels[$ev['event_type']] ?? [$ev['event_type'], 'secondary']; ?>
                <tr>
                    <td class="text-secondary text-nowrap"><?= date('d.m.Y H:i', strtotime($ev['created_at'])) ?></td>
                    <td class="text-truncate" style="max-width:220px;">
                        <?php if ($ev['user_id'] && $ev['user_email']): ?>
                            <a href="?page=admin-user&amp;id=<?= (int)$ev['user_id'] ?>"><?= $e($ev['user_email']) ?></a>
                        <?php else: ?><span class="text-secondary">silinmiş kullanıcı</span><?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $color ?>-lt"><?= $e($label) ?></span></td>
                    <td><?= $e($providers[$ev['provider'] ?? ''] ?? ($ev['provider'] ?: '—')) ?></td>
                    <td class="text-nowrap"><?= $e($planLabels[$ev['plan'] ?? ''] ?? ($ev['plan'] ?: '—')) ?><?= $ev['billing_interval'] ? ' <span class="text-secondary small">' . ($ev['billing_interval'] === 'year' ? 'yıllık' : 'aylık') . '</span>' : '' ?></td>
                    <td class="text-secondary small text-truncate" style="max-width:320px;" title="<?= $e($ev['detail']) ?>"><?= $e($ev['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$events): ?><tr><td colspan="6" class="text-center text-secondary py-5">Kayıtlı olay yok.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small">Sayfa <?= $pageNum ?> / <?= $totalPages ?></p>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i> Önceki</a></li>
            <li class="page-item<?= $pageNum >= $totalPages ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>">Sonraki <i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
