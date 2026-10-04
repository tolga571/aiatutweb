<?php
require_once __DIR__ . '/partials/flags.php';
$pageTitle = __('fc.page_title');

$currentUser = $currentUser ?? $auth->currentUser();
$targetLang = strtolower($deckLang ?? ($currentUser['target_lang'] ?? 'en'));
$nativeLang = strtolower($currentUser['native_lang'] ?? 'en');

$targetLangName = \App\Src\Language::langName($targetLang);
$nativeLangName = \App\Src\Language::langName($nativeLang);
$targetFlag = flagImg($targetLang, 'w-6 h-4');
$nativeFlag = flagImg($nativeLang, 'w-5 h-4');
$rtlLangs = ['ar', 'he', 'fa', 'ur'];
$isRtlTarget = in_array($targetLang, $rtlLangs, true);

// ?view= picks a word list (all / favorites / learned / mine / chat) and
// ?level=A1..B2 one CEFR level — both server-side, so big decks never all
// land in the DOM at once. The starter deck is added in index.php.
$activeView = in_array($_GET['view'] ?? '', \App\Src\Flashcard::VIEWS, true) ? $_GET['view'] : 'all';
$activeLevel = in_array($_GET['level'] ?? '', \App\Src\Flashcard::PACK_LEVELS, true) ? $_GET['level'] : 'all';
$levelArg = $activeLevel === 'all' ? null : $activeLevel;
$cards = $flashcard->getAllCards($currentUser['id'], $targetLang, null, null, $levelArg ? 1000 : 400, $levelArg, $activeView);
$fcStats = $flashcard->getStats($currentUser['id'], $targetLang);
$viewTabs = [
    'all' => ['label' => __('fc.view_all'), 'icon' => 'style', 'n' => $fcStats['total']],
    'favorites' => ['label' => __('fc.view_favorites'), 'icon' => 'star', 'n' => $fcStats['favorites']],
    'learned' => ['label' => __('fc.view_learned'), 'icon' => 'task_alt', 'n' => $fcStats['learned']],
    'mine' => ['label' => __('fc.view_mine'), 'icon' => 'edit_note', 'n' => $fcStats['mine']],
    'chat' => ['label' => __('fc.tab_chat_words'), 'icon' => 'forum', 'n' => $fcStats['chat_words']],
];
$deckLangs = \App\Src\Language::listed('learn', $currentUser['target_lang'] ?? null);
if (!in_array($targetLang, $deckLangs, true)) {
    $deckLangs[] = $targetLang;
}
$fcUrl = function (array $q = []) use ($activeView, $activeLevel): string {
    $q += ['view' => $activeView, 'level' => $activeLevel];
    $q = array_filter($q, fn($v) => $v !== 'all' && $v !== null && $v !== '');
    return '?page=flashcards' . ($q ? '&amp;' . http_build_query($q, '', '&amp;') : '');
};
$packs = $flashcard->getPacks($currentUser['id'], $targetLang);
$levelCounts = [];
foreach ($db->fetchAll('SELECT level, COUNT(*) AS c FROM vocabulary_words WHERE user_id = ? AND language = ? GROUP BY level', [$currentUser['id'], $targetLang]) as $lr) {
    $levelCounts[$lr['level']] = (int)$lr['c'];
}
// HEX_* flags: card text (some of it AI-generated) is printed inside a <script> block.
$cardsJson = json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$firstCard = $cards[0] ?? null;
?>

<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<link rel="stylesheet" href="css/flashcard.css?v=7">


<main class="flex-1 flex flex-col relative h-[calc(100vh-56px)] bg-surface-dim overflow-hidden">
  <div id="fc-sidebar-backdrop" onclick="fcCloseSidebar()" class="hidden fixed inset-0 bg-black/40 z-30"></div>

  <div class="flex flex-1 overflow-hidden h-full w-full">

    <!-- Sidebar: Word List -->
    <aside id="fc-sidebar" class="hidden absolute z-40 lg:relative lg:flex w-72 h-full bg-surface-container-low/95 backdrop-blur-xl lg:bg-surface-container-low/30 lg:backdrop-blur-none flex-col border-r border-outline-variant/10 p-md gap-md overflow-y-auto chat-scrollbar shrink-0 shadow-2xl lg:shadow-none">
      <div class="flex flex-col gap-xs mb-xs">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-sm text-on-surface flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-primary">list</span>
            <?= __('fc.word_list') ?>
          </h3>
          <button onclick="fcCloseSidebar()" class="lg:hidden p-1 rounded-full hover:bg-surface-variant/50">
            <span class="material-symbols-outlined text-[16px]">close</span>
          </button>
        </div>
        <p class="text-[11px] text-on-surface-variant"><?= __('fc.select_word') ?></p>
      </div>

      <div class="relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
        <input type="text" id="word-search" class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl pl-9 pr-3 py-2.5 text-xs text-on-surface placeholder-outline focus:outline-none focus:border-primary/50 transition-colors" placeholder="<?= __('fc.search_words') ?>">
      </div>

      <?php
      $uniqueCats = array_values(array_unique(array_map(function($c) { return $c['category'] ?? 'General'; }, $cards)));
      $catLabels = [
        'Greeting' => __('fc.category_greeting'),
        'Food' => __('fc.category_food'),
        'House' => __('fc.category_house'),
        'Travel' => __('fc.category_travel'),
        'Emotion' => __('fc.category_emotion'),
        'Family' => __('fc.category_family'),
        'Education' => __('fc.category_education'),
        'General' => __('fc.category_general'),
        'Shopping' => __('fc.category_shopping'),
        'Body' => __('fc.category_body'),
        'Nature' => __('fc.category_nature'),
        'Work' => __('fc.category_work'),
        'Numbers' => __('fc.category_numbers'),
        'Colors' => __('fc.category_colors'),
        'Actions' => __('fc.category_actions'),
        'Describing' => __('fc.category_describing'),
        'Time' => __('fc.category_time'),
        'HSK' => __('fc.category_hsk'),
        'Custom' => __('fc.category_custom'),
        'chat' => __('fc.tab_chat_words'),
      ];
      ?>
      <nav class="fc-views" aria-label="<?= __('fc.word_list') ?>">
        <?php foreach ($viewTabs as $v => $vt): ?>
        <a href="<?= $fcUrl(['view' => $v]) ?>" class="fc-view <?= $activeView === $v ? 'is-active' : '' ?>"<?= $activeView === $v ? ' aria-current="page"' : '' ?>>
          <span class="material-symbols-outlined text-[18px]"><?= $vt['icon'] ?></span>
          <span class="flex-1 truncate"><?= htmlspecialchars($vt['label']) ?></span>
          <span class="fc-view-n"><?= (int)$vt['n'] ?></span>
        </a>
        <?php endforeach; ?>
      </nav>

      <?php if (count(array_filter($levelCounts)) > 1 || $activeLevel !== 'all'): ?>
      <div class="flex flex-wrap gap-xs" id="level-filters" aria-label="CEFR">
        <a href="<?= $fcUrl(['level' => 'all']) ?>" class="level-chip <?= $activeLevel === 'all' ? 'is-active' : '' ?>"><?= __('fc.all') ?></a>
        <?php foreach (\App\Src\Flashcard::PACK_LEVELS as $lv): if (empty($levelCounts[$lv])) continue; ?>
        <a href="<?= $fcUrl(['level' => $lv]) ?>" class="level-chip <?= $activeLevel === $lv ? 'is-active' : '' ?>"><?= $lv ?> <span class="opacity-70"><?= (int)$levelCounts[$lv] ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="flex flex-wrap gap-xs py-xs" id="category-filters">
        <button class="filter-chip active-filter bg-primary text-on-primary text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all hover:opacity-90" data-cat="all"><?= __('fc.all') ?></button>
        <?php foreach ($uniqueCats as $cat): ?>
        <button class="filter-chip bg-surface-container-high border border-outline-variant/30 text-on-surface-variant hover:text-on-surface text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($catLabels[$cat] ?? $cat) ?></button>
        <?php endforeach; ?>
      </div>

      <?php if ($packs && $activeView === 'all'): ?>
      <div class="fc-packs rounded-xl border border-outline-variant/20 bg-surface-container-high/50 p-3" id="fc-packs">
        <div class="flex items-center gap-2 text-[11px] font-bold text-on-surface uppercase tracking-wider">
          <span class="material-symbols-outlined text-[16px] text-primary">library_add</span>
          <?= __('fc.packs_title') ?>
        </div>
        <p class="text-[10px] text-on-surface-variant mt-1 mb-2"><?= __('fc.packs_hint') ?></p>
        <div class="flex flex-col gap-1.5">
          <?php foreach ($packs as $lv => $pk): $done = $pk['added'] >= $pk['total']; ?>
          <div class="flex items-center justify-between gap-2 text-[11px]">
            <div class="flex items-center gap-2 min-w-0">
              <span class="level-badge"><?= $lv ?></span>
              <span class="text-on-surface-variant truncate"><?= sprintf(__('fc.pack_words'), $pk['total']) ?></span>
            </div>
            <?php if ($done): ?>
              <span class="pack-done"><span class="material-symbols-outlined text-[14px]">check</span><?= __('fc.pack_added') ?></span>
            <?php else: ?>
              <button type="button" class="pack-add-btn" data-level="<?= $lv ?>"><?= __('fc.pack_add') ?><?= $pk['added'] > 0 ? ' +' . ($pk['total'] - $pk['added']) : '' ?></button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="flex-1 flex flex-col gap-xs overflow-y-auto chat-scrollbar" id="words-list"></div>
    </aside>

    <!-- Main Board -->
    <section class="flex-1 flex flex-col items-center justify-between p-6 overflow-y-auto chat-scrollbar relative">

      <!-- Header Area (Top Bar & Progress) -->
      <div class="w-full max-w-6xl flex flex-col gap-6 shrink-0 pt-2">
        <!-- Top Bar -->
        <div class="flex items-center justify-between gap-md">
          <div class="flex items-center gap-2 min-w-0">
            <button onclick="fcToggleSidebar()" class="lg:hidden shrink-0 text-on-surface-variant hover:text-on-surface transition-colors flex items-center justify-center p-1.5 rounded-full hover:bg-surface-container-high/50 border border-outline-variant/20" aria-label="<?= __('fc.word_list') ?>">
              <span class="material-symbols-outlined text-[18px]">list</span>
            </button>
            <?= $targetFlag ?>
            <label class="sr-only" for="fc-lang"><?= __('fc.deck_language') ?></label>
            <select id="fc-lang" class="fc-lang-select" title="<?= __('fc.deck_language') ?>" onchange="location.href='?page=flashcards&lang='+encodeURIComponent(this.value)">
              <?php foreach ($deckLangs as $dl): ?>
              <option value="<?= htmlspecialchars($dl) ?>" <?= $dl === $targetLang ? 'selected' : '' ?>><?= htmlspecialchars(__('languages.' . $dl, \App\Src\Language::langName($dl))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <span class="hidden sm:flex items-center gap-2 text-label-md text-primary font-bold">
              <span class="material-symbols-outlined text-[16px] text-yellow-500 animate-pulse">workspace_premium</span>
              <span id="session-xp"><?= sprintf(__('fc.xp_earned'), 0) ?></span>
            </span>
            <button type="button" id="btn-new-card" class="fc-new-btn">
              <span class="material-symbols-outlined text-[18px]">add</span><span><?= __('fc.new_card') ?></span>
            </button>
          </div>
        </div>

        <!-- Progress Bar -->
        <div id="card-controls" class="w-full flex items-center gap-md <?= count($cards) ? '' : 'hidden' ?>">
          <div class="flex-1 flex flex-col gap-1.5">
            <div class="flex items-center justify-between w-full text-[10px] text-outline font-semibold uppercase tracking-wider">
              <span id="card-counter"><?= sprintf(__('fc.card_counter'), count($cards), count($cards)) ?></span>
              <span id="percent-complete"><?= sprintf(__('fc.percent_learned'), 0) ?></span>
            </div>
            <div class="w-full bg-surface-container-highest rounded-full h-2 overflow-hidden shadow-inner">
              <div id="progress-bar-fill" class="bg-gradient-to-r from-teal-400 to-indigo-500 h-2 rounded-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(45,212,191,0.5)]" style="width: 0%"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Flashcards Grid Stage -->
      <div id="card-stage" class="flex-1 w-full max-w-6xl flex flex-col pt-6 pb-12 relative min-h-[420px]">
        
        <div id="empty-deck" class="<?= count($cards) ? 'hidden' : '' ?> w-full max-w-md mx-auto text-center mt-12 px-6">
          <div class="mx-auto w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-primary text-[32px]">style</span>
          </div>
          <?php
          $emptyText = [
            'favorites' => __('fc.empty_favorites'),
            'learned' => __('fc.empty_learned'),
            'mine' => __('fc.empty_mine'),
            'chat' => __('fc.no_chat_cards'),
          ][$activeView] ?? null;
          ?>
          <?php if ($emptyText): ?>
          <p class="text-sm text-on-surface-variant mb-4"><?= htmlspecialchars($emptyText) ?></p>
          <button type="button" class="fc-new-btn mx-auto" data-new-card><span class="material-symbols-outlined text-[18px]">add</span><?= __('fc.new_card') ?></button>
          <?php else: ?>
          <h3 class="text-lg font-bold text-on-surface mb-2"><?= __('fc.empty_title') ?></h3>
          <p class="text-sm text-on-surface-variant mb-4"><?= __('fc.empty_body') ?></p>
          <div class="flex flex-wrap gap-2 justify-center">
            <button id="btn-import-cards" class="bg-primary text-on-primary text-xs font-semibold px-6 py-3 rounded-xl shadow-md hover:opacity-90 transition-opacity">
              <?= __('fc.import_static') ?>
            </button>
            <button type="button" class="fc-new-btn" data-new-card><span class="material-symbols-outlined text-[18px]">add</span><?= __('fc.new_card') ?></button>
          </div>
          <?php endif; ?>
        </div>

        <div id="card-wrapper" class="<?= count($cards) ? '' : 'hidden' ?> w-full flex flex-col">
          <!-- Grid Container -->
          <div id="cards-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 w-full auto-rows-max">
            <!-- Cards will be dynamically injected here by JS -->
          </div>
        </div>
      </div>

    </section>
  </div>
</main>

<dialog id="fc-modal" class="fc-modal" aria-labelledby="fc-modal-title">
  <form id="fc-form" method="dialog" class="flex flex-col gap-3" novalidate>
    <div class="flex items-center justify-between gap-2">
      <h2 id="fc-modal-title" class="text-base font-bold text-on-surface"><?= __('fc.new_card') ?></h2>
      <button type="button" class="fc-icon-btn" data-close aria-label="<?= __('fc.cancel') ?>"><span class="material-symbols-outlined text-[20px]">close</span></button>
    </div>
    <label class="fc-field"><span><?= __('fc.field_word') ?> *</span>
      <input name="word" maxlength="120" required dir="<?= $isRtlTarget ? 'rtl' : 'auto' ?>" autocomplete="off"></label>
    <label class="fc-field"><span><?= __('fc.field_translation') ?></span>
      <input name="translation" maxlength="200" autocomplete="off"></label>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <label class="fc-field"><span><?= __('fc.field_pronunciation') ?></span>
        <input name="pronunciation" maxlength="120" autocomplete="off"></label>
      <label class="fc-field"><span><?= __('fc.field_category') ?></span>
        <input name="category" maxlength="40" list="fc-cat-list" placeholder="<?= htmlspecialchars(__('fc.category_custom')) ?>" autocomplete="off"></label>
    </div>
    <datalist id="fc-cat-list">
      <?php foreach ($flashcard->getCategories($currentUser['id'], $targetLang) as $cr): ?>
      <option value="<?= htmlspecialchars($cr['category']) ?>">
      <?php endforeach; ?>
    </datalist>
    <label class="fc-field"><span><?= __('fc.field_example') ?></span>
      <textarea name="example" maxlength="400" rows="2" dir="<?= $isRtlTarget ? 'rtl' : 'auto' ?>"></textarea></label>
    <label class="fc-field"><span><?= __('fc.field_example_translation') ?></span>
      <textarea name="example_translation" maxlength="400" rows="2"></textarea></label>
    <label class="fc-field"><span><?= __('fc.field_note') ?></span>
      <textarea name="note" maxlength="1000" rows="2"></textarea></label>
    <label class="flex items-center gap-2 text-xs text-on-surface-variant"><input type="checkbox" name="is_favorite"> <?= __('fc.favorite_add') ?></label>
    <p id="fc-form-error" class="text-xs text-red-400 hidden" role="alert"></p>
    <div class="flex items-center justify-between gap-2 pt-1">
      <button type="button" id="fc-delete" class="fc-danger-btn hidden"><span class="material-symbols-outlined text-[18px]">delete</span><?= __('fc.delete') ?></button>
      <div class="flex gap-2 ml-auto">
        <button type="button" class="fc-ghost-btn" data-close><?= __('fc.cancel') ?></button>
        <button type="submit" class="fc-new-btn"><?= __('fc.save') ?></button>
      </div>
    </div>
  </form>
</dialog>

<div id="toast-container" class="fixed bottom-lg right-lg flex flex-col gap-sm z-50 pointer-events-none"></div>

<script>
  function fcOpenSidebar() {
    var el = document.getElementById('fc-sidebar');
    var bd = document.getElementById('fc-sidebar-backdrop');
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
    if (bd) bd.classList.remove('hidden');
  }
  function fcCloseSidebar() {
    var el = document.getElementById('fc-sidebar');
    var bd = document.getElementById('fc-sidebar-backdrop');
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
    if (bd) bd.classList.add('hidden');
  }
  function fcToggleSidebar() {
    var el = document.getElementById('fc-sidebar');
    if (!el) return;
    if (el.classList.contains('hidden')) { fcOpenSidebar(); } else { fcCloseSidebar(); }
  }
</script>

<!-- Config -->
<script>
window.__FC_CONFIG__ = {
  cards: <?= $cardsJson ?>,
  targetLang: "<?= htmlspecialchars($targetLang) ?>",
  nativeLang: "<?= htmlspecialchars($nativeLang) ?>",
  isRtl: <?= $isRtlTarget ? 'true' : 'false' ?>,
  userId: <?= (int)$currentUser['id'] ?>,
  texts: {
    learnedBadge: "<?= __('fc.learned_badge') ?>",
    remainingBadge: "<?= __('fc.remaining_badge') ?>",
    xpEarned: "<?= __('fc.xp_earned') ?>",
    xpToast: "<?= __('fc.xp_toast') ?>",
    cardCounter: "<?= __('fc.card_counter') ?>",
    percentLearned: "<?= __('fc.percent_learned') ?>",
    speechNotSupported: "<?= __('fc.speech_not_supported') ?>",
    importSuccess: "<?= __('fc.import_success') ?>",
    packError: "<?= __('fc.pack_error') ?>",
  },
  // Every visible string the script builds (JSON-encoded, so quotes are safe).
  t: <?= json_encode([
    'tapToFlip' => __('fc.tap_to_flip'), 'translation' => __('fc.translation_label'),
    'again' => __('fc.quality_again'), 'hard' => __('fc.quality_hard'), 'good' => __('fc.quality_good'), 'easy' => __('fc.quality_easy'),
    'listen' => __('fc.audio_btn'), 'favAdd' => __('fc.favorite_add'), 'favRemove' => __('fc.favorite_remove'),
    'markLearned' => __('fc.mark_learned'), 'unmarkLearned' => __('fc.unmark_learned'),
    'edit' => __('fc.edit_card'), 'newCard' => __('fc.new_card'), 'deleteConfirm' => __('fc.delete_confirm'),
    'saved' => __('fc.saved'), 'deleted' => __('fc.deleted'), 'markedLearned' => __('fc.marked_learned'),
    'unmarkedLearned' => __('fc.unmarked_learned'), 'errGeneric' => __('fc.err_generic'), 'noted' => __('fc.marked_review'),
    'learnedBadge' => __('fc.view_learned'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  catLabels: <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  view: "<?= $activeView ?>",
  speechLocale: <?= json_encode(\App\Src\Language::speechLocale($targetLang)) ?>,
  csrf: "<?= htmlspecialchars(csrf_token()) ?>",
};
</script>
<script src="js/flashcard.js?v=9"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
