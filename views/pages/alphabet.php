<?php
$pageTitle = __('alphabet.page_title');
$targetLang = 'en';
$isLoggedIn = $auth->isLoggedIn() ?? false;
if ($isLoggedIn) {
    $u = $auth->currentUser();
    $targetLang = $u['target_lang'] ?? 'en';
}
$alphabets = require __DIR__ . '/../../data/alphabets.php';
$alphabet = $alphabets[$targetLang] ?? $alphabets['en'];
$isRtl = ($alphabet['direction'] ?? 'ltr') === 'rtl';
$isPinyin = ($alphabet['type'] ?? '') === 'pinyin';
$isSyllabary = ($alphabet['type'] ?? '') === 'syllabary';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/navbar.php';
?>
<link rel="stylesheet" href="/css/alphabet.css?v=2">
<main class="flex-1 overflow-y-auto flex flex-col bg-radial-gradient">
  <div class="py-10 px-6 max-w-5xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-10">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-primary text-2xl">abc</span>
          <h1 class="text-2xl md:text-3xl font-bold text-white font-headline"><?= $alphabet['name'] ?></h1>
        </div>
        <p class="text-gray-400 text-sm mt-2"><?= __('alphabet.subtitle_' . $targetLang, __('alphabet.subtitle_default')) ?></p>
      </div>

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
              <button type="button" class="speak-btn" onclick="speakAlphabet('<?= htmlspecialchars($letter['example'] ?? $letter['char']) ?>')" title="<?= __('alphabet.listen') ?>">
                <span class="material-symbols-outlined text-sm">volume_up</span>
              </button>
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
              <div class="initial-card" onclick="speakAlphabet('<?= htmlspecialchars($init['pinyin']) ?>')">
                <div class="pinyin"><?= htmlspecialchars($init['pinyin']) ?></div>
                <div class="pron"><?= htmlspecialchars($init['pron']) ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($init['example']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Finals -->
        <div>
          <h2 class="text-sm font-bold text-gray-300 uppercase tracking-wider mb-3"><?= __('alphabet.finals') ?></h2>
          <div class="final-grid">
            <?php foreach ($alphabet['finals'] as $fin): ?>
              <div class="final-card" onclick="speakAlphabet('<?= htmlspecialchars($fin['pinyin']) ?>')">
                <div class="pinyin"><?= htmlspecialchars($fin['pinyin']) ?></div>
                <div class="pron"><?= htmlspecialchars($fin['pron']) ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($fin['example']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Japanese Syllabary -->
      <?php if ($isSyllabary): ?>
        <!-- Tabs -->
        <div class="flex gap-2 mb-6 border-b border-gray-700/30 pb-3">
          <?php $first = true; foreach ($alphabet['tabs'] as $key => $tab): ?>
            <button type="button" class="alphabet-tab-btn <?= $first ? 'active' : '' ?>" data-tab="<?= $key ?>">
              <?= $tab['name'] ?>
            </button>
          <?php $first = false; endforeach; ?>
        </div>

        <!-- Tab Panels -->
        <?php $first = true; foreach ($alphabet['tabs'] as $key => $tab): ?>
          <div class="alphabet-tab-panel <?= $first ? 'active' : '' ?>" id="tab-<?= $key ?>">
            <?php foreach ($tab['rows'] as $row): ?>
              <div class="mb-4">
                <div class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-2"><?= htmlspecialchars($row['name']) ?></div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                  <?php foreach ($row['chars'] as $ch): ?>
                    <div class="gojuon-cell" onclick="speakAlphabet('<?= htmlspecialchars($ch['romaji']) ?>')">
                      <span class="char"><?= htmlspecialchars($ch['char']) ?></span>
                      <span class="romaji"><?= htmlspecialchars($ch['romaji']) ?></span>
                    </div>
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

<script>
function speakAlphabet(text) {
  if ('speechSynthesis' in window) {
    const u = new SpeechSynthesisUtterance(text);
    <?php if ($isPinyin): ?>
    u.lang = 'zh-CN';
    <?php elseif ($targetLang === 'ja'): ?>
    u.lang = 'ja-JP';
    <?php elseif ($targetLang === 'ar'): ?>
    u.lang = 'ar-SA';
    <?php elseif ($targetLang === 'tr'): ?>
    u.lang = 'tr-TR';
    <?php elseif ($targetLang === 'de'): ?>
    u.lang = 'de-DE';
    <?php elseif ($targetLang === 'fr'): ?>
    u.lang = 'fr-FR';
    <?php elseif ($targetLang === 'es'): ?>
    u.lang = 'es-ES';
    <?php else: ?>
    u.lang = 'en-US';
    <?php endif; ?>
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(u);
  }
}

document.querySelectorAll('.alphabet-tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.alphabet-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.alphabet-tab-panel').forEach(p => p.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('tab-' + this.dataset.tab).classList.add('active');
  });
});
</script>
</body>
</html>
