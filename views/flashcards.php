<?php
require_once __DIR__ . '/partials/flags.php';
$pageTitle = __('fc.page_title');

$currentUser = $currentUser ?? $auth->currentUser();
$targetLang = strtolower($currentUser['target_lang'] ?? 'en');
$nativeLang = strtolower($currentUser['native_lang'] ?? 'en');

$langNames = [
    'en' => 'English', 'de' => 'German',
    'fr' => 'French', 'es' => 'Spanish', 'zh' => 'Chinese',
    'ja' => 'Japanese', 'ar' => 'Arabic', 'tr' => 'Turkish',
];
$targetLangName = $langNames[$targetLang] ?? strtoupper($targetLang);
$nativeLangName = $langNames[$nativeLang] ?? strtoupper($nativeLang);
$targetFlag = flagImg($targetLang, 'w-6 h-4');
$nativeFlag = flagImg($nativeLang, 'w-5 h-4');
$rtlLangs = ['ar', 'he', 'fa', 'ur'];
$isRtlTarget = in_array($targetLang, $rtlLangs, true);

// Load cards — ensure minimum deck exists
$cards = $flashcard->getAllCards($currentUser['id'], $targetLang);
if (count($cards) < 50) {
    $flashcard->importStaticCards($currentUser['id'], $targetLang, $nativeLang);
    $cards = $flashcard->getAllCards($currentUser['id'], $targetLang);
}
$cardsJson = json_encode($cards, JSON_UNESCAPED_UNICODE);
$firstCard = $cards[0] ?? null;
?>

<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<link rel="stylesheet" href="css/flashcard.css?v=4">


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
      $uniqueCats = array_unique(array_map(function($c) { return $c['category'] ?? 'General'; }, $cards));
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
      ];
      ?>
      <div class="flex flex-wrap gap-xs py-xs" id="category-filters">
        <button class="filter-chip active-filter bg-primary text-on-primary text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all hover:opacity-90" data-cat="all"><?= __('fc.all') ?></button>
        <?php foreach ($uniqueCats as $cat): ?>
        <button class="filter-chip bg-surface-container-high border border-outline-variant/30 text-on-surface-variant hover:text-on-surface text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($catLabels[$cat] ?? $cat) ?></button>
        <?php endforeach; ?>
      </div>

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
            <span class="text-xs font-semibold text-on-surface-variant truncate"><?= sprintf(__('fc.words_in'), $targetLangName) ?></span>
          </div>
          <div class="flex items-center gap-2 text-label-md text-primary font-bold shrink-0">
            <span class="material-symbols-outlined text-[16px] text-yellow-500 animate-pulse">workspace_premium</span>
            <span id="session-xp"><?= sprintf(__('fc.xp_earned'), 0) ?></span>
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
          <h3 class="text-lg font-bold text-on-surface mb-2"><?= __('fc.empty_title') ?></h3>
          <p class="text-sm text-on-surface-variant mb-4"><?= __('fc.empty_body') ?></p>
          <button id="btn-import-cards" class="bg-primary text-on-primary text-xs font-semibold px-6 py-3 rounded-xl shadow-md hover:opacity-90 transition-opacity">
            <?= __('fc.import_static') ?>
          </button>
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
  }
};
</script>
<script src="js/flashcard.js?v=4"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
