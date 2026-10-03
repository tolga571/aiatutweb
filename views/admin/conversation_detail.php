<?php
$e = fn($v) => htmlspecialchars((string)$v);
$title = t('admin.conv_title', ['id' => (int)$conv['id']]);
$pageHeader = $title;
$pagePretitle = t('admin.conv_pretitle', ['topic' => $conv['topic_id'] ?: t('admin.topic_free_chat'), 'n' => count($messages)]);
$pageActions = '<a href="?page=admin-conversations" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>' . $e(t('admin.conversations')) . '</a>';

ob_start();
?>
<style>
    .chat-log { display: flex; flex-direction: column; gap: .9rem; }
    .chat-msg { max-width: min(46rem, 88%); min-width: 0; }
    .chat-msg.user { align-self: flex-end; }
    .chat-bubble { border-radius: 14px; padding: .75rem 1rem; overflow-wrap: anywhere; line-height: 1.5; }
    .chat-text { white-space: pre-wrap; }
    .chat-msg.user .chat-bubble { background: rgba(109, 139, 255, .18); border: 1px solid rgba(109, 139, 255, .3); border-bottom-right-radius: 4px; }
    .chat-msg.ai .chat-bubble { background: var(--tblr-bg-surface); border: 1px solid var(--tblr-border-color); border-bottom-left-radius: 4px; }
    .chat-meta { font-size: .72rem; color: #64748b; margin: .2rem .25rem 0; }
    .chat-msg.user .chat-meta { text-align: right; }
    .chat-extra { font-size: .82rem; margin-top: .45rem; padding-top: .45rem; border-top: 1px dashed var(--tblr-border-color); overflow-wrap: anywhere; }
</style>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="avatar bg-primary-lt"><?= $e(mb_strtoupper(mb_substr($conv['user_name'] ?: ($conv['user_email'] ?? '?'), 0, 1))) ?></span>
        <div class="flex-fill" style="min-width:0;">
            <?php if ($conv['user_email']): ?>
                <a href="?page=admin-user&amp;id=<?= (int)$conv['user_id'] ?>" class="fw-semibold d-block text-truncate"><?= $e($conv['user_email']) ?></a>
            <?php else: ?><span class="text-secondary"><?= $e(t('admin.deleted_user')) ?></span><?php endif; ?>
            <div class="text-secondary small">
                <?= $e(strtoupper((string)$conv['native_lang'])) ?> → <?= $e(strtoupper((string)$conv['target_lang'])) ?>
                · <?= $e(t('admin.conv_started_last', ['start' => date('d.m.Y H:i', strtotime($conv['created_at'])), 'last' => date('d.m.Y H:i', strtotime($conv['updated_at']))])) ?>
            </div>
        </div>
        <span class="badge bg-yellow-lt" title="<?= $e(t('admin.view_logged_help')) ?>"><i class="ti ti-eye"></i> <?= $e(t('admin.view_logged')) ?></span>
    </div>
</div>

<?php if (!$messages): ?>
    <div class="alert alert-info"><?= __('admin.no_messages') ?></div>
<?php else: ?>
<div class="chat-log">
    <?php foreach ($messages as $m):
        $isUser = $m['role'] === 'user'; ?>
    <div class="chat-msg <?= $isUser ? 'user' : 'ai' ?>">
        <div class="chat-bubble">
            <div class="chat-text"><?= $e($m['content']) ?></div>
            <?php if (!$isUser && ($m['translation'] || $m['correction'])): ?>
            <div class="chat-extra">
                <?php if ($m['translation']): ?><div><span class="text-secondary"><?= $e(t('admin.translation')) ?>:</span> <?= $e($m['translation']) ?></div><?php endif; ?>
                <?php if ($m['correction']): ?><div class="text-orange"><span class="text-secondary"><?= $e(t('admin.correction')) ?>:</span> <?= $e($m['correction']) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="chat-meta"><?= $e($isUser ? t('admin.user_col') : t('admin.ai_tutor')) ?> · <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
