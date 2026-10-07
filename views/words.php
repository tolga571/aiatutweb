<?php
require_once __DIR__ . '/partials/flags.php';
$pageTitle = __('fc.words_title');

$currentUser = $currentUser ?? $auth->currentUser();
$targetLang = strtolower($deckLang ?? ($currentUser['target_lang'] ?? 'en'));
$nativeLang = strtolower($currentUser['native_lang'] ?? 'en');
$isRtlTarget = \App\Src\Language::dir($targetLang) === 'rtl';

$words = (new \App\Src\Flashcard($db))->wordBank($currentUser['id'], $targetLang, $nativeLang);
$levels = array_values(array_unique(array_column($words, 'level')));
sort($levels);
$categories = array_values(array_unique(array_column($words, 'category')));
sort($categories);
$catLabel = function (string $c): string {
    $key = 'fc.category_' . strtolower($c);
    $label = __($key);
    return $label === $key ? $c : $label;
};
$deckLangs = \App\Src\Language::listed('learn', $currentUser['target_lang'] ?? null);
$deckLangs = array_values(array_diff($deckLangs, [$nativeLang]));
if (!in_array($targetLang, $deckLangs, true)) {
    $deckLangs[] = $targetLang;
}
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<link rel="stylesheet" href="/css/flashcard.css?v=9">

<main class="flex-1 bg-surface-dim">
  <div class="fc-words">
    <div class="fc-words-head">
      <div class="min-w-0">
        <a href="?page=flashcards" class="fc-words-back"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span><?= __('nav.flashcards') ?></a>
        <h1 class="fc-words-title"><?= __('fc.words_title') ?></h1>
        <p class="fc-words-sub"><?= htmlspecialchars(t('fc.words_sub', ['lang' => __('languages.' . $targetLang, \App\Src\Language::langName($targetLang))])) ?></p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <?= flagImg($targetLang, 'w-6 h-4') ?>
        <label class="sr-only" for="fc-lang"><?= __('fc.deck_language') ?></label>
        <select id="fc-lang" class="fc-lang-select" onchange="location.href='?page=words&lang='+encodeURIComponent(this.value)">
          <?php foreach ($deckLangs as $dl): ?>
          <option value="<?= htmlspecialchars($dl) ?>" <?= $dl === $targetLang ? 'selected' : '' ?>><?= htmlspecialchars(__('languages.' . $dl, \App\Src\Language::langName($dl))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    
    <!-- Bulk Actions Toolbar -->
    <div id="wb-bulk-toolbar" class="hidden flex items-center justify-between bg-primary text-primary-foreground p-3 rounded-lg mb-4 shadow-md sticky top-16 z-40 transition-all duration-300">
      <div class="flex items-center gap-3">
        <button id="wb-bulk-close" class="material-symbols-outlined hover:text-white" title="Close">close</button>
        <span id="wb-bulk-count" class="font-bold">0 selected</span>
      </div>
      <div class="flex gap-2">
        <button id="wb-bulk-select-all" class="px-3 py-1 text-sm bg-white/20 hover:bg-white/30 rounded">Select All Visible</button>
        <button id="wb-bulk-add" class="px-3 py-1 text-sm bg-white text-primary hover:bg-surface rounded font-bold">Add Selected</button>
      </div>
    </div>

    <div class="fc-words-tools" role="search">
      <div class="relative flex-1 min-w-[12rem]">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]" aria-hidden="true">search</span>
        <input type="search" id="wb-q" class="fc-words-input pl-9" placeholder="<?= htmlspecialchars(__('fc.words_search')) ?>" aria-label="<?= htmlspecialchars(__('fc.words_search')) ?>" autocomplete="off">
      </div>
      <select id="wb-level" class="fc-words-input w-auto" aria-label="<?= htmlspecialchars(__('fc.col_level')) ?>">
        <option value=""><?= __('fc.words_all_levels') ?></option>
        <?php foreach ($levels as $lv): ?><option value="<?= htmlspecialchars($lv) ?>"><?= htmlspecialchars($lv) ?></option><?php endforeach; ?>
      </select>
      <select id="wb-cat" class="fc-words-input w-auto" aria-label="<?= htmlspecialchars(__('fc.col_category')) ?>">
        <option value=""><?= __('fc.words_all_cats') ?></option>
        <?php foreach ($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($catLabel($c)) ?></option><?php endforeach; ?>
      </select>
      <label class="fc-words-check"><input type="checkbox" id="wb-new"> <?= __('fc.words_only_new') ?></label>
    </div>

    <p id="wb-count" class="fc-words-count" aria-live="polite"></p>

    <table class="fc-words-table">
      <thead>
        <tr>
          <th scope="col" class="w-10"><input type="checkbox" id="wb-bulk-master" aria-label="Select all"></th>
          <th scope="col"><?= __('fc.col_word') ?></th>
          <th scope="col"><?= __('fc.col_meaning') ?></th>
          <th scope="col" class="fc-words-narrow"><?= __('fc.col_level') ?></th>
          <th scope="col" class="fc-words-cat"><?= __('fc.col_category') ?></th>
          <th scope="col" class="fc-words-narrow"><span class="sr-only"><?= __('fc.words_add') ?></span></th>
        </tr>
      </thead>
      <tbody id="wb-rows"></tbody>
    </table>
    <p id="wb-none" class="fc-words-none" hidden><?= __('fc.words_none') ?></p>
    <div class="flex justify-center py-4"><button type="button" id="wb-more" class="fc-ghost-btn" hidden><?= __('fc.words_more') ?></button></div>
  </div>
</main>

<div id="toast-container" class="fixed bottom-lg right-lg flex flex-col gap-sm z-50 pointer-events-none"></div>

<script>
window.__WB__ = {
  words: <?= json_encode($words, $jsonFlags) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  rtl: <?= $isRtlTarget ? 'true' : 'false' ?>,
  speechLocale: <?= json_encode(\App\Src\Language::speechLocale($targetLang)) ?>,
  cats: <?= json_encode(array_combine($categories, array_map($catLabel, $categories)) ?: new stdClass(), $jsonFlags) ?>,
  t: <?= json_encode([
    'add' => __('fc.words_add'), 'added' => __('fc.words_added'), 'inDeck' => __('fc.words_in_deck'),
    'count' => __('fc.words_count'), 'listen' => __('fc.audio_btn'), 'err' => __('fc.err_generic'),
  ], $jsonFlags) ?>,
};
</script>
<script src="/js/words.js?v=1"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
