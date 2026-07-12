<?php
$pageTitle = __('alphabet.page_title');
$alphabets = require __DIR__ . '/../../data/alphabets.php';
$isLoggedIn = $auth->isLoggedIn() ?? false;

// Target language: explicit ?target= wins (works for guests too), otherwise the
// logged-in user's own target language, otherwise English.
$accountTargetLang = 'en';
$nativeLang = null;
if ($isLoggedIn) {
    $u = $auth->currentUser();
    $accountTargetLang = $u['target_lang'] ?? 'en';
    $nativeLang = $u['native_lang'] ?? null;
}
$requestedLang = $_GET['target'] ?? null;
$targetLang = ($requestedLang && isset($alphabets[$requestedLang])) ? $requestedLang : $accountTargetLang;
if (!isset($alphabets[$targetLang])) {
    $targetLang = 'en';
}

// Language the example-word gloss should be shown in: the learner's own
// native language when we have it, otherwise English. If the native
// language is the one being studied (e.g. a guest just browsing), no
// gloss is needed at all since the word is already understood.
if ($nativeLang === $targetLang) {
    $glossLang = null;
} else {
    $glossLang = $nativeLang ?: 'en';
}

$alphabet = $alphabets[$targetLang] ?? $alphabets['en'];
$isRtl = ($alphabet['direction'] ?? 'ltr') === 'rtl';
$isPinyin = ($alphabet['type'] ?? '') === 'pinyin';
$isSyllabary = ($alphabet['type'] ?? '') === 'syllabary';
$langNames = ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', 'ar' => 'العربية', 'zh' => '中文', 'ja' => '日本語'];
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/navbar.php';
?>
<link rel="stylesheet" href="/css/alphabet.css?v=3">
<main class="flex-1 overflow-y-auto flex flex-col bg-radial-gradient">
  <div class="py-10 px-6 max-w-5xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-10">
      <!-- Header -->
      <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary text-2xl">abc</span>
            <h1 class="text-2xl md:text-3xl font-bold text-white font-headline"><?= $alphabet['name'] ?></h1>
          </div>
          <p class="text-gray-400 text-sm mt-2"><?= __('alphabet.subtitle_' . $targetLang, __('alphabet.subtitle_default')) ?></p>
        </div>

        <!-- Language selector: works for guests too, independent of account settings -->
        <div class="alphabet-lang-select">
          <label for="alphabet-lang" class="sr-only"><?= __('alphabet.choose_language') ?></label>
          <select id="alphabet-lang" onchange="if(this.value){window.location='?page=alphabet&target='+this.value;}">
            <?php foreach ($langNames as $code => $label): ?>
              <option value="<?= $code ?>" <?= $code === $targetLang ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Progress bar (standard alphabets only) -->
      <?php if (!$isPinyin && !$isSyllabary && isset($alphabet['letters'])): ?>
        <div id="alphabet-progress-wrap" class="alphabet-progress-wrap">
          <?php if ($isLoggedIn): ?>
            <div class="alphabet-progress-bar"><div id="alphabet-progress-fill" class="alphabet-progress-fill" style="width:0%"></div></div>
            <div id="alphabet-progress-label" class="alphabet-progress-label"><?= __('alphabet.progress_loading') ?></div>
          <?php else: ?>
            <a href="?page=login&redirect=alphabet" class="alphabet-progress-cta">
              <span class="material-symbols-outlined text-sm">lock</span>
              <?= __('alphabet.progress_signin_cta') ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Standard Alphabet -->
      <?php if (!$isPinyin && !$isSyllabary && isset($alphabet['letters'])): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
          <?php foreach ($alphabet['letters'] as $letter): ?>
            <div class="alphabet-card">
              <?php if (isset($letter['upper'])): ?>
                <div class="char"><?= htmlspecialchars($letter['upper']) ?><span class="text-gray-500 text-lg ml-1"><?= htmlspecialchars($letter['lower']) ?></span></div>
              <?php else: ?>
                <div class="char char-rtl"><?= htmlspecialchars($letter['char']) ?></div>
              <?php endif; ?>
              <div class="letter-name"><?= htmlspecialchars($letter['name']) ?></div>
              <div class="pron"><?= htmlspecialchars($letter['pron']) ?></div>
              <div class="example"><?= htmlspecialchars($letter['example'] ?? '') ?></div>
              <?php
                $gloss = null;
                if ($glossLang && !empty($letter['example_trans'])) {
                    $gloss = $letter['example_trans'][$glossLang] ?? $letter['example_trans']['en'] ?? null;
                }
              ?>
              <?php if ($gloss): ?>
                <div class="ex-trans"><?= htmlspecialchars($gloss) ?></div>
              <?php endif; ?>
              <div class="card-actions">
                <button type="button" class="speak-btn" data-say="<?= htmlspecialchars($letter['example'] ?? $letter['char']) ?>" title="<?= __('alphabet.listen') ?>" aria-label="<?= __('alphabet.listen') ?>">
                  <span class="material-symbols-outlined text-sm">volume_up</span>
                </button>
                <?php if ($isLoggedIn): $letterKey = htmlspecialchars($letter['upper'] ?? $letter['char']); ?>
                  <button type="button" class="learn-btn" data-key="<?= $letterKey ?>" title="<?= __('alphabet.mark_learned') ?>" aria-label="<?= __('alphabet.mark_learned') ?>" aria-pressed="false">
                    <span class="material-symbols-outlined text-sm">check</span>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($alphabet['has_forms']) && !empty($alphabet['letters'][0]['forms'])): ?>
        <div class="mt-8 border-t border-gray-700/30 pt-8">
          <h2 class="text-sm font-bold text-gray-300 uppercase tracking-wider mb-4"><?= __('alphabet.letter_forms') ?></h2>
          <p class="text-xs text-gray-500 mb-4"><?= __('alphabet.forms_desc') ?></p>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2" dir="rtl">
            <?php foreach ($alphabet['letters'] as $letter): ?>
            <div class="alphabet-card">
              <div class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1"><?= __('alphabet.form_isolated') ?></div>
              <div class="text-3xl font-semibold text-gray-200 leading-tight"><?= htmlspecialchars($letter['forms']['isolated']) ?></div>
              <div class="grid grid-cols-3 gap-1 mt-2">
                <div class="flex flex-col items-center justify-center min-h-[3rem]">
                  <div class="text-2xl font-semibold text-gray-200 leading-tight"><?= htmlspecialchars($letter['forms']['final']) ?></div>
                  <div class="text-xs text-gray-500 mt-0.5"><?= __('alphabet.form_final') ?></div>
                </div>
                <div class="flex flex-col items-center justify-center min-h-[3rem]">
                  <div class="text-2xl font-semibold text-gray-200 leading-tight"><?= htmlspecialchars($letter['forms']['medial']) ?></div>
                  <div class="text-xs text-gray-500 mt-0.5"><?= __('alphabet.form_medial') ?></div>
                </div>
                <div class="flex flex-col items-center justify-center min-h-[3rem]">
                  <div class="text-2xl font-semibold text-gray-200 leading-tight"><?= htmlspecialchars($letter['forms']['initial']) ?></div>
                  <div class="text-xs text-gray-500 mt-0.5"><?= __('alphabet.form_initial') ?></div>
                </div>
              </div>
              <div class="text-[10px] text-gray-500 mt-1"><?= htmlspecialchars($letter['name']) ?></div>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Pinyin -->
      <?php if ($isPinyin): ?>
        <!-- Tones -->
        <div class="mb-8">
          <h2 class="text-sm font-bold text-gray-300 uppercase tracking-wider mb-3"><?= __('alphabet.tones') ?></h2>
          <div class="flex flex-wrap gap-3">
            <?php foreach ($alphabet['tones'] as $tone): ?>
              <div class="tone-badge">
                <span class="tone-mark"><?= htmlspecialchars($tone['mark']) ?>a</span>
                <div>
                  <span class="text-xs font-bold text-gray-200">Tone <?= $tone['num'] ?></span>
                  <span class="text-xs text-gray-400 block"><?= __($tone['desc_key'], $tone['desc']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Initials -->
        <div class="mb-8">
          <h2 class="text-sm font-bold text-gray-300 uppercase tracking-wider mb-3"><?= __('alphabet.initials') ?></h2>
          <div class="initial-grid">
            <?php foreach ($alphabet['initials'] as $init): ?>
              <button type="button" class="initial-card" data-say="<?= htmlspecialchars($init['pinyin']) ?>" aria-label="<?= htmlspecialchars($init['pinyin']) ?>, <?= htmlspecialchars($init['example']) ?>">
                <div class="pinyin"><?= htmlspecialchars($init['pinyin']) ?></div>
                <div class="pron"><?= htmlspecialchars($init['pron']) ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($init['example']) ?></div>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Finals -->
        <div>
          <h2 class="text-sm font-bold text-gray-300 uppercase tracking-wider mb-3"><?= __('alphabet.finals') ?></h2>
          <div class="final-grid">
            <?php foreach ($alphabet['finals'] as $fin): ?>
              <button type="button" class="final-card" data-say="<?= htmlspecialchars($fin['pinyin']) ?>" aria-label="<?= htmlspecialchars($fin['pinyin']) ?>, <?= htmlspecialchars($fin['example']) ?>">
                <div class="pinyin"><?= htmlspecialchars($fin['pinyin']) ?></div>
                <div class="pron"><?= htmlspecialchars($fin['pron']) ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($fin['example']) ?></div>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Japanese Syllabary -->
      <?php if ($isSyllabary): ?>
        <!-- Tabs -->
        <div class="flex gap-2 mb-6 border-b border-gray-700/30 pb-3" role="tablist">
          <?php $first = true; foreach ($alphabet['tabs'] as $key => $tab): ?>
            <button type="button" class="alphabet-tab-btn <?= $first ? 'active' : '' ?>" data-tab="<?= $key ?>" role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>">
              <?= $tab['name'] ?>
            </button>
          <?php $first = false; endforeach; ?>
        </div>

        <!-- Tab Panels -->
        <?php $first = true; foreach ($alphabet['tabs'] as $key => $tab): ?>
          <div class="alphabet-tab-panel <?= $first ? 'active' : '' ?>" id="tab-<?= $key ?>" role="tabpanel">
            <?php foreach ($tab['rows'] as $row): ?>
              <div class="mb-4">
                <div class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-2"><?= htmlspecialchars($row['name']) ?></div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                  <?php foreach ($row['chars'] as $ch): ?>
                    <button type="button" class="gojuon-cell" data-say="<?= htmlspecialchars($ch['romaji']) ?>" aria-label="<?= htmlspecialchars($ch['char']) ?>, <?= htmlspecialchars($ch['romaji']) ?>">
                      <span class="char"><?= htmlspecialchars($ch['char']) ?></span>
                      <span class="romaji"><?= htmlspecialchars($ch['romaji']) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php $first = false; endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>

<div id="tts-toast" class="tts-toast" role="status" aria-live="polite"></div>

<script>
(function() {
  const TARGET_LANG = <?= json_encode($targetLang) ?>;
  const IS_PINYIN = <?= json_encode($isPinyin) ?>;
  const IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;
  const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
  const TOTAL_LETTERS = <?= json_encode(isset($alphabet['letters']) ? count($alphabet['letters']) : 0) ?>;
  const I18N = {
    noVoice: <?= json_encode(__('alphabet.no_voice')) ?>,
    progress: <?= json_encode(__('alphabet.progress_label')) ?>,
  };

  const langToSpeechCode = {
    zh: 'zh-CN', ja: 'ja-JP', ar: 'ar-SA', tr: 'tr-TR', de: 'de-DE', fr: 'fr-FR', es: 'es-ES', en: 'en-US',
  };
  const speechCode = IS_PINYIN ? 'zh-CN' : (langToSpeechCode[TARGET_LANG] || 'en-US');

  let voicesReady = false;
  let toastTimer = null;
  function showToast(msg) {
    const el = document.getElementById('tts-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('visible'), 2600);
  }

  function hasVoiceFor(code) {
    if (!('speechSynthesis' in window)) return false;
    const voices = window.speechSynthesis.getVoices();
    if (!voices.length) return true; // voices not loaded yet; give benefit of the doubt
    const lang = code.split('-')[0];
    return voices.some(v => v.lang && v.lang.toLowerCase().startsWith(lang));
  }

  window.speakAlphabet = function(text) {
    if (!('speechSynthesis' in window)) {
      showToast(I18N.noVoice);
      return;
    }
    if (voicesReady && !hasVoiceFor(speechCode)) {
      showToast(I18N.noVoice);
      return;
    }
    const u = new SpeechSynthesisUtterance(text);
    u.lang = speechCode;
    u.onerror = () => showToast(I18N.noVoice);
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(u);
  };

  if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = () => { voicesReady = true; };
    // Voices may already be loaded synchronously in some browsers.
    if (window.speechSynthesis.getVoices().length) voicesReady = true;
  }

  document.querySelectorAll('[data-say]').forEach(el => {
    el.addEventListener('click', () => window.speakAlphabet(el.dataset.say));
  });

  document.querySelectorAll('.alphabet-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.alphabet-tab-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
      document.querySelectorAll('.alphabet-tab-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      this.setAttribute('aria-selected', 'true');
      document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
  });

  // ── Progress tracking (standard alphabets, logged-in users only) ──
  if (IS_LOGGED_IN && TOTAL_LETTERS > 0) {
    const fill = document.getElementById('alphabet-progress-fill');
    const label = document.getElementById('alphabet-progress-label');
    let learnedSet = new Set();

    function renderProgress() {
      const count = learnedSet.size;
      const pct = TOTAL_LETTERS ? Math.round((count / TOTAL_LETTERS) * 100) : 0;
      if (fill) fill.style.width = pct + '%';
      if (label) label.textContent = I18N.progress.replace('{count}', count).replace('{total}', TOTAL_LETTERS);
    }

    function syncButtons() {
      document.querySelectorAll('.learn-btn').forEach(btn => {
        const learned = learnedSet.has(btn.dataset.key);
        btn.classList.toggle('is-learned', learned);
        btn.setAttribute('aria-pressed', learned ? 'true' : 'false');
      });
    }

    fetch('?page=alphabet-progress&lang=' + encodeURIComponent(TARGET_LANG), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        learnedSet = new Set(data.learned || []);
        renderProgress();
        syncButtons();
      })
      .catch(() => { if (label) label.textContent = ''; });

    document.querySelectorAll('.learn-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const key = this.dataset.key;
        const body = new URLSearchParams({ lang: TARGET_LANG, letter_key: key, csrf_token: CSRF_TOKEN });
        this.disabled = true;
        fetch('?page=alphabet-toggle', { method: 'POST', body })
          .then(r => r.json())
          .then(data => {
            if (data.learned) learnedSet.add(key); else learnedSet.delete(key);
            renderProgress();
            syncButtons();
          })
          .finally(() => { this.disabled = false; });
      });
    });
  }
})();
</script>
</body>
</html>
