<?php
$title = 'İşlem kaydı';
$pageHeader = 'İşlem kaydı';
$pagePretitle = number_format($totalCount, 0, ',', '.') . ' admin işlemi · kim, ne zaman, kime, nereden';
$e = fn($v) => htmlspecialchars((string)$v);
$labels = [
    'admin_login' => ['Giriş (şifre)', 'secondary'], 'admin_login_2fa' => ['Giriş (2FA)', 'green'],
    'admin_backup_code_used' => ['Yedek kodla giriş', 'yellow'], 'admin_2fa_enabled' => ['2FA açıldı', 'green'],
    'admin_2fa_disabled' => ['2FA kapatıldı', 'red'], 'admin_2fa_backup_regenerated' => ['Yedek kodlar yenilendi', 'blue'],
    'admin_created' => ['Admin eklendi', 'blue'], 'admin_deleted' => ['Admin kaldırıldı', 'red'],
    'user_plan_changed' => ['Plan değiştirildi', 'purple'], 'user_bonus_added' => ['Bonus mesaj', 'teal'],
    'user_usage_reset' => ['Kullanım sıfırlandı', 'teal'], 'user_email_verified' => ['E-posta doğrulandı', 'teal'],
    'user_password_reset_sent' => ['Şifre sıfırlama gönderildi', 'blue'], 'user_suspended' => ['Askıya alındı', 'orange'],
    'user_unsuspended' => ['Askı kaldırıldı', 'green'], 'user_deleted' => ['Kullanıcı silindi', 'red'],
    'conversation_viewed' => ['Sohbet görüntülendi', 'yellow'], 'refund_handled' => ['İade kapatıldı', 'orange'],
    'cancellation_handled' => ['İptal kapatıldı', 'orange'],
];
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-audit'], $filters, $o), fn($v) => $v !== '' && $v !== null && $v !== 0));

ob_start();
?>
<div class="card">
    <div class="card-header">
        <form method="GET" class="d-flex flex-wrap gap-2 ms-auto">
            <input type="hidden" name="page" value="admin-audit">
            <?php if ($filters['user']): ?><input type="hidden" name="user" value="<?= (int)$filters['user'] ?>"><span class="badge bg-primary-lt align-self-center">Kullanıcı #<?= (int)$filters['user'] ?></span><?php endif; ?>
            <select name="action" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="">Tüm işlemler</option>
                <?php foreach ($actions as $a): ?><option value="<?= $e($a) ?>"<?= $filters['action'] === $a ? ' selected' : '' ?>><?= $e($labels[$a][0] ?? $a) ?></option><?php endforeach; ?>
            </select>
            <select name="admin" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="">Tüm adminler</option>
                <?php foreach ($adminEmails as $a): ?><option value="<?= $e($a) ?>"<?= $filters['admin'] === $a ? ' selected' : '' ?>><?= $e($a) ?></option><?php endforeach; ?>
            </select>
            <?php if (array_filter($filters)): ?><a href="?page=admin-audit" class="btn btn-sm btn-ghost-secondary">Temizle</a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Zaman</th><th>Admin</th><th>İşlem</th><th>Kullanıcı</th><th>Detay</th><th>IP</th></tr></thead>
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
                            <?php else: ?><span class="text-secondary">#<?= (int)$r['target_user_id'] ?> (silinmiş)</span><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-secondary small" style="min-width:180px;max-width:320px;"><?= $e($r['detail'] ?? '') ?></td>
                    <td><code class="small"><?= $e($r['ip'] ?? '') ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-5">Kayıt yok.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-secondary small">Sayfa <?= $pageNum ?> / <?= $totalPages ?></p>
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
