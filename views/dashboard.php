<?php
require_once __DIR__ . '/partials/flags.php';
$pageTitle = __('dash.page_title');
$user = $auth->currentUser();
$xp = (int)($user['xp'] ?? 0);
$level = max(1, (int)floor($xp / 100) + 1);
$xpInLevel = $xp % 100;
$cefrColors = [
  'A1' => 'bg-on-secondary-fixed/20 text-secondary border-secondary-fixed-dim/30',
  'A2' => 'bg-tertiary/20 text-tertiary border-tertiary/30',
  'B1' => 'bg-primary/20 text-primary border-primary/30',
  'B2' => 'bg-primary/20 text-primary border-primary/30',
  'C1' => 'bg-tertiary/20 text-tertiary border-tertiary/30',
  'C2' => 'bg-secondary/20 text-secondary border-secondary/30',
];
$cefrColor = $cefrColors[$user['cefr_level'] ?? 'A1'] ?? $cefrColors['A1'];
$totalConvs = $db->fetchOne('SELECT COUNT(*) as c FROM conversations WHERE user_id=?', [$auth->userId()])['c'] ?? 0;
$totalMsgs  = $db->fetchOne('SELECT COUNT(*) as c FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE user_id=?)', [$auth->userId()])['c'] ?? 0;
$vocabCount = $db->fetchOne('SELECT COUNT(*) as c FROM vocabulary_words WHERE user_id=?', [$auth->userId()])['c'] ?? 0;
$recentConvs = $db->fetchAll('SELECT id, updated_at, (SELECT content FROM messages WHERE conversation_id=conversations.id ORDER BY created_at ASC LIMIT 1) as title FROM conversations WHERE user_id=? ORDER BY updated_at DESC LIMIT 5', [$auth->userId()]);
$tips = [
  __('dash.tip_1'),
  __('dash.tip_2'),
  __('dash.tip_3'),
  __('dash.tip_4'),
  __('dash.tip_5'),
];
$tip = $tips[date('N') % count($tips)];
$targetFlag = flagImg($user['target_lang'] ?? 'en', 'w-5 h-3.5');
$userInitial = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));
?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="flex h-[calc(100vh-56px)] overflow-hidden">

  <!-- Sidebar -->
  <aside class="w-64 border-r border-outline-variant/10 flex flex-col p-4 gap-4 overflow-y-auto shrink-0 bg-surface-container-low/30">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container border border-primary/20 shrink-0">
        <span class="material-symbols-outlined">person</span>
      </div>
      <div class="min-w-0">
        <div class="text-body-md font-medium text-on-surface truncate"><?= htmlspecialchars($user['name'] ?? '') ?></div>
        <div class="text-label-md text-outline truncate"><?= htmlspecialchars($user['email']) ?></div>
      </div>
    </div>

    <span class="self-start text-label-md font-semibold px-3 py-1 rounded-full border <?= $cefrColor ?>">
      <?= htmlspecialchars($user['cefr_level'] ?? 'A1') ?> Level
    </span>

    <div class="bg-surface-container border border-outline-variant/20 rounded-xl p-4">
      <div class="flex justify-between text-label-md text-outline mb-1">
        <span><?= sprintf(__('dash.level_xp'), $level) ?></span>
        <span><?= sprintf(__('dash.xp_progress'), $xpInLevel) ?></span>
      </div>
      <div class="w-full bg-surface-container-highest rounded-full h-2">
        <div class="bg-primary h-2 rounded-full transition-all" style="width:<?= $xpInLevel ?>%"></div>
      </div>
      <div class="text-center text-label-md text-outline mt-1"><?= sprintf(__('dash.total_xp'), $xp) ?></div>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div class="bg-surface-container border border-outline-variant/20 rounded-xl p-3 text-center">
        <div class="text-headline-sm font-bold text-on-surface"><?= $totalConvs ?></div>
        <div class="text-label-md text-outline"><?= __('dash.chats_count') ?></div>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-xl p-3 text-center">
        <div class="text-headline-sm font-bold text-on-surface"><?= $vocabCount ?></div>
        <div class="text-label-md text-outline"><?= __('dash.words_count') ?></div>
      </div>
    </div>

    <div class="space-y-1 mt-2">
      <a href="?page=chat" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-body-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition">
        <span class="material-symbols-outlined text-[18px]">forum</span>
        <?= __('dash.go_to_chat') ?>
      </a>
      <a href="?page=blog" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-body-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition">
        <span class="material-symbols-outlined text-[18px]">article</span>
        <?= __('dash.blog') ?>
      </a>
      <a href="?page=flashcards" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-body-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition">
        <span class="material-symbols-outlined text-[18px]">style</span>
        <?= __('dash.flashcards') ?>
      </a>
    </div>

    <div class="mt-auto">
      <div class="flex items-center gap-2 bg-surface-container border border-outline-variant/20 rounded-xl px-4 py-2.5 text-body-md text-on-surface-variant">
        <?= $targetFlag ?>
        <?= __('languages.' . strtolower($user['target_lang'] ?? 'en')) ?>
      </div>
    </div>
  </aside>

  <!-- Main content -->
  <main class="flex-1 overflow-y-auto p-6">
    <div class="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 class="font-headline-md text-headline-md text-on-surface mb-1">
          <?= sprintf(__('dash.greeting'), htmlspecialchars($user['name'] ?? explode('@', $user['email'])[0])) ?>
        </h1>
        <p class="text-body-md text-on-surface-variant"><?= __('dash.keep_momentum') ?></p>
      </div>

      <div class="bg-primary/10 border border-primary/20 rounded-2xl p-5">
        <div class="text-label-md text-primary font-semibold uppercase tracking-wide mb-2"><?= __('dash.tip_title') ?></div>
        <p class="text-body-md text-on-surface-variant"><?= htmlspecialchars($tip) ?></p>
      </div>

      <a href="?page=chat" class="flex items-center justify-between bg-surface-container border border-outline-variant/20 hover:border-primary/50 rounded-2xl p-5 transition group">
        <div>
          <div class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= __('dash.new_conversation') ?></div>
          <div class="text-body-md text-on-surface-variant"><?= sprintf(__('dash.new_conv_desc'), htmlspecialchars($user['target_lang'] ?? 'en')) ?></div>
        </div>
        <span class="material-symbols-outlined text-primary text-2xl group-hover:translate-x-1 transition-transform">arrow_forward</span>
      </a>

      <?php if ($recentConvs): ?>
      <div>
        <h2 class="text-label-md font-semibold text-outline uppercase tracking-wide mb-3"><?= __('dash.recent_convs') ?></h2>
        <div class="space-y-2">
          <?php foreach ($recentConvs as $conv): ?>
            <a href="?page=chat&conv_id=<?= $conv['id'] ?>"
               class="flex items-center gap-3 bg-surface-container border border-outline-variant/20 hover:border-outline-variant rounded-xl px-4 py-3 transition">
              <span class="material-symbols-outlined text-on-surface-variant">forum</span>
              <div class="min-w-0 flex-1">
                <div class="text-body-md text-on-surface truncate"><?= htmlspecialchars($conv['title'] ?? 'Conversation #' . $conv['id']) ?></div>
                <div class="text-label-md text-outline"><?= date('M j, H:i', strtotime($conv['updated_at'])) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

</body>
</html>
