<?php
// Homepage: what Jumplearner is, every part of it on a real screen
// recording (public/media/home/*, recorded from the real site), a word-by-
// word line from a real story, the 11 languages, prices, FAQ.
use App\Src\Language;
use App\Src\Stories;

$pageTitle = __('home.page_title');
$isLoggedIn = isset($auth) && $auth->isLoggedIn();
$hasPlan = false;
if ($isLoggedIn) {
    $curr = $auth->currentUser();
    $hasPlan = (($curr['plan_status'] ?? 'inactive') !== 'inactive' || ($curr['has_paid'] ?? 0) == 1);
}
$ctaHref = $isLoggedIn ? '?page=chat' : '?page=register';
$ctaText = $hasPlan ? __('home.continue_learning') : __('home.get_started');
$uiLang = Language::currentLang();

// A real line from a published story, in Spanish (English for a Spanish
// interface), with meanings in the visitor's interface language.
$try = null;
try {
    $stories = new Stories($db);
    $tryStory = $stories->findBySlug('pied-piper-of-hamelin');
    if ($tryStory) {
        $tryLang = $uiLang === 'es' ? 'en' : 'es';
        $tryText = $stories->text((int)$tryStory['id'], $tryLang);
        $sent = $tryText['sentences'][6] ?? $tryText['sentences'][0] ?? null;
        $tryMeaning = $uiLang !== $tryLang ? $uiLang : 'en';
        $titleInUi = $stories->text((int)$tryStory['id'], $uiLang)['title'] ?? $tryText['title'];
        if ($sent) {
            $try = ['slug' => $tryStory['slug'], 'lang' => $tryLang, 'meaning' => $tryMeaning, 'title' => $titleInUi, 'tokens' => $sent['tokens']];
        }
    }
} catch (\Throwable $e) {
    $try = null;
}

$tour = [
    ['key' => 'chat', 'icon' => 'forum', 'name' => __('nav.chat'), 'desc' => __('home.tour_chat'), 'video' => 'chat'],
    ['key' => 'cards', 'icon' => 'style', 'name' => __('home.tour_cards_title'), 'desc' => __('home.tour_cards'), 'video' => 'cards'],
    ['key' => 'words', 'icon' => 'library_books', 'name' => __('home.tour_words_title'), 'desc' => __('home.tour_words'), 'video' => 'words'],
    ['key' => 'lists', 'icon' => 'queue_music', 'name' => __('home.tour_lists_title'), 'desc' => __('home.tour_lists'), 'video' => 'lists'],
    ['key' => 'mistakes', 'icon' => 'edit_note', 'name' => __('nav.mistakes'), 'desc' => __('home.tour_mistakes'), 'video' => 'mistakes'],
    ['key' => 'alphabet', 'icon' => 'abc', 'name' => __('nav.alphabet'), 'desc' => __('home.tour_alphabet'), 'video' => 'alphabet'],
    ['key' => 'stories', 'icon' => 'auto_stories', 'name' => __('stories.heading'), 'desc' => __('home.tour_stories'), 'video' => 'stories'],
    ['key' => 'grammar', 'icon' => 'menu_book', 'name' => __('home.tour_grammar_title'), 'desc' => __('home.tour_grammar'), 'video' => 'grammar'],
    ['key' => 'practice', 'icon' => 'quiz', 'name' => __('home.tour_practice_title'), 'desc' => __('home.tour_practice'), 'video' => 'practice'],
];
$media = function (string $name): ?array {
    $dir = __DIR__ . '/../public/media/home/';
    $hasSilent = is_file($dir . $name . '.mp4');
    $hasLeo = is_file($dir . 'leo/' . $name . '.mp4');
    if (!$hasSilent && !$hasLeo) return null;
    $silentMp4 = $hasSilent ? '/media/home/' . $name . '.mp4?v=6' : '/media/home/leo/' . $name . '.mp4?v=6';
    $leoMp4 = $hasLeo ? '/media/home/leo/' . $name . '.mp4?v=6' : $silentMp4;
    $poster = is_file($dir . $name . '.jpg') ? '/media/home/' . $name . '.jpg?v=6' : '';
    return [
        'mp4' => $silentMp4,
        'leo' => $leoMp4,
        'poster' => $poster,
    ];
};
$plans = [
    ['id' => 'starter', 'features' => 4, 'rec' => false],
    ['id' => 'pro', 'features' => 5, 'rec' => true],
    ['id' => 'premium', 'features' => 4, 'rec' => false],
];
?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>
<link rel="stylesheet" href="/css/home.css?v=6">

<main class="hm">
  <section class="hm-hero" aria-labelledby="hm-hero-title">
    <div class="hm-wrap hm-hero-grid">
      <div class="hm-hero-copy">
        <div class="hm-brand-header">
          <div class="hm-brand-lockup">
            <img src="/brand/emblem.svg?v=3" alt="Jumplearner" width="56" height="62" class="jl-emblem hm-hero-emblem" fetchpriority="high" decoding="async">
            <span class="hm-brand-name">Jumplearner</span>
          </div>
          <span class="hm-badge"><?= __('home.badge') ?></span>
        </div>
        <h1 id="hm-hero-title" class="hm-h1"><?= preg_replace('#<br\s*/?>#i', ' ', __('home.heading')) ?></h1>
        <p class="hm-lead"><?= __('home.subtitle') ?></p>
        <div class="hm-cta-row">
          <a href="<?= $ctaHref ?>" class="hm-btn"><?= $ctaText ?></a>
          <a href="/stories" class="hm-btn-ghost"><?= __('home.read_story') ?></a>
        </div>
        <?php if (!$hasPlan): ?><p class="hm-note"><?= __('home.free_note') ?></p><?php endif; ?>
      </div>
      <figure class="hm-video-box">
        <?php if ($m = $media('chat')): ?>
          <div class="hm-video-wrap" data-video="<?= $m['leo'] ?>" data-title="<?= htmlspecialchars(__('home.hero_video')) ?>" title="<?= htmlspecialchars(__('home.watch_larger')) ?>">
            <video class="hm-video" src="<?= $m['mp4'] ?>" poster="<?= $m['poster'] ?>" muted loop playsinline autoplay preload="metadata" aria-label="<?= htmlspecialchars(__('home.hero_video')) ?>"></video>
            <button type="button" class="hm-expand" data-video="<?= $m['leo'] ?>" data-title="<?= htmlspecialchars(__('home.hero_video')) ?>" aria-label="<?= htmlspecialchars(__('home.watch_larger')) ?>"><span class="material-symbols-outlined" aria-hidden="true">play_circle</span><span><?= __('home.watch_larger') ?></span></button>
          </div>
        <?php endif; ?>
        <figcaption class="hm-caption"><?= __('home.hero_video') ?></figcaption>
      </figure>
    </div>
  </section>

  <section class="hm-band" aria-labelledby="hm-how-title">
    <div class="hm-wrap">
      <h2 id="hm-how-title" class="hm-h2"><?= __('home.how_title') ?></h2>
      <ol class="hm-steps">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <li class="hm-step">
            <span class="hm-step-n"><?= $i ?></span>
            <h3 class="hm-h3"><?= __('home.how_' . $i . '_title') ?></h3>
            <p><?= __('home.how_' . $i . '_desc') ?></p>
          </li>
        <?php endfor; ?>
      </ol>
    </div>
  </section>

  <section id="features" class="hm-section" aria-labelledby="hm-tour-title">
    <div class="hm-wrap">
      <h2 id="hm-tour-title" class="hm-h2"><?= __('home.tour_title') ?></h2>
      <p class="hm-sub"><?= __('home.tour_sub') ?></p>
      <div class="hm-tabs" role="tablist" aria-label="<?= htmlspecialchars(__('home.tour_title')) ?>">
        <?php foreach ($tour as $i => $f): ?>
          <button type="button" role="tab" class="hm-tab<?= $i === 0 ? ' is-on' : '' ?>" id="hm-tab-<?= $f['key'] ?>" aria-controls="hm-pane-<?= $f['key'] ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"><span class="material-symbols-outlined" aria-hidden="true"><?= $f['icon'] ?></span><?= htmlspecialchars($f['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($tour as $i => $f): $m = $media($f['video']); ?>
        <div class="hm-pane" role="tabpanel" id="hm-pane-<?= $f['key'] ?>" aria-labelledby="hm-tab-<?= $f['key'] ?>"<?= $i === 0 ? '' : ' hidden' ?>>
          <div class="hm-pane-media">
            <?php if ($m): ?>
              <div class="hm-video-wrap" data-video="<?= $m['leo'] ?>" data-title="<?= htmlspecialchars($f['name']) ?>" title="<?= htmlspecialchars(__('home.watch_larger')) ?>">
                <video class="hm-video" data-src="<?= $m['mp4'] ?>" poster="<?= $m['poster'] ?>" muted loop playsinline preload="none" aria-label="<?= htmlspecialchars($f['name']) ?>"></video>
                <button type="button" class="hm-expand" data-video="<?= $m['leo'] ?>" data-title="<?= htmlspecialchars($f['name']) ?>" aria-label="<?= htmlspecialchars(__('home.watch_larger')) ?>"><span class="material-symbols-outlined" aria-hidden="true">play_circle</span><span><?= __('home.watch_larger') ?></span></button>
              </div>
            <?php endif; ?>
          </div>
          <div class="hm-pane-copy">
            <h3 class="hm-h3 hm-pane-title"><?= htmlspecialchars($f['name']) ?></h3>
            <p><?= htmlspecialchars($f['desc']) ?></p>
            <?php if ($f['key'] === 'stories'): ?><a href="/stories" class="hm-link"><?= __('home.browse_stories') ?> <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($try): ?>
  <section class="hm-band" aria-labelledby="hm-try-title">
    <div class="hm-wrap">
      <span class="hm-kicker"><?= __('home.try_badge') ?></span>
      <h2 id="hm-try-title" class="hm-h2"><?= __('home.try_title') ?></h2>
      <p class="hm-sub"><?= htmlspecialchars(t('home.try_sub', ['story' => $try['title'], 'lang' => Language::nativeName($try['lang'])])) ?></p>
      <div class="hm-try">
        <p class="hm-try-line" lang="<?= $try['lang'] ?>" dir="<?= Language::dir($try['lang']) ?>">
          <?php $firstWord = null; foreach ($try['tokens'] as $ti => $tk): if (\App\Src\StoryGenerator::isPunct($tk['t'])) { echo '<span class="hm-try-punct">' . htmlspecialchars($tk['t']) . '</span>'; continue; } $firstWord ??= $ti; ?>
            <button type="button" class="hm-try-tok<?= $ti === $firstWord ? ' is-on' : '' ?>" aria-pressed="<?= $ti === $firstWord ? 'true' : 'false' ?>" data-word="<?= htmlspecialchars(Stories::cleanWord($tk['t'])) ?>" data-read="<?= htmlspecialchars($tk['r']) ?>" data-mean="<?= htmlspecialchars($tk['m'][$try['meaning']] ?? '') ?>">
              <span class="hm-try-t"><?= htmlspecialchars($tk['t']) ?></span>
              <span class="hm-try-r"><?= htmlspecialchars($tk['r']) ?></span>
            </button>
          <?php endforeach; $f0 = $try['tokens'][$firstWord ?? 0]; ?>
        </p>
        <div class="hm-try-out" aria-live="polite">
          <span class="hm-try-word" lang="<?= $try['lang'] ?>" id="hm-try-word"><?= htmlspecialchars(Stories::cleanWord($f0['t'])) ?></span>
          <span class="hm-try-read" id="hm-try-read"><?= htmlspecialchars($f0['r']) ?></span>
          <span class="hm-try-mean" lang="<?= $try['meaning'] ?>" dir="<?= Language::dir($try['meaning']) ?>" id="hm-try-mean"><?= htmlspecialchars($f0['m'][$try['meaning']] ?? '') ?></span>
        </div>
      </div>
      <a href="/stories/<?= htmlspecialchars($try['slug']) ?>/<?= $try['lang'] ?>" class="hm-btn-ghost hm-try-more"><?= __('home.read_whole') ?></a>
    </div>
  </section>
  <?php endif; ?>

  <section class="hm-section" aria-labelledby="hm-langs-title">
    <div class="hm-wrap">
      <h2 id="hm-langs-title" class="hm-h2 hm-h2-sm"><?= __('home.langs_title') ?></h2>
      <ul class="hm-langs" role="list">
        <?php foreach (Language::listed('learn') as $l): ?>
          <li lang="<?= $l ?>" dir="<?= Language::dir($l) ?>"><?= htmlspecialchars(Language::nativeName($l)) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section id="pricing" class="hm-band" aria-labelledby="hm-price-title">
    <div class="hm-wrap">
      <div class="hm-price-head">
        <div>
          <h2 id="hm-price-title" class="hm-h2"><?= __('pricing.heading') ?></h2>
          <p class="hm-sub"><?= __('pricing.trial_prompt_desc') ?></p>
        </div>
        <div class="hm-period" role="group" aria-label="<?= htmlspecialchars(__('home.monthly') . ' / ' . __('home.yearly')) ?>">
          <button type="button" class="hm-period-btn is-on" data-period="monthly" aria-pressed="true"><?= __('home.monthly') ?></button>
          <button type="button" class="hm-period-btn" data-period="yearly" aria-pressed="false"><?= __('home.yearly') ?></button>
        </div>
      </div>
      <div class="hm-plans">
        <?php foreach ($plans as $p): $id = $p['id']; ?>
          <article class="hm-plan<?= $p['rec'] ? ' is-rec' : '' ?>">
            <div class="hm-plan-top">
              <h3 class="hm-h3"><?= __('pricing.' . $id . '_title') ?></h3>
              <?php if ($p['rec']): ?><span class="hm-rec"><?= __('pricing.recommended') ?></span><?php endif; ?>
            </div>
            <p class="hm-plan-desc"><?= __('pricing.' . $id . '_desc') ?></p>
            <p class="hm-price" data-period="monthly"><?= __('pricing.' . $id . '_monthly') ?></p>
            <p class="hm-price" data-period="yearly" hidden><?= __('pricing.' . $id . '_yearly') ?></p>
            <ul class="hm-feats">
              <?php for ($i = 1; $i <= $p['features']; $i++): ?>
                <li><span class="material-symbols-outlined" aria-hidden="true">check</span><?= __('pricing.' . $id . '_feature_' . $i) ?></li>
              <?php endfor; ?>
            </ul>
            <a href="?page=pricing" class="<?= $p['rec'] ? 'hm-btn' : 'hm-btn-ghost' ?> hm-plan-btn"><?= __('pricing.' . $id . '_btn') ?></a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="faq" class="hm-section" aria-labelledby="hm-faq-title">
    <div class="hm-wrap hm-narrow">
      <h2 id="hm-faq-title" class="hm-h2"><?= __('faq.heading') ?></h2>
      <div class="hm-faq">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <details class="hm-faq-item"<?= $i === 1 ? ' open' : '' ?>>
            <summary><?= __('faq.q' . $i) ?><span class="material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
            <p><?= __('faq.a' . $i . '_1') ?></p>
          </details>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <section class="hm-band hm-final" aria-labelledby="hm-cta-title">
    <div class="hm-wrap">
      <img src="/brand/emblem.svg?v=3" alt="" width="64" height="70" class="jl-emblem" loading="lazy" decoding="async">
      <h2 id="hm-cta-title" class="hm-h2"><?= __('home.cta_title') ?></h2>
      <p class="hm-sub"><?= __('home.cta_sub') ?></p>
      <a href="<?= $ctaHref ?>" class="hm-btn"><?= $ctaText ?></a>
    </div>
  </section>
</main>
<dialog class="hm-lightbox" id="hm-lightbox" aria-labelledby="hm-lightbox-title">
  <div class="hm-lightbox-head">
    <p id="hm-lightbox-title"></p>
    <button type="button" class="hm-lightbox-close" id="hm-lightbox-close" aria-label="<?= htmlspecialchars(__('home.close_video')) ?>"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
  </div>
  <video id="hm-lightbox-video" controls playsinline preload="none"></video>
  <button type="button" class="hm-lightbox-tip" id="hm-lightbox-tip" aria-label="<?= htmlspecialchars(__('home.rotate_tip')) ?>"><span class="material-symbols-outlined" aria-hidden="true">screen_rotation</span><span><?= __('home.rotate_tip') ?></span></button>
</dialog>
<script src="/js/home.js?v=6" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>

</html>
