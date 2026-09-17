<?php
$title = __('admin.activity');

$eventBadges = [
    'subscription_started'    => ['label' => 'New subscription',   'bg' => '#1f6f4a', 'fg' => '#eafff2'],
    'subscription_upgraded'   => ['label' => 'Upgraded',           'bg' => '#1f5f8f', 'fg' => '#eaf5ff'],
    'subscription_downgraded' => ['label' => 'Downgraded',         'bg' => '#8f6a1f', 'fg' => '#fff6e6'],
    'downgrade_scheduled'     => ['label' => 'Downgrade scheduled','bg' => '#8f6a1f', 'fg' => '#fff6e6'],
    'cancellation_requested'  => ['label' => 'Cancellation requested', 'bg' => '#8f3a1f', 'fg' => '#fff1ea'],
    'subscription_canceled'   => ['label' => 'Canceled',           'bg' => '#8f1f2a', 'fg' => '#ffecee'],
    'cancellation_resumed'    => ['label' => 'Resumed',            'bg' => '#2a6f6a', 'fg' => '#eafffd'],
    'refund_requested'        => ['label' => 'Refund requested',   'bg' => '#8f5a1f', 'fg' => '#fff3e6'],
];
$providerLabels = ['dodo' => 'Dodo', 'fastspring' => 'FastSpring', 'paddle' => 'Paddle'];

ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h2 style="margin:0;"><?= __('admin.activity') ?></h2>
    <span style="color:#9aa0a6;font-size:13px;">
        <?= (int)$totalCount ?> event<?= $totalCount === 1 ? '' : 's' ?> total
    </span>
</div>

<table class="table table-hover table-striped">
    <thead>
        <tr>
            <th>Time</th>
            <th>User</th>
            <th>Event</th>
            <th>Provider</th>
            <th>Plan</th>
            <th>Detail</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($events)): ?>
        <tr><td colspan="6" style="text-align:center;color:#9aa0a6;padding:24px;">No activity recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($events as $ev):
            $badge = $eventBadges[$ev['event_type']] ?? ['label' => $ev['event_type'], 'bg' => '#444', 'fg' => '#eee'];
            $providerLabel = $providerLabels[$ev['provider'] ?? ''] ?? ($ev['provider'] ?: '—');
        ?>
        <tr>
            <td style="white-space:nowrap;font-variant-numeric:tabular-nums;"><?= htmlspecialchars($ev['created_at']) ?></td>
            <td><?= htmlspecialchars($ev['user_email'] ?? '—') ?></td>
            <td>
                <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;white-space:nowrap;background:<?= $badge['bg'] ?>;color:<?= $badge['fg'] ?>;">
                    <?= htmlspecialchars($badge['label']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($providerLabel) ?></td>
            <td><?= htmlspecialchars(trim(($ev['plan'] ?? '') . ($ev['billing_interval'] ? '/' . $ev['billing_interval'] : '')) ?: '—') ?></td>
            <td style="color:#9aa0a6;font-size:13px;"><?= htmlspecialchars($ev['detail']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:16px;">
    <?php if ($pageNum > 1): ?>
        <a href="?page=admin-activity&p=<?= $pageNum - 1 ?>" class="btn btn-sm btn-outline-secondary">&larr; Newer</a>
    <?php endif; ?>
    <span style="align-self:center;color:#9aa0a6;font-size:13px;">Page <?= $pageNum ?> / <?= $totalPages ?></span>
    <?php if ($pageNum < $totalPages): ?>
        <a href="?page=admin-activity&p=<?= $pageNum + 1 ?>" class="btn btn-sm btn-outline-secondary">Older &rarr;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
