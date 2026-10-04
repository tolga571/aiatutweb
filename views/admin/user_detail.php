<?php
$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v, int $dec = 0) => number_format((float)$v, $dec, ',', '.');
$date = fn($v, string $fmt = 'd.m.Y H:i') => $v ? date($fmt, strtotime((string)$v)) : '—';
$planLabels = ['inactive' => t('admin.plan_inactive'), 'trial' => t('admin.plan_trial'), 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium'];
$planColors = ['inactive' => 'secondary', 'trial' => 'yellow', 'starter' => 'blue', 'pro' => 'purple', 'active' => 'orange'];
// Timeline label: subscription events and admin actions share the labels
// of the event feed (admin.event_*) and the audit log (admin.audit_*).
$actionLabel = function (string $a): string {
    foreach (['admin.event_', 'admin.audit_'] as $prefix) {
        $s = t($prefix . $a);
        if ($s !== $prefix . $a) {
            return $s;
        }
    }
    return $a;
};

$u = $user;
$uid = (int)$u['id'];
$isReal = (int)$u['has_paid'] === 1 && !empty($u['dodo_subscription_id']);
$isTest = (int)$u['has_paid'] === 1 && empty($u['dodo_subscription_id']);
$provider = !empty($u['dodo_subscription_id']) ? 'Dodo' : (!empty($u['fastspring_subscription_id']) ? 'FastSpring' : (!empty($u['paddle_subscription_id']) ? 'Paddle (sandbox)' : '—'));
$subId = $u['dodo_subscription_id'] ?: ($u['fastspring_subscription_id'] ?: ($u['paddle_subscription_id'] ?: ''));
$quotaTotal = $quotaLimit + $details['quota']['bonus'];
$quotaPct = $quotaTotal > 0 ? min(100, $details['quota']['used'] / $quotaTotal * 100) : 0;
$c = $details['counts'];

$title = ($u['name'] ?: $u['email']) . ' · ' . t('admin.user_col');
$pageHeader = $u['name'] ?: $u['email'];
$pagePretitle = t('admin.user_col') . ' #' . $uid;
$pageActions = '<a href="?page=admin-users" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>' . $e(t('admin.users')) . '</a>';

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
                    <?php if ($isReal): ?><span class="badge bg-green-lt"><?= $e(t('admin.real_subscriber_dodo')) ?></span><?php endif; ?>
                    <?php if ($isTest): ?><span class="badge bg-secondary text-white">TEST</span><?php endif; ?>
                    <?php if (!empty($u['suspended_at'])): ?><span class="badge bg-red-lt"><?= $e(t('admin.suspended')) ?> · <?= $date($u['suspended_at'], 'd.m.Y') ?></span><?php endif; ?>
                    <?php if (empty($u['email_verified_at'])): ?><span class="badge bg-yellow-lt"><?= $e(t('admin.email_unverified')) ?></span><?php endif; ?>
                    <?php if (!empty($u['google_id'])): ?><span class="badge bg-azure-lt"><i class="ti ti-brand-google"></i> Google</span><?php endif; ?>
                </div>
            </div>
            <div class="list-group list-group-flush small">
                <?php foreach ([
                    [$e(t('admin.native_to_target')), strtoupper((string)$u['native_lang']) . ' → ' . strtoupper((string)$u['target_lang']) . ' (' . \App\Src\Language::displayName($u['target_lang'] ?: 'en') . ')'],
                    [$e(t('admin.level')), $u['cefr_level'] ?: '—'],
                    [$e(t('admin.goal_interest')), ($u['learning_goal'] ?: '—') . ' · ' . ($u['interest_area'] ?: '—')],
                    ['Onboarding', (int)$u['onboarding_completed'] ? t('admin.completed') : t('admin.not_completed')],
                    [$e(t('admin.xp_streak')), $n($u['xp']) . ' XP · ' . t('admin.n_days', ['n' => (int)$u['streak_count']])],
                    [$e(t('admin.last_active')), $date($u['last_activity_date'], 'd.m.Y')],
                    [$e(t('admin.col_signup')), $date($u['created_at'])],
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
                [$e(t('admin.col_messages')), $n($c['messages'] ?? 0), 'message-circle', 'azure'],
                [$e(t('admin.conversations')), $n($c['conversations'] ?? 0), 'messages', 'primary'],
                [$e(t('admin.words')), $n($c['words'] ?? 0) . ' <span class="text-secondary small">(' . $e(t('admin.n_mastered', ['n' => $n($c['mastered'] ?? 0)])) . ')</span>', 'cards', 'green'],
                [$e(t('admin.mistakes')), $n($c['mistakes_open'] ?? 0) . ' <span class="text-secondary small">' . $e(t('admin.open_learned', ['n' => $n($c['mistakes_learned'] ?? 0)])) . '</span>', 'edit', 'red'],
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
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-credit-card me-1"></i><?= $e(t('admin.subscription')) ?></h3></div>
                    <div class="list-group list-group-flush small">
                        <?php foreach ([
                            [$e(t('admin.col_provider')), $provider],
                            [$e(t('admin.subscription_id')), $subId ?: '—'],
                            [$e(t('admin.period')), ($u['billing_interval'] ?? 'month') === 'year' ? t('admin.yearly_cap') : t('admin.monthly_cap')],
                            [$e(t('admin.next_payment')), $date($u['next_billed_at'] ?? null, 'd.m.Y')],
                            [$e(t('admin.pending_change')), $u['pending_plan_change'] ? ($planLabels[$u['pending_plan_change']] ?? $u['pending_plan_change']) : '—'],
                            [$e(t('admin.event_cancellation_requested')), $u['cancel_requested_at'] ? $date($u['cancel_requested_at']) . ' (' . ($u['cancel_method'] === 'manual' ? t('admin.must_do_manually') : 'API') . ')' : '—'],
                            [$e(t('admin.event_refund_requested')), $date($u['refund_requested_at'] ?? null)],
                        ] as [$label, $value]): ?>
                        <div class="list-group-item d-flex justify-content-between gap-3"><span class="text-secondary"><?= $label ?></span><span class="text-end text-break"><?= $e($value) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-bolt me-1"></i><?= $e(t('admin.usage_this_month')) ?></h3></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= $e(t('admin.quota_used', ['used' => $n($details['quota']['used']), 'total' => $n($quotaTotal)])) ?></span>
                            <span class="text-secondary"><?= $e(t('admin.n_left', ['n' => $n($details['quota']['remaining'])])) ?></span>
                        </div>
                        <div class="progress mb-2"><div class="progress-bar <?= $quotaPct >= 90 ? 'bg-red' : '' ?>" style="width: <?= round($quotaPct, 1) ?>%"></div></div>
                        <div class="text-secondary small mb-3"><?= $e(t('admin.plan_limit', ['n' => $n($quotaLimit)])) ?><?= $details['quota']['bonus'] ? ' + ' . $n($details['quota']['bonus']) . ' bonus' : '' ?></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary"><?= $e(t('admin.ai_cost_month')) ?></span><span>$<?= $n($details['ai']['month_cost'] ?? 0, 4) ?></span></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary"><?= $e(t('admin.ai_cost_total')) ?></span><span>$<?= $n($details['ai']['total_cost'] ?? 0, 4) ?></span></div>
                        <div class="d-flex justify-content-between small"><span class="text-secondary"><?= $e(t('admin.ai_requests_failed')) ?></span><span><?= $n($details['ai']['requests'] ?? 0) ?> / <?= $n($details['ai']['failed'] ?? 0) ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-tool me-1"></i><?= $e(t('admin.actions')) ?></h3><span class="card-subtitle ms-2"><?= $e(t('admin.every_action_logged')) ?></span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?= $e(t('admin.change_plan')) ?></label>
                        <?php if ($isReal): ?>
                            <div class="text-secondary small"><?= $e(t('admin.has_real_sub')) ?></div>
                        <?php else: ?>
                            <?= $form('set_plan', '<select name="value" class="form-select w-auto">' . implode('', array_map(fn($p) => '<option value="' . $p . '"' . ($p === $u['plan_status'] ? ' selected' : '') . '>' . $e($planLabels[$p]) . '</option>', array_keys($planLabels))) . '</select><button class="btn btn-primary">' . $e(t('admin.lang_save')) . '</button>', t('admin.change_plan_confirm')) ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= $e(t('admin.give_bonus')) ?> <span class="text-secondary small"><?= $e(t('admin.resets_month_end')) ?></span></label>
                        <?= $form('add_bonus', '<input type="number" name="value" min="1" max="10000" value="50" class="form-control w-auto" style="max-width:110px"><button class="btn btn-primary">' . $e(t('admin.add')) . '</button>') ?>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <?= $form('reset_usage', '<button class="btn btn-outline-secondary"><i class="ti ti-refresh me-1"></i>' . $e(t('admin.reset_usage_btn')) . '</button>', t('admin.reset_usage_confirm')) ?>
                        <?php if (empty($u['email_verified_at'])): ?>
                            <?= $form('verify_email', '<button class="btn btn-outline-secondary"><i class="ti ti-mail-check me-1"></i>' . $e(t('admin.mark_verified')) . '</button>') ?>
                        <?php endif; ?>
                        <?= $form('send_reset', '<button class="btn btn-outline-secondary"><i class="ti ti-key me-1"></i>' . $e(t('admin.send_reset_btn')) . '</button>', t('admin.send_reset_confirm', ['email' => $u['email']])) ?>
                        <?php if (empty($u['suspended_at'])): ?>
                            <?= $form('suspend', '<input type="hidden" name="value" value=""><button class="btn btn-outline-warning"><i class="ti ti-ban me-1"></i>' . $e(t('admin.suspend_btn')) . '</button>', t('admin.suspend_confirm')) ?>
                        <?php else: ?>
                            <?= $form('unsuspend', '<button class="btn btn-outline-success"><i class="ti ti-lock-open me-1"></i>' . $e(t('admin.unsuspend_btn')) . '</button>') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="background:rgba(248,113,113,.06)">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-fill">
                        <div class="fw-semibold text-red"><i class="ti ti-alert-triangle me-1"></i><?= $e(t('admin.delete_user')) ?></div>
                        <div class="text-secondary small"><?= $e($isReal ? t('admin.delete_user_help_real') : t('admin.delete_user_help')) ?></div>
                    </div>
                    <?= $form('delete', '<input type="text" name="value" class="form-control" style="max-width:240px" placeholder="' . $e(t('admin.type_email_confirm')) . '" autocomplete="off"><button class="btn btn-danger">' . $e(t('admin.delete')) . '</button>', t('admin.delete_user_confirm')) ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info"><?= $e(t('admin.readonly_hidden')) ?></div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-messages me-1"></i><?= $e(t('admin.recent_convs')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th><?= $e(t('admin.topic_col')) ?></th><th><?= $e(t('admin.first_message')) ?></th><th class="text-end"><?= $e(t('admin.col_messages')) ?></th><th><?= $e(t('admin.col_last')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($details['conversations'] as $cv): ?>
                        <tr>
                            <td><?= $e($cv['topic_id'] ?: t('admin.topic_free')) ?></td>
                            <td class="text-secondary text-truncate" style="max-width:260px;"><?= $e(mb_substr((string)$cv['first_message'], 0, 80)) ?></td>
                            <td class="text-end"><?= (int)$cv['user_messages'] ?></td>
                            <td class="text-secondary text-nowrap"><?= $date($cv['updated_at']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-ghost-primary" href="?page=admin-conversation&amp;conv_id=<?= (int)$cv['id'] ?>" title="<?= $e(t('admin.view_is_logged')) ?>"><?= $e(t('admin.open')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$details['conversations']): ?><tr><td colspan="5" class="text-center text-secondary py-4"><?= $e(t('admin.no_convs_yet')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-timeline me-1"></i><?= $e(t('admin.history')) ?></h3><span class="card-subtitle ms-2"><?= $e(t('admin.history_sub')) ?></span><a href="?page=admin-audit&amp;user=<?= $uid ?>" class="ms-auto small"><?= $e(t('admin.all_admin_actions')) ?></a></div>
            <div class="list-group list-group-flush">
                <?php foreach ($details['timeline'] as $t): ?>
                <div class="list-group-item">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="avatar avatar-xs <?= $t['kind'] === 'admin' ? 'bg-purple-lt' : 'bg-blue-lt' ?>"><i class="ti ti-<?= $t['kind'] === 'admin' ? 'shield' : 'receipt' ?>"></i></span>
                        <div class="flex-fill">
                            <div><?= $e($actionLabel($t['action'])) ?><?= $t['detail'] ? ' <span class="text-secondary small">· ' . $e($t['detail']) . '</span>' : '' ?></div>
                            <div class="text-secondary small"><?= $date($t['at']) ?><?= $t['admin_email'] ? ' · ' . $e($t['admin_email']) : '' ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$details['timeline']): ?><div class="list-group-item text-secondary"><?= $e(t('admin.no_events')) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
