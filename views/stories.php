<?php
// Story list: public. Story language, category and length are plain query
// parameters (/stories?lang=es&cat=bedtime&len=short), so every filtered
// view is a real, crawlable link and works without JavaScript.
require_once __DIR__ . '/partials/flags.php';
use App\Src\Language;
use App\Src\Stories;

$stories = new Stories($db);
$isLoggedIn = $auth->isLoggedIn();
$me = $isLoggedIn ? $auth->currentUser() : null;

$langs = Language::listed('learn');
$lang = strtolower((string)($_GET['lang'] ?? ''));
if (!in_array($lang, $langs, true)) {
    $lang = ($me && in_array($me['target_lang'] ?? '', $langs, true)) ? $me['target_lang'] : 'en';
}
$cats = $stories->categories(Language::currentLang());
$catSlugs = array_column($cats, 'slug');
$cat = (string)($_GET['cat'] ?? '');
$cat = in_array($cat, $catSlugs, true) ? $cat : '';
$len = (string)($_GET['len'] ?? '');
$len = in_array($len, Stories::LENGTHS, true) ? $len : '';

$list = $stories->published($lang, $cat, $len);
$catNames = array_column($cats, 'name', 'slug');
$url = function (array $change) use ($lang, $cat, $len): string {
    $q = array_filter(array_merge(['lang' => $lang, 'cat' => $cat, 'len' => $len], $change), fn($v) => $v !== '');
    return '/stories' . ($q ? '?' . http_build_query($q) : '');
};
$pageTitle = __('stories.page_title');
$pageDescription = __('stories.meta_description');
?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<link rel="stylesheet" href="/css/stories.css?v=1">

<main class="st-page">
  <div class="st-wrap">
    <header class="st-head">
      <h1 class="st-h1"><?= __('stories.heading') ?></h1>
      <p class="st-sub"><?= __('stories.subtitle') ?></p>
    </header>

    <nav class="st-filter" aria-labelledby="st-lang-label">
      <span id="st-lang-label" class="st-label"><?= __('stories.story_language') ?></span>
      <div class="st-chips">
        <?php foreach ($langs as $l): ?>
          <a href="<?= htmlspecialchars($url(['lang' => $l])) ?>" lang="<?= $l ?>" class="st-chip<?= $l === $lang ? ' is-on' : '' ?>"<?= $l === $lang ? ' aria-current="true"' : '' ?>>
            <?= flagImg($l, 'w-5 h-3.5') ?><span><?= htmlspecialchars(Language::nativeName($l)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </nav>

    <div class="st-filter-row">
      <nav class="st-filter" aria-labelledby="st-cat-label">
        <span id="st-cat-label" class="st-label"><?= __('stories.category') ?></span>
        <div class="st-chips">
          <a href="<?= htmlspecialchars($url(['cat' => ''])) ?>" class="st-chip<?= $cat === '' ? ' is-on' : '' ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>><?= __('stories.all') ?></a>
          <?php foreach ($cats as $c): ?>
            <a href="<?= htmlspecialchars($url(['cat' => $c['slug']])) ?>" class="st-chip<?= $c['slug'] === $cat ? ' is-on' : '' ?>"<?= $c['slug'] === $cat ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($c['name']) ?> <span class="st-count">(<?= (int)$c['count'] ?>)</span></a>
          <?php endforeach; ?>
        </div>
      </nav>
      <nav class="st-filter" aria-labelledby="st-len-label">
        <span id="st-len-label" class="st-label"><?= __('stories.length') ?></span>
        <div class="st-chips">
          <a href="<?= htmlspecialchars($url(['len' => ''])) ?>" class="st-chip<?= $len === '' ? ' is-on' : '' ?>"<?= $len === '' ? ' aria-current="true"' : '' ?>><?= __('stories.any') ?></a>
          <?php foreach (Stories::LENGTHS as $l): ?>
            <a href="<?= htmlspecialchars($url(['len' => $l])) ?>" class="st-chip<?= $l === $len ? ' is-on' : '' ?>"<?= $l === $len ? ' aria-current="true"' : '' ?>><?= __('stories.len_' . $l) ?></a>
          <?php endforeach; ?>
        </div>
      </nav>
    </div>

    <?php if (!$list): ?>
      <p class="st-empty"><?= __('stories.empty') ?></p>
    <?php else: ?>
      <ul class="st-grid" role="list">
        <?php foreach ($list as $s): ?>
          <li>
            <a class="st-card" href="/stories/<?= htmlspecialchars($s['slug']) ?>/<?= htmlspecialchars($s['lang']) ?>">
              <span class="st-meta"><span class="st-cat"><?= htmlspecialchars($catNames[$s['category']] ?? $s['category']) ?></span><span aria-hidden="true">·</span><span><?= __('stories.len_' . $s['length']) ?></span></span>
              <span class="st-card-title" lang="<?= htmlspecialchars($s['lang']) ?>" dir="<?= Language::dir($s['lang']) ?>"><?= htmlspecialchars($s['title']) ?></span>
              <span class="st-card-sum" lang="<?= htmlspecialchars($s['lang']) ?>" dir="<?= Language::dir($s['lang']) ?>"><?= htmlspecialchars($s['summary']) ?></span>
              <span class="st-card-go"><?= __('stories.read') ?> <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
