<?php
// One story in one language: /stories/<slug>/<lang>. Public and indexable;
// the plain text and the word-by-word view are both in the HTML, the
// meaning language, read-aloud and the word drawer are public/js/story.js.
require_once __DIR__ . '/partials/flags.php';
use App\Src\Language;
use App\Src\Stories;

$stories = new Stories($db);
$storyId = (int)$storyRow['id'];
$have = $stories->languagesOf($storyId);
$isLoggedIn = $auth->isLoggedIn();
$me = $isLoggedIn ? $auth->currentUser() : null;

$lang = strtolower((string)($_GET['lang'] ?? ''));
if (!in_array($lang, $have, true)) {
    // No (or an unknown) language in the URL: the learner's target
    // language when the story has it, else English.
    $pick = ($me && in_array($me['target_lang'] ?? '', $have, true)) ? $me['target_lang'] : (in_array('en', $have, true) ? 'en' : ($have[0] ?? 'en'));
    header('Location: /stories/' . rawurlencode($storyRow['slug']) . '/' . $pick, true, 302);
    exit;
}
$text = $stories->text($storyId, $lang);
$titles = $stories->titles($storyId);

// Meanings: the learner's native language; for a guest the browser's
// language; never the story's own language.
$meaningLangs = array_values(array_filter(Language::listed('learn'), fn($l) => $l !== $lang && in_array($l, $have, true)));
$meaning = $me ? strtolower((string)($me['native_lang'] ?? '')) : (Language::fromAcceptLanguage((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) ?? '');
if (!in_array($meaning, $meaningLangs, true)) {
    $meaning = in_array('en', $meaningLangs, true) ? 'en' : ($meaningLangs[0] ?? 'en');
}

$catNames = $stories->categoryNames(Language::currentLang());
$dir = Language::dir($lang);
$unspaced = in_array($lang, ['zh', 'ja'], true);
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// Recorded read-aloud (scripts/stories/audio.py): the MP3 and when each
// sentence and word starts and ends. Without it the browser's voice reads.
$audio = null;
$audioBase = '/media/stories/' . $storyRow['slug'] . '/' . $lang;
$audioJson = __DIR__ . '/../public' . $audioBase . '.json';
if (is_file($audioJson) && is_file(__DIR__ . '/../public' . $audioBase . '.mp3')) {
    $timing = json_decode((string)file_get_contents($audioJson), true);
    if (is_array($timing) && !empty($timing['sentences'])) {
        $audio = ['src' => $audioBase . '.mp3?v=' . filemtime(__DIR__ . '/../public' . $audioBase . '.mp3'), 'sentences' => $timing['sentences'], 'words' => (object)($timing['words'] ?? [])];
    }
}

$pageTitle = $text['title'] . ' – ' . __('stories.heading') . ' – Jumplearner';
$pageDescription = $text['summary'];
$selfPath = '/stories/' . $storyRow['slug'] . '/' . $lang;
?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php foreach ($have as $l): ?>
<link rel="alternate" hreflang="<?= $l ?>" href="https://jumplearner.com/stories/<?= htmlspecialchars($storyRow['slug']) ?>/<?= $l ?>">
<?php endforeach; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<link rel="stylesheet" href="/css/stories.css?v=1">

<main class="st-page">
  <article class="st-wrap st-reader" data-lang="<?= $lang ?>">
    <a href="/stories?lang=<?= $lang ?>" class="st-back"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span><?= __('stories.back') ?></a>

    <header class="st-reader-head">
      <span class="st-meta"><span class="st-cat"><?= htmlspecialchars($catNames[$storyRow['category']] ?? $storyRow['category']) ?></span><span aria-hidden="true">·</span><span><?= __('stories.len_' . $storyRow['length']) ?></span></span>
      <h1 class="st-title" lang="<?= $lang ?>" dir="<?= $dir ?>"><?= htmlspecialchars($text['title']) ?></h1>
    </header>

    <nav class="st-filter" aria-labelledby="st-lang-label">
      <span id="st-lang-label" class="st-label"><?= __('stories.story_language') ?></span>
      <div class="st-chips">
        <?php foreach (Language::listed('learn') as $l): if (!in_array($l, $have, true)) continue; ?>
          <a href="/stories/<?= htmlspecialchars($storyRow['slug']) ?>/<?= $l ?>" lang="<?= $l ?>" class="st-chip<?= $l === $lang ? ' is-on' : '' ?>"<?= $l === $lang ? ' aria-current="page"' : '' ?> title="<?= htmlspecialchars($titles[$l] ?? '') ?>">
            <?= flagImg($l, 'w-5 h-3.5') ?><span><?= htmlspecialchars(Language::nativeName($l)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </nav>

    <div class="st-bar">
      <div class="st-tabs" role="tablist" aria-label="<?= htmlspecialchars(__('stories.mode')) ?>">
        <button type="button" role="tab" id="st-tab-words" aria-controls="st-panel-words" aria-selected="true" class="st-tab is-on"><?= __('stories.tab_words') ?></button>
        <button type="button" role="tab" id="st-tab-text" aria-controls="st-panel-text" aria-selected="false" tabindex="-1" class="st-tab"><?= __('stories.tab_text') ?></button>
      </div>
      <div class="st-audio" id="st-audio" hidden>
        <button type="button" class="st-play" id="st-play" aria-pressed="false">
          <span class="material-symbols-outlined" aria-hidden="true">play_arrow</span><span id="st-play-label"><?= __('stories.listen') ?></span>
        </button>
        <div class="st-rates" role="group" aria-label="<?= htmlspecialchars(__('stories.speed')) ?>">
          <button type="button" class="st-rate" data-rate="0.75" aria-pressed="false">0.75×</button>
          <button type="button" class="st-rate is-on" data-rate="1" aria-pressed="true">1×</button>
          <button type="button" class="st-rate" data-rate="1.25" aria-pressed="false">1.25×</button>
        </div>
      </div>
    </div>

    <section id="st-panel-words" role="tabpanel" aria-labelledby="st-tab-words" class="st-panel">
      <div class="st-filter">
        <span id="st-mean-label" class="st-label"><?= __('stories.meanings_in') ?></span>
        <div class="st-chips" role="group" aria-labelledby="st-mean-label">
          <?php foreach ($meaningLangs as $l): ?>
            <button type="button" class="st-chip st-mean<?= $l === $meaning ? ' is-on' : '' ?>" data-lang="<?= $l ?>" lang="<?= $l ?>" aria-pressed="<?= $l === $meaning ? 'true' : 'false' ?>"><?= flagImg($l, 'w-5 h-3.5') ?><span><?= htmlspecialchars(Language::nativeName($l)) ?></span></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="st-words" lang="<?= $lang ?>" dir="<?= $dir ?>">
        <?php foreach ($text['sentences'] as $si => $s): ?>
          <p class="st-sent" data-s="<?= $si ?>">
            <?php foreach ($s['tokens'] as $ti => $tk): $punct = \App\Src\StoryGenerator::isPunct($tk['t']); ?>
              <?php if ($punct): ?>
                <span class="st-punct"><?= htmlspecialchars($tk['t']) ?></span>
              <?php else: ?>
                <button type="button" class="st-tok" data-s="<?= $si ?>" data-t="<?= $ti ?>">
                  <span class="st-t"><?= htmlspecialchars($tk['t']) ?></span>
                  <span class="st-r" lang="<?= $lang ?>-Latn" dir="ltr"><?= htmlspecialchars($tk['r']) ?></span>
                  <span class="st-m" lang="<?= $meaning ?>" dir="<?= Language::dir($meaning) ?>"><?= htmlspecialchars($tk['m'][$meaning] ?? '') ?></span>
                </button>
              <?php endif; ?>
            <?php endforeach; ?>
          </p>
        <?php endforeach; ?>
      </div>
      <p class="st-hint"><?= __('stories.tap_hint') ?></p>
    </section>

    <section id="st-panel-text" role="tabpanel" aria-labelledby="st-tab-text" class="st-panel" hidden>
      <div class="st-text" lang="<?= $lang ?>" dir="<?= $dir ?>">
        <p><?php foreach ($text['sentences'] as $si => $s): ?><span class="st-line" data-s="<?= $si ?>"><?= htmlspecialchars($s['text']) ?></span><?= $unspaced ? '' : ' ' ?><?php endforeach; ?></p>
      </div>
    </section>

    <?php if (!empty($storyRow['source'])): ?>
      <p class="st-source"><?= __('stories.source') ?>: <?= htmlspecialchars($storyRow['source']) ?></p>
    <?php endif; ?>
  </article>
</main>

<dialog class="st-drawer" id="st-drawer" aria-labelledby="st-d-word">
  <div class="st-d-head">
    <div class="min-w-0">
      <p class="st-d-word" id="st-d-word" lang="<?= $lang ?>" dir="<?= $dir ?>"></p>
      <p class="st-d-read" id="st-d-read" dir="ltr"></p>
    </div>
    <button type="button" class="st-icon-btn" id="st-d-close" aria-label="<?= htmlspecialchars(__('stories.close')) ?>"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
  </div>
  <p class="st-label" id="st-d-mlabel"></p>
  <p class="st-d-mean" id="st-d-mean"></p>
  <p class="st-d-other" id="st-d-other"></p>
  <div class="st-d-actions">
    <button type="button" class="st-btn-ghost" id="st-d-listen" hidden><span class="material-symbols-outlined" aria-hidden="true">volume_up</span><?= __('stories.listen_word') ?></button>
    <?php if ($isLoggedIn): ?>
      <button type="button" class="st-btn" id="st-d-save" aria-pressed="false"><span class="material-symbols-outlined" aria-hidden="true">bookmark_add</span><span id="st-d-save-label"><?= __('stories.save') ?></span></button>
    <?php endif; ?>
  </div>
  <?php if (!$isLoggedIn): ?>
    <div class="st-d-guest">
      <p><?= __('stories.guest_save') ?></p>
      <div class="st-d-actions">
        <a class="st-btn" href="?page=register"><?= __('stories.sign_up') ?></a>
        <a class="st-btn-ghost" href="?page=login&amp;redirect=<?= rawurlencode(ltrim($selfPath, '/')) ?>"><?= __('stories.log_in') ?></a>
      </div>
    </div>
  <?php endif; ?>
  <p class="st-d-msg" id="st-d-msg" role="status"></p>
</dialog>

<script>
window.__STORY__ = {
  slug: <?= json_encode($storyRow['slug']) ?>,
  lang: <?= json_encode($lang) ?>,
  unspaced: <?= $unspaced ? 'true' : 'false' ?>,
  speechLocale: <?= json_encode(Language::speechLocale($lang)) ?>,
  audio: <?= json_encode($audio, $jsonFlags) ?>,
  meaning: <?= json_encode($meaning) ?>,
  names: <?= json_encode(array_combine($meaningLangs, array_map(fn($l) => Language::nativeName($l), $meaningLangs)) ?: new stdClass(), $jsonFlags) ?>,
  dirs: <?= json_encode(array_combine($meaningLangs, array_map(fn($l) => Language::dir($l), $meaningLangs)) ?: new stdClass(), $jsonFlags) ?>,
  native: <?= json_encode($me ? strtolower((string)($me['native_lang'] ?? '')) : '') ?>,
  loggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  sentences: <?= json_encode(array_map(fn($s) => ['text' => $s['text'], 'tokens' => array_map(fn($t) => [$t['t'], $t['r'], $t['m']], $s['tokens'])], $text['sentences']), $jsonFlags) ?>,
  t: <?= json_encode([
    'listen' => __('stories.listen'), 'pause' => __('stories.pause'), 'resume' => __('stories.resume'),
    'meaning' => __('stories.meaning'), 'save' => __('stories.save'), 'saved' => __('stories.saved'),
    'failed' => __('stories.save_failed'),
  ], $jsonFlags) ?>,
};
</script>
<script src="/js/story.js?v=2"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
