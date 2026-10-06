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
// ?list= opens one of the user's card lists (playlists) instead of a view.
$cardLists = new \App\Src\CardLists($db);
$lists = $cardLists->all($currentUser['id'], $targetLang);
$listLabel = fn(array $l): string => $l['is_default'] ? __('fc.list_saved') : $l['name'];
$activeList = null;
foreach ($lists as $l) {
    if ($l['id'] === (int)($_GET['list'] ?? 0)) {
        $activeList = $l;
    }
}
if ($activeList) {
    $activeView = 'list';
    $activeLevel = 'all';
    $cards = $flashcard->getAllCards($currentUser['id'], $targetLang, null, null, \App\Src\CardLists::MAX_ITEMS, null, 'all', 0, $activeList['id']);
} else {
    $cards = $flashcard->getAllCards($currentUser['id'], $targetLang, null, null, $levelArg ? 1000 : 400, $levelArg, $activeView);
}
$fcStats = $flashcard->getStats($currentUser['id'], $targetLang);
$viewTabs = [
    'all' => ['label' => __('fc.view_all'), 'icon' => 'style', 'n' => $fcStats['total']],
    'favorites' => ['label' => __('fc.view_favorites'), 'icon' => 'star', 'n' => $fcStats['favorites']],
    'learned' => ['label' => __('fc.view_learned'), 'icon' => 'task_alt', 'n' => $fcStats['learned']],
    'mine' => ['label' => __('fc.view_mine'), 'icon' => 'edit_note', 'n' => $fcStats['mine']],
    'chat' => ['label' => __('fc.tab_chat_words'), 'icon' => 'forum', 'n' => $fcStats['chat_words']],
];
$deckLangs = \App\Src\Language::listed('learn', $currentUser['target_lang'] ?? null);
// No deck in your own native language (the chat pickers hide it too).
$deckLangs = array_values(array_diff($deckLangs, [strtolower($currentUser['native_lang'] ?? '')]));
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

<link rel="stylesheet" href="/css/flashcard.css?v=9">


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

      <div>
        <div class="fc-lists-head">
          <span class="fc-lists-title"><?= __('fc.lists') ?></span>
          <button type="button" class="fc-icon-btn" data-new-list title="<?= htmlspecialchars(__('fc.list_new')) ?>" aria-label="<?= htmlspecialchars(__('fc.list_new')) ?>"><span class="material-symbols-outlined text-[18px]">playlist_add</span></button>
        </div>
        <nav class="fc-views" id="fc-list-nav" aria-label="<?= htmlspecialchars(__('fc.lists')) ?>">
          <?php foreach ($lists as $l): $isOn = $activeList && $activeList['id'] === $l['id']; ?>
          <a href="?page=flashcards&amp;list=<?= $l['id'] ?>" class="fc-view <?= $isOn ? 'is-active' : '' ?>"<?= $isOn ? ' aria-current="page"' : '' ?>>
            <span class="material-symbols-outlined text-[18px]"><?= $l['is_default'] ? 'bookmark' : 'playlist_play' ?></span>
            <span class="flex-1 truncate"><?= htmlspecialchars($listLabel($l)) ?></span>
            <span class="fc-view-n"><?= $l['cards'] ?></span>
          </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <?php if (!$activeList && (count(array_filter($levelCounts)) > 1 || $activeLevel !== 'all')): ?>
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
        <div class="flex items-center justify-between gap-md flex-wrap">
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
          <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
            <span class="hidden sm:flex items-center gap-2 text-label-md text-primary font-bold">
              <span class="material-symbols-outlined text-[16px] text-yellow-500 animate-pulse">workspace_premium</span>
              <span id="session-xp"><?= sprintf(__('fc.xp_earned'), 0) ?></span>
            </span>
            <a href="?page=words" class="fc-ghost-btn inline-flex items-center gap-1" title="<?= htmlspecialchars(__('fc.words_title')) ?>"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">menu_book</span><span class="hidden sm:inline"><?= __('fc.words_title') ?></span><span class="sm:hidden sr-only"><?= __('fc.words_title') ?></span></a>
            <button type="button" id="btn-select" class="fc-ghost-btn <?= count($cards) ? '' : 'hidden' ?>"><?= __('fc.select') ?></button>
            <button type="button" id="btn-study" class="fc-study-btn" <?= count($cards) ? '' : 'disabled' ?>>
              <span class="material-symbols-outlined text-[18px]">play_arrow</span><span><?= __('fc.study') ?></span>
            </button>
            <button type="button" id="btn-new-card" class="fc-new-btn">
              <span class="material-symbols-outlined text-[18px]">add</span><span class="hidden sm:inline"><?= __('fc.new_card') ?></span>
            </button>
          </div>
        </div>

        <?php if ($activeList): ?>
        <div class="fc-list-title">
          <span class="material-symbols-outlined text-[20px] text-primary"><?= $activeList['is_default'] ? 'bookmark' : 'playlist_play' ?></span>
          <h1 id="fc-list-name"><?= htmlspecialchars($listLabel($activeList)) ?></h1>
          <?php if (!$activeList['is_default']): ?>
          <button type="button" class="fc-icon-btn" id="btn-list-rename" title="<?= htmlspecialchars(__('fc.list_rename')) ?>" aria-label="<?= htmlspecialchars(__('fc.list_rename')) ?>"><span class="material-symbols-outlined text-[18px]">edit</span></button>
          <button type="button" class="fc-icon-btn" id="btn-list-delete" title="<?= htmlspecialchars(__('fc.list_delete')) ?>" aria-label="<?= htmlspecialchars(__('fc.list_delete')) ?>"><span class="material-symbols-outlined text-[18px]">delete</span></button>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Lists as chips on phones (the sidebar is a drawer there) -->
        <div class="fc-list-chips lg:hidden" aria-label="<?= htmlspecialchars(__('fc.lists')) ?>">
          <?php foreach ($lists as $l): $isOn = $activeList && $activeList['id'] === $l['id']; ?>
          <a href="?page=flashcards&amp;list=<?= $l['id'] ?>" class="fc-chip <?= $isOn ? 'is-active' : '' ?>"<?= $isOn ? ' aria-current="page"' : '' ?>>
            <span class="material-symbols-outlined text-[15px]"><?= $l['is_default'] ? 'bookmark' : 'playlist_play' ?></span><?= htmlspecialchars($listLabel($l)) ?><span class="fc-view-n"><?= $l['cards'] ?></span>
          </a>
          <?php endforeach; ?>
          <button type="button" class="fc-chip" data-new-list><span class="material-symbols-outlined text-[15px]">add</span><?= __('fc.list_new') ?></button>
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
            'list' => __('fc.list_empty'),
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

<!-- Selection bar: what to do with the selected cards -->
<div id="fc-selbar" class="fc-selbar" hidden role="toolbar" aria-label="<?= htmlspecialchars(__('fc.select')) ?>">
  <span id="fc-selbar-n" class="fc-selbar-n"></span>
  <button type="button" class="fc-ghost-btn" id="fc-sel-all"><?= __('fc.select_all') ?></button>
  <button type="button" class="fc-new-btn" id="fc-sel-save"><span class="material-symbols-outlined text-[18px]">playlist_add</span><?= __('fc.list_save_to') ?></button>
  <?php if ($activeList): ?>
  <button type="button" class="fc-danger-btn" id="fc-sel-remove"><span class="material-symbols-outlined text-[18px]">playlist_remove</span><?= __('fc.list_remove_selected') ?></button>
  <?php endif; ?>
  <button type="button" class="fc-ghost-btn" id="fc-sel-done"><?= __('fc.done') ?></button>
</div>

<!-- "Save to…": tick the lists a card belongs in (or, for a selection, add / move) -->
<dialog id="fc-save-modal" class="fc-modal" aria-labelledby="fc-save-title">
  <div class="flex items-center justify-between gap-2">
    <h2 id="fc-save-title" class="text-base font-bold text-on-surface"><?= __('fc.list_save_to') ?></h2>
    <button type="button" class="fc-icon-btn" data-close aria-label="<?= __('fc.cancel') ?>"><span class="material-symbols-outlined text-[20px]">close</span></button>
  </div>
  <p id="fc-save-sub" class="text-xs text-on-surface-variant mt-1"></p>
  <div id="fc-save-list" class="fc-save-list"></div>
  <form id="fc-save-new" class="fc-save-new" autocomplete="off">
    <input name="name" maxlength="60" placeholder="<?= htmlspecialchars(__('fc.list_name')) ?>" aria-label="<?= htmlspecialchars(__('fc.list_name')) ?>">
    <button type="submit" class="fc-new-btn"><span class="material-symbols-outlined text-[18px]">add</span><?= __('fc.list_new') ?></button>
  </form>
</dialog>

<!-- New list / rename -->
<dialog id="fc-list-modal" class="fc-modal" aria-labelledby="fc-list-modal-title">
  <form id="fc-list-form" method="dialog" class="flex flex-col gap-3" autocomplete="off">
    <h2 id="fc-list-modal-title" class="text-base font-bold text-on-surface"><?= __('fc.list_new') ?></h2>
    <label class="fc-field"><span><?= __('fc.list_name') ?></span><input name="name" maxlength="60" required></label>
    <p id="fc-list-error" class="text-xs text-red-400 hidden" role="alert"></p>
    <div class="flex gap-2 justify-end">
      <button type="button" class="fc-ghost-btn" data-close><?= __('fc.cancel') ?></button>
      <button type="submit" class="fc-new-btn"><?= __('fc.save') ?></button>
    </div>
  </form>
</dialog>

<!-- Study mode -->
<div id="fc-study" class="fc-study" hidden role="dialog" aria-modal="true" aria-labelledby="fc-study-name">
  <div class="fc-study-top">
    <button type="button" class="fc-icon-btn" id="fc-study-close" aria-label="<?= htmlspecialchars(__('fc.study_close')) ?>"><span class="material-symbols-outlined">close</span></button>
    <span id="fc-study-name" class="fc-study-name"></span>
    <span id="fc-study-count" class="fc-study-count"></span>
    <button type="button" class="fc-icon-btn" id="fc-study-shuffle" aria-pressed="false" title="<?= htmlspecialchars(__('fc.study_shuffle')) ?>" aria-label="<?= htmlspecialchars(__('fc.study_shuffle')) ?>"><span class="material-symbols-outlined">shuffle</span></button>
  </div>
  <div class="fc-study-track"><div id="fc-study-fill" class="fc-study-fill"></div></div>
  <div id="fc-study-play" class="contents">
    <div class="fc-study-stage">
      <div id="fc-study-card" class="fc-study-card">
        <div class="fc-study-inner">
          <div class="fc-study-face fc-study-front">
            <span id="fc-study-cat" class="fc-study-tag"></span>
            <button type="button" class="fc-icon-btn fc-study-speak" id="fc-study-speak" aria-label="<?= htmlspecialchars(__('fc.audio_btn')) ?>"><span class="material-symbols-outlined">volume_up</span></button>
            <div id="fc-study-word" class="fc-study-word" dir="<?= $isRtlTarget ? 'rtl' : 'auto' ?>"></div>
            <div id="fc-study-pron" class="fc-study-pron"></div>
          </div>
          <div class="fc-study-face fc-study-back">
            <span class="fc-study-tag"><?= __('fc.translation_label') ?></span>
            <div id="fc-study-trans" class="fc-study-trans"></div>
            <div id="fc-study-ex" class="fc-study-ex" dir="<?= $isRtlTarget ? 'rtl' : 'auto' ?>"></div>
            <div id="fc-study-ex-tr" class="fc-study-ex-tr"></div>
            <div id="fc-study-note" class="fc-note"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="fc-study-nav">
      <button type="button" class="fc-study-round" id="fc-study-prev" aria-label="<?= htmlspecialchars(__('fc.study_prev')) ?>"><span class="material-symbols-outlined">arrow_back</span></button>
      <button type="button" class="fc-study-round is-main" id="fc-study-flip" aria-label="<?= htmlspecialchars(__('fc.study_flip')) ?>"><span class="material-symbols-outlined">flip</span></button>
      <button type="button" class="fc-study-round" id="fc-study-next" aria-label="<?= htmlspecialchars(__('fc.study_next')) ?>"><span class="material-symbols-outlined">arrow_forward</span></button>
    </div>
    <p class="fc-study-hint"><?= __('fc.study_hint') ?></p>
  </div>
  <div id="fc-study-done" class="fc-study-done" hidden>
    <span class="material-symbols-outlined text-[48px] text-teal-300">celebration</span>
    <h2><?= __('fc.study_done_title') ?></h2>
    <p id="fc-study-done-body"></p>
    <div class="flex flex-wrap gap-2 justify-center">
      <button type="button" class="fc-study-btn" id="fc-study-flipped"></button>
      <button type="button" class="fc-new-btn" id="fc-study-again"><?= __('fc.study_again') ?></button>
      <button type="button" class="fc-ghost-btn" id="fc-study-exit"><?= __('fc.study_close') ?></button>
    </div>
  </div>
</div>

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
    learnedBadge: "<?= jsq(__('fc.learned_badge')) ?>",
    remainingBadge: "<?= jsq(__('fc.remaining_badge')) ?>",
    xpEarned: "<?= jsq(__('fc.xp_earned')) ?>",
    xpToast: "<?= jsq(__('fc.xp_toast')) ?>",
    cardCounter: "<?= jsq(__('fc.card_counter')) ?>",
    percentLearned: "<?= jsq(__('fc.percent_learned')) ?>",
    speechNotSupported: "<?= jsq(__('fc.speech_not_supported')) ?>",
    importSuccess: "<?= jsq(__('fc.import_success')) ?>",
    packError: "<?= jsq(__('fc.pack_error')) ?>",
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
    'saveTo' => __('fc.list_save_to'), 'listNew' => __('fc.list_new'), 'listRename' => __('fc.list_rename'), 'listDeleteConfirm' => __('fc.list_delete_confirm'),
    'listAdded' => __('fc.list_added'), 'listRemoved' => __('fc.list_removed'), 'listMoved' => __('fc.list_moved'),
    'listAdd' => __('fc.list_add_here'), 'listMove' => __('fc.list_move_here'), 'listCreated' => __('fc.list_created'),
    'saveToOne' => __('fc.list_save_hint'), 'saveToMany' => __('fc.list_save_hint_many'), 'selected' => __('fc.selected'),
    'studyDone' => __('fc.study_done_body'), 'studyFlipped' => __('fc.study_flipped_again'), 'studyEmpty' => __('fc.study_empty'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  catLabels: <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  view: "<?= $activeView ?>",
  lists: <?= json_encode(array_map(fn($l) => $l + ['label' => $listLabel($l)], $lists), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  listId: <?= $activeList ? (int)$activeList['id'] : 0 ?>,
  studyName: <?= json_encode($activeList ? $listLabel($activeList) : ($viewTabs[$activeView]['label'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  speechLocale: <?= json_encode(\App\Src\Language::speechLocale($targetLang)) ?>,
  csrf: "<?= htmlspecialchars(csrf_token()) ?>",
};
</script>
<script src="/js/flashcard.js?v=10"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
