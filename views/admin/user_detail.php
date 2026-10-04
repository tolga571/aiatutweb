<?php
$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v, int $dec = 0) => number_format((float)$v, $dec, ',', '.');
$date = fn($v, string $fmt = 'd.m.Y H:i') => $v ? date($fmt, strtotime((string)$v)) : '—';
$planLabels = ['inactive' => 'Pasif', 'trial' => 'Deneme', 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$planColors = ['inactive' => 'secondary', 'trial' => 'yellow', 'starter' => 'blue', 'pro' => 'purple', 'active' => 'orange'];
$actionLabels = [
    'subscription_started' => 'Yeni abonelik', 'subscription_upgraded' => 'Plan yükseltti', 'subscription_downgraded' => 'Plan düşürdü',
    'downgrade_scheduled' => 'Düşürme planlandı', 'cancellation_requested' => 'İptal talebi', 'subscription_canceled' => 'Abonelik iptal',
    'cancellation_resumed' => 'İptal geri alındı', 'refund_requested' => 'İade talebi', 'account_deleted' => 'Hesap silindi',
    'user_plan_changed' => 'Admin planı değiştirdi', 'user_bonus_added' => 'Admin bonus mesaj verdi', 'user_usage_reset' => 'Admin kullanımı sıfırladı',
    'user_email_verified' => 'Admin e-postayı doğruladı', 'user_password_reset_sent' => 'Admin şifre sıfırlama gönderdi',
    'user_suspended' => 'Admin hesabı askıya aldı', 'user_unsuspended' => 'Admin hesabı açtı', 'conversation_viewed' => 'Admin sohbeti görüntüledi',
];

$u = $user;
$uid = (int)$u['id'];
$isReal = (int)$u['has_paid'] === 1 && !empty($u['dodo_subscription_id']);
$isTest = (int)$u['has_paid'] === 1 && empty($u['dodo_subscription_id']);
$provider = !empty($u['dodo_subscription_id']) ? 'Dodo' : (!empty($u['fastspring_subscription_id']) ? 'FastSpring' : (!empty($u['paddle_subscription_id']) ? 'Paddle (sandbox)' : '—'));
$subId = $u['dodo_subscription_id'] ?: ($u['fastspring_subscription_id'] ?: ($u['paddle_subscription_id'] ?: ''));
$quotaTotal = $quotaLimit + $details['quota']['bonus'];
$quotaPct = $quotaTotal > 0 ? min(100, $details['quota']['used'] / $quotaTotal * 100) : 0;
$c = $details['counts'];

$title = ($u['name'] ?: $u['email']) . ' · Kullanıcı';
$pageHeader = $u['name'] ?: $u['email'];
$pagePretitle = 'Kullanıcı #' . $uid;
$pageActions = '<a href="?page=admin-users" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Kullanıcılar</a>';

/** One small POST form posting to admin-user-action. */
$form = function (string $action, string $inner, string $confirm = '') use ($csrf, $uid, $e): string {
    return '<form method="POST" action="?page=admin-user-action" class="d-flex gap-2 align-items-center flex-wrap"'
        . ($confirm !== '' ? ' onsubmit="return confirm(' . $e(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ')"' : '') . '>'
        . '<input type="hidden" name="csrf" value="' . $e($csrf) . '">'
        . '<input type="hidden" name="id" value="' . $uid . '">'
        . '<input type="hidden" name="action" value="' . $e($action) . '">' . $inner . '</form>';
};

ob_start();
?>
<div class="row row-cards">
    <!-- Left: profile -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <span class="avatar avatar-xl mb-3 bg-primary-lt" style="font-size:1.6rem;"><?= $e(mb_strtoupper(mb_substr($u['name'] ?: $u['email'], 0, 1))) ?></span>
                <h3 class="m-0 mb-1"><?= $e($u['name'] ?: '—') ?></h3>
                <div class="text-secondary text-break"><?= $e($u['email']) ?></div>
                <div class="mt-3 d-flex flex-wrap gap-1 justify-content-center">
                    <span class="badge bg-<?= $planColors[$u['plan_status']] ?? 'secondary' ?>-lt"><?= $e($planLabels[$u['plan_status']] ?? $u['plan_status']) ?></span>
                    <?php if ($isReal): ?><span class="badge bg-green-lt">Gerçek abone · Dodo</span><?php endif; ?>
                    <?php if ($isTest): ?><span class="badge bg-secondary text-white">TEST</span><?php endif; ?>
                    <?php if (!empty($u['suspended_at'])): ?><span class="badge bg-red-lt">Askıda · <?= $date($u['suspended_at'], 'd.m.Y') ?></span><?php endif; ?>
                    <?php if (empty($u['email_verified_at'])): ?><span class="badge bg-yellow-lt">E-posta doğrulanmadı</span><?php endif; ?>
                    <?php if (!empty($u['google_id'])): ?><span class="badge bg-azure-lt"><i class="ti ti-brand-google"></i> Google</span><?php endif; ?>
                </div>
            </div>
            <div class="list-group list-group-flush small">
                <?php foreach ([
                    ['Ana dil → hedef', strtoupper((string)$u['native_lang']) . ' → ' . strtoupper((string)$u['target_lang']) . ' (' . __('languages.' . ($u['target_lang'] ?: 'en')) . ')'],
                    ['Seviye', $u['cefr_level'] ?: '—'],
                    ['Hedef / ilgi', ($u['learning_goal'] ?: '—') . ' · ' . ($u['interest_area'] ?: '—')],
                    ['Onboarding', (int)$u['onboarding_completed'] ? 'Tamamlandı' : 'Tamamlanmadı'],
                    ['XP · seri', $n($u['xp']) . ' XP · ' . (int)$u['streak_count'] . ' gün'],
                    ['Son aktif', $date($u['last_activity_date'], 'd.m.Y')],
                    ['Kayıt', $date($u['created_at'])],
                ] as [$label, $value]): ?>
                <div class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-secondary"><?= $label ?></span><span class="text-end"><?= $e($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right -->
    <div class="col-lg-8">
        <div class="row row-cards mb-3">
            <?php foreach ([
                ['Mesaj', $n($c['messages'] ?? 0), 'message-circle', 'azure'],
                ['Sohbet', $n($c['conversations'] ?? 0), 'messages', 'primary'],
                ['Kelime', $n($c['words'] ?? 0) . ' <span class="text-secondary small">(' . $n($c['mastered'] ?? 0) . ' ezber)</span>', 'cards', 'green'],
                ['Hata', $n($c['mistakes_open'] ?? 0) . ' <span class="text-secondary small">açık · ' . $n($c['mistakes_learned'] ?? 0) . ' öğrenildi</span>', 'edit', 'red'],
            ] as [$label, $value, $icon, $color]): ?>
            <div class="col-6 col-md-3">
                <div class="card card-sm"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1"><span class="avatar avatar-xs bg-<?= $color ?>-lt"><i class="ti ti-<?= $icon ?>"></i></span><span class="text-secondary small"><?= $label ?></span></div>
                    <div class="h3 m-0"><?= $value ?></div>
                </div></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row row-cards mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-credit-card me-1"></i>Abonelik</h3></div>
                    <div class="list-group list-group-flush small">
                        <?php foreach ([
                            ['Sağlayıcı', $provider],
                            ['Abonelik ID', $subId ?: '—'],
                            ['Dönem', ($u['billing_interval'] ?? 'month') === 'year' ? 'Yıllık' : 'Aylık'],
                            ['Sonraki ödeme', $date($u['next_billed_at'] ?? null, 'd.m.Y')],
                            ['Bekleyen değişiklik', $u['pending_plan_change'] ? ($planLabels[$u['pending_plan_change']] ?? $u['pending_plan_change']) : '—'],
                            ['İptal talebi', $u['cancel_requested_at'] ? $date($u['cancel_requested_at']) . ' (' . ($u['cancel_method'] === 'manual' ? 'elle yapılmalı' : 'API') . ')' : '—'],
                            ['İade talebi', $date($u['refund_requested_at'] ?? null)],
                        ] as [$label, $value]): ?>
                        <div class="list-group-item d-flex justify-content-between gap-3"><span class="text-secondary"><?= $label ?></span><span class="text-end text-break"><?= $e($value) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-bolt me-1"></i>Bu ayki kullanım</h3></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= $n($details['quota']['used']) ?> / <?= $n($quotaTotal) ?> mesaj</span>
                            <span class="text-secondary"><?= $n($details['quota']['remaining']) ?> kaldı</span>
                        </div>
                        <div class="progress mb-2"><div class="progress-bar <?= $quotaPct >= 90 ? 'bg-red' : '' ?>" style="width: <?= round($quotaPct, 1) ?>%"></div></div>
                        <div class="text-secondary small mb-3">Plan limiti <?= $n($quotaLimit) ?><?= $details['quota']['bonus'] ? ' + ' . $n($details['quota']['bonus']) . ' bonus' : '' ?></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary">AI maliyeti (bu ay)</span><span>$<?= $n($details['ai']['month_cost'] ?? 0, 4) ?></span></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary">AI maliyeti (toplam)</span><span>$<?= $n($details['ai']['total_cost'] ?? 0, 4) ?></span></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary">AI isteği / hatalı</span><span><?= $n($details['ai']['requests'] ?? 0) ?> / <?= $n($details['ai']['failed'] ?? 0) ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-tool me-1"></i>İşlemler</h3><span class="card-subtitle ms-2">her işlem kayıt altına alınır</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Planı değiştir</label>
                        <?php if ($isReal): ?>
                            <div class="text-secondary small">Gerçek Dodo aboneliği var; plan kullanıcının kendi hesabından veya Dodo panelinden değişmeli.</div>
                        <?php else: ?>
                            <?= $form('set_plan', '<select name="value" class="form-select w-auto">' . implode('', array_map(fn($p) => '<option value="' . $p . '"' . ($p === $u['plan_status'] ? ' selected' : '') . '>' . $planLabels[$p] . '</option>', array_keys($planLabels))) . '</select><button class="btn btn-primary">Kaydet</button>', 'Plan değiştirilsin mi? Ücretli plan elle verilirse gelire sayılmaz.') ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bonus mesaj ver <span class="text-secondary small">(ay sonunda sıfırlanır)</span></label>
                        <?= $form('add_bonus', '<input type="number" name="value" min="1" max="10000" value="50" class="form-control w-auto" style="max-width:110px"><button class="btn btn-primary">Ekle</button>') ?>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <?= $form('reset_usage', '<button class="btn btn-outline-secondary"><i class="ti ti-refresh me-1"></i>Bu ayın kullanımını sıfırla</button>', 'Bu ayın mesaj kullanımı sıfırlansın mı?') ?>
                        <?php if (empty($u['email_verified_at'])): ?>
                            <?= $form('verify_email', '<button class="btn btn-outline-secondary"><i class="ti ti-mail-check me-1"></i>E-postayı doğrulandı yap</button>') ?>
                        <?php endif; ?>
                        <?= $form('send_reset', '<button class="btn btn-outline-secondary"><i class="ti ti-key me-1"></i>Şifre sıfırlama e-postası gönder</button>', $u['email'] . ' adresine şifre sıfırlama bağlantısı gönderilsin mi?') ?>
                        <?php if (empty($u['suspended_at'])): ?>
                            <?= $form('suspend', '<input type="hidden" name="value" value=""><button class="btn btn-outline-warning"><i class="ti ti-ban me-1"></i>Askıya al</button>', 'Hesap askıya alınsın mı? Kullanıcı giriş yapamaz ve açık oturumu kapanır.') ?>
                        <?php else: ?>
                            <?= $form('unsuspend', '<button class="btn btn-outline-success"><i class="ti ti-lock-open me-1"></i>Askıyı kaldır</button>') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="background:rgba(248,113,113,.06)">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-fill">
                        <div class="fw-semibold text-red"><i class="ti ti-alert-triangle me-1"></i>Kullanıcıyı sil</div>
                        <div class="text-secondary small">Tüm sohbetler, kelimeler ve hatalar kalıcı olarak silinir<?= $isReal ? '; gerçek Dodo aboneliği iptal edilir' : '' ?>. Geri alınamaz.</div>
                    </div>
                    <?= $form('delete', '<input type="text" name="value" class="form-control" style="max-width:240px" placeholder="Onay için e-postayı yaz" autocomplete="off"><button class="btn btn-danger">Sil</button>', 'Bu kullanıcı ve tüm verileri kalıcı olarak silinecek. Emin misin?') ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">Salt okunur admin hesabısın; işlemler gizlendi.</div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-messages me-1"></i>Son sohbetler</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Konu</th><th>İlk mesaj</th><th class="text-end">Mesaj</th><th>Son</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($details['conversations'] as $cv): ?>
                        <tr>
                            <td><?= $e($cv['topic_id'] ?: 'serbest') ?></td>
                            <td class="text-secondary text-truncate" style="max-width:260px;"><?= $e(mb_substr((string)$cv['first_message'], 0, 80)) ?></td>
                            <td class="text-end"><?= (int)$cv['user_messages'] ?></td>
                            <td class="text-secondary text-nowrap"><?= $date($cv['updated_at']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-ghost-primary" href="?page=admin-conversation&amp;conv_id=<?= (int)$cv['id'] ?>" title="Görüntüleme kayıt altına alınır">Aç</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$details['conversations']): ?><tr><td colspan="5" class="text-center text-secondary py-4">Henüz sohbet yok.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-timeline me-1"></i>Geçmiş</h3><span class="card-subtitle ms-2">abonelik olayları ve admin işlemleri</span><a href="?page=admin-audit&amp;user=<?= $uid ?>" class="ms-auto small">Tüm admin işlemleri</a></div>
            <div class="list-group list-group-flush">
                <?php foreach ($details['timeline'] as $t): ?>
                <div class="list-group-item">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="avatar avatar-xs <?= $t['kind'] === 'admin' ? 'bg-purple-lt' : 'bg-blue-lt' ?>"><i class="ti ti-<?= $t['kind'] === 'admin' ? 'shield' : 'receipt' ?>"></i></span>
                        <div class="flex-fill">
                            <div><?= $e($actionLabels[$t['action']] ?? $t['action']) ?><?= $t['detail'] ? ' <span class="text-secondary small">· ' . $e($t['detail']) . '</span>' : '' ?></div>
                            <div class="text-secondary small"><?= $date($t['at']) ?><?= $t['admin_email'] ? ' · ' . $e($t['admin_email']) : '' ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$details['timeline']): ?><div class="list-group-item text-secondary">Kayıtlı olay yok.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
