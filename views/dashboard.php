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
$streak = (int)($user['streak_count'] ?? 0);
$wordsToday = $db->fetchOne("SELECT COUNT(*) as c FROM vocabulary_words WHERE user_id=? AND date(created_at) = " . $db->dateNow(), [$auth->userId()])['c'] ?? 0;
$dueCount = $db->fetchOne("SELECT COUNT(*) as c FROM user_flashcards WHERE user_id=? AND next_review <= " . $db->now(), [$auth->userId()])['c'] ?? 0;
$masteredCount = $db->fetchOne("SELECT COUNT(*) as c FROM user_flashcards WHERE user_id=? AND status='mastered'", [$auth->userId()])['c'] ?? 0;
$tips = [
  __('dash.tip_1'),
  __('dash.tip_2'),
  __('dash.tip_3'),
  __('dash.tip_4'),
  __('dash.tip_5'),
];
$tip = $tips[date('z') % count($tips)];
$targetFlag = flagImg($user['target_lang'] ?? 'en', 'w-5 h-3.5');
$userInitial = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));

// Quota
$quotaRemaining = $quotaRemaining ?? 0;
$quotaTotal = $quotaTotal ?? 0;
$quotaPercent = $quotaTotal > 0 ? round(($quotaRemaining / $quotaTotal) * 100) : 0;
if ($quotaPercent > 75) {
  $quotaBarColor = 'bg-green-500';
  $quotaTextColor = 'text-green-400';
} elseif ($quotaPercent > 50) {
  $quotaBarColor = 'bg-yellow-500';
  $quotaTextColor = 'text-yellow-400';
} elseif ($quotaPercent > 25) {
  $quotaBarColor = 'bg-orange-500';
  $quotaTextColor = 'text-orange-400';
} else {
  $quotaBarColor = 'bg-red-500';
  $quotaTextColor = 'text-red-400';
}
$planLabels = [
  'trial'   => __('chat.plan_trial'),
  'starter' => __('chat.plan_starter'),
  'pro'     => __('chat.plan_pro'),
  'active'  => __('chat.plan_premium'),
];
$planLabel = $planLabels[$user['plan_status'] ?? 'inactive'] ?? __('chat.plan_free');
?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<div class="flex h-[calc(100vh-56px)] overflow-hidden relative">

  <!-- Sidebar -->
  <aside id="dash-sidebar" class="hidden absolute z-40 md:relative md:flex w-64 h-full border-r border-outline-variant/10 flex-col p-4 gap-4 overflow-y-auto shrink-0 bg-surface-container-low/95 backdrop-blur-xl md:bg-surface-container-low/30 md:backdrop-blur-none shadow-2xl md:shadow-none">
    <div class="flex items-center justify-between md:hidden mb-1">
      <span class="text-label-md text-outline font-semibold uppercase tracking-wide"><?= __('dash.preferences') ?></span>
      <button onclick="document.getElementById('dash-sidebar').classList.add('hidden')" class="p-1 rounded-full hover:bg-surface-variant/50">
        <span class="material-symbols-outlined text-[16px]">close</span>
      </button>
    </div>
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container border border-primary/20 shrink-0 font-bold text-sm">
        <?= htmlspecialchars($userInitial) ?>
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

    <!-- Quota Sidebar Widget -->
    <div class="bg-surface-container border border-outline-variant/20 rounded-xl p-4">
      <div class="flex justify-between items-center mb-1">
        <span class="text-label-md text-outline font-semibold flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px] <?= $quotaTextColor ?>">bolt</span>
          <?= __('chat.quota_title') ?>
        </span>
        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary border border-primary/20"><?= htmlspecialchars($planLabel) ?></span>
      </div>
      <div class="w-full bg-surface-container-highest rounded-full h-2 mb-1">
        <div class="<?= $quotaBarColor ?> h-2 rounded-full transition-all" style="width:<?= $quotaPercent ?>%"></div>
      </div>
      <div class="flex justify-between text-label-md text-outline mt-1">
        <span class="<?= $quotaTextColor ?> font-semibold"><?= $quotaRemaining ?> / <?= $quotaTotal ?></span>
        <span><?= __('chat.quota_renews') ?></span>
      </div>
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
      <div class="bg-surface-container border border-outline-variant/20 rounded-xl p-3 text-center">
        <div class="text-headline-sm font-bold text-on-surface flex items-center justify-center gap-1">
           <?= $streak ?> <span class="material-symbols-outlined text-orange-500 text-[20px]">local_fire_department</span>
        </div>
        <div class="text-label-md text-outline"><?= __('dash.day_streak') ?></div>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-xl p-3 text-center">
        <div class="text-headline-sm font-bold text-on-surface"><?= $wordsToday ?></div>
        <div class="text-label-md text-outline"><?= __('dash.words_today') ?></div>
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
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="font-headline-md text-headline-md text-on-surface mb-1">
            <?= sprintf(__('dash.greeting'), htmlspecialchars($user['name'] ?? explode('@', $user['email'])[0])) ?>
          </h1>
          <p class="text-body-md text-on-surface-variant"><?= __('dash.keep_momentum') ?></p>
        </div>
        <button onclick="document.getElementById('dash-sidebar').classList.toggle('hidden'); document.getElementById('dash-sidebar').classList.toggle('flex');"
          class="md:hidden shrink-0 text-on-surface-variant hover:text-on-surface transition-colors flex items-center justify-center p-2 rounded-full hover:bg-surface-container-high/50 border border-outline-variant/20"
          aria-label="<?= __('chat.your_progress') ?>">
          <span class="material-symbols-outlined text-[22px]">insights</span>
        </button>
      </div>

      <div class="bg-primary/10 border border-primary/20 rounded-2xl p-5">
        <div class="text-label-md text-primary font-semibold uppercase tracking-wide mb-2"><?= __('dash.tip_title') ?></div>
        <p class="text-body-md text-on-surface-variant"><?= htmlspecialchars($tip) ?></p>
      </div>

      <!-- Quota Overview Card -->
      <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-5 flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
          <h2 class="font-headline-sm text-headline-sm text-on-surface mb-1 flex items-center gap-2">
            <span class="material-symbols-outlined <?= $quotaTextColor ?>">bolt</span>
            <?= __('dash.quota_title') ?>
          </h2>
          <p class="text-body-md text-on-surface-variant">
            <?= sprintf(__('dash.quota_desc'), $quotaRemaining, $quotaTotal, htmlspecialchars($planLabel)) ?>
          </p>
        </div>
        <div class="shrink-0 flex items-center gap-4">
          <div class="relative w-16 h-16 flex items-center justify-center">
            <svg class="w-full h-full transform -rotate-90">
              <circle cx="32" cy="32" r="28" class="stroke-outline-variant/20 fill-transparent" stroke-width="6"></circle>
              <circle cx="32" cy="32" r="28" class="stroke-current <?= $quotaTextColor ?> fill-transparent" stroke-width="6"
                stroke-dasharray="175.9" stroke-dashoffset="<?= 175.9 * (1 - $quotaPercent / 100) ?>"></circle>
            </svg>
            <span class="absolute text-sm font-bold <?= $quotaTextColor ?>"><?= $quotaPercent ?>%</span>
          </div>
          <?php if (($user['plan_status'] ?? '') === 'trial' || ($user['plan_status'] ?? '') === 'inactive'): ?>
            <a href="?page=pricing" class="bg-primary text-on-primary font-bold text-sm px-4 py-2 rounded-xl hover:opacity-90 transition whitespace-nowrap glow-hover">
              <?= __('chat.upgrade_plan') ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <a href="?page=chat" class="flex items-center justify-between bg-surface-container border border-outline-variant/20 hover:border-primary/50 rounded-2xl p-5 transition group">
        <div>
          <div class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= __('dash.new_conversation') ?></div>
          <div class="text-body-md text-on-surface-variant"><?= sprintf(__('dash.new_conv_desc'), htmlspecialchars($user['target_lang'] ?? 'en')) ?></div>
        </div>
        <span class="material-symbols-outlined text-primary text-2xl group-hover:translate-x-1 transition-transform">arrow_forward</span>
      </a>

      <?php if ($dueCount > 0): ?>
      <a href="?page=flashcards" class="flex items-center justify-between bg-secondary/10 border border-secondary/30 hover:border-secondary/60 rounded-2xl p-5 transition group">
        <div>
          <div class="font-headline-sm text-headline-sm text-secondary mb-1"><?= __('dash.review_time') ?></div>
          <div class="text-body-md text-on-surface-variant"><?= sprintf(__('dash.review_desc'), $dueCount) ?></div>
        </div>
        <span class="material-symbols-outlined text-secondary text-2xl group-hover:scale-110 transition-transform">style</span>
      </a>
      <?php endif; ?>

      <?php if ($masteredCount >= 50 && ($user['cefr_level'] ?? 'A1') !== 'C2'): ?>
      <a href="?page=chat" class="block bg-tertiary/10 border border-tertiary/30 hover:border-tertiary/60 rounded-2xl p-5 transition group">
        <div class="font-headline-sm text-headline-sm text-tertiary mb-1"><?= __('dash.level_up_title') ?></div>
        <p class="text-body-md text-on-surface-variant mb-3"><?= sprintf(__('dash.level_up_body'), $masteredCount) ?></p>
        <span class="inline-flex items-center gap-2 bg-tertiary text-on-tertiary px-4 py-2 rounded-xl text-sm font-bold hover:opacity-90 transition group-hover:gap-3">
          <?= __('dash.level_up_btn') ?>
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </span>
      </a>
      <?php endif; ?>

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

      <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-5 mt-6">
        <h2 class="text-label-md font-semibold text-outline uppercase tracking-wide mb-4"><?= __('dash.preferences') ?></h2>
        <?php if (isset($_SESSION['pref_saved'])): unset($_SESSION['pref_saved']); ?>
          <div class="bg-green-500/20 text-green-400 px-4 py-2 rounded-lg mb-4 text-sm"><?= __('dash.pref_saved') ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['pref_error'])): $prefError = $_SESSION['pref_error']; unset($_SESSION['pref_error']); ?>
          <div class="bg-error-container/30 border border-error/30 text-error px-4 py-2 rounded-lg mb-4 text-sm"><?= htmlspecialchars($prefError) ?></div>
        <?php endif; ?>
        <form method="POST" action="?page=dashboard" class="space-y-4">
          <?= csrf_field() ?>
          <input type="hidden" name="update_preferences" value="1">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('dash.interface_language') ?></label>
              <select name="native_lang" class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:border-primary transition appearance-none">
                <?php foreach(['en'=>'English', 'de'=>'Deutsch', 'fr'=>'Français', 'es'=>'Español', 'tr'=>'Türkçe', 'zh'=>'中文', 'ja'=>'日本語', 'ar'=>'العربية'] as $code => $name): ?>
                  <option value="<?= $code ?>" <?= ($user['native_lang'] ?? 'en') === $code ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('dash.cefr_level') ?></label>
              <select name="cefr_level" class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:border-primary transition appearance-none">
                <?php foreach(['A1','A2','B1','B2','C1','C2'] as $lvl): ?>
                  <option value="<?= $lvl ?>" <?= ($user['cefr_level'] ?? 'A1') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" class="bg-surface-variant text-on-surface font-semibold py-2 px-4 rounded-lg transition hover:bg-surface-variant/80 text-sm">
            <?= __('dash.save_preferences') ?>
          </button>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
