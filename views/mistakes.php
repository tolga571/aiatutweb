<?php
require_once __DIR__ . '/partials/flags.php';
$pageTitle = __('mis.page_title');

$targetLang = strtolower($currentUser['target_lang'] ?? 'en');
$targetFlag = flagImg($targetLang, 'w-6 h-4');
$activeCount = count(array_filter($mistakes, fn($m) => !$m['learned']));
$learnedCount = count($mistakes) - $activeCount;
$weekAgo = strtotime('-7 days');
$weekCount = count(array_filter($mistakes, fn($m) => strtotime($m['created_at']) >= $weekAgo));
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<style>
  .mis-tab { padding: .5rem 1rem; border-radius: 9999px; font-size: .8rem; font-weight: 600; color: var(--mis-muted, #c4c6d0); border: 1px solid rgba(142,144,153,.2); }
  .mis-tab[aria-selected="true"] { background: rgba(180,197,255,.15); border-color: rgba(180,197,255,.45); color: #b4c5ff; }
  .mis-wrong { text-decoration: line-through; text-decoration-color: rgba(255,180,171,.7); text-decoration-thickness: 2px; }
  /* Tailwind's display utilities (e.g. .flex) would otherwise beat [hidden]. */
  main [hidden] { display: none !important; }
</style>

<main class="flex-1 overflow-y-auto bg-surface-dim">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-6">

    <header class="flex items-start justify-between gap-3">
      <div>
        <h1 class="font-headline-md text-headline-md text-on-surface mb-1 flex items-center gap-2">
          <span class="material-symbols-outlined text-error">edit_note</span>
          <?= __('mis.heading') ?>
        </h1>
        <p class="text-body-md text-on-surface-variant"><?= __('mis.subtitle') ?></p>
      </div>
      <div class="shrink-0 flex items-center gap-2 rounded-full border border-outline-variant/20 px-3 py-1.5">
        <?= $targetFlag ?>
        <span class="text-xs font-semibold text-on-surface-variant"><?= htmlspecialchars(__('languages.' . $targetLang)) ?></span>
      </div>
    </header>

    <?php if (!$mistakes): ?>
    <div class="text-center bg-surface-container border border-outline-variant/20 rounded-2xl px-6 py-12">
      <div class="mx-auto w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center mb-4">
        <span class="material-symbols-outlined text-primary text-[32px]">spellcheck</span>
      </div>
      <h2 class="text-lg font-bold text-on-surface mb-2"><?= __('mis.empty_title') ?></h2>
      <p class="text-sm text-on-surface-variant mb-6 max-w-md mx-auto"><?= __('mis.empty_body') ?></p>
      <a href="?page=chat" class="inline-flex items-center gap-2 bg-primary text-on-primary text-sm font-semibold px-6 py-3 rounded-xl hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-[18px]">forum</span><?= __('mis.go_chat') ?>
      </a>
    </div>
    <?php else: ?>

    <div class="grid grid-cols-3 gap-3">
      <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-4">
        <div class="text-headline-sm font-bold text-on-surface" id="stat-active"><?= $activeCount ?></div>
        <div class="text-xs text-on-surface-variant"><?= __('mis.stat_active') ?></div>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-4">
        <div class="text-headline-sm font-bold text-teal-400" id="stat-learned"><?= $learnedCount ?></div>
        <div class="text-xs text-on-surface-variant"><?= __('mis.stat_learned') ?></div>
      </div>
      <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-4">
        <div class="text-headline-sm font-bold text-on-surface"><?= $weekCount ?></div>
        <div class="text-xs text-on-surface-variant"><?= __('mis.stat_week') ?></div>
      </div>
    </div>

    <!-- Start practice -->
    <section id="mis-start" class="bg-error/10 border border-error/25 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= __('mis.practice_title') ?></h2>
        <p class="text-body-md text-on-surface-variant" id="mis-start-desc"></p>
      </div>
      <button type="button" id="mis-start-btn" class="shrink-0 inline-flex items-center justify-center gap-2 bg-primary text-on-primary text-sm font-bold px-5 py-3 rounded-xl hover:opacity-90 transition disabled:opacity-40 disabled:cursor-not-allowed">
        <span class="material-symbols-outlined text-[18px]">play_arrow</span><?= __('mis.start') ?>
      </button>
    </section>

    <!-- Practice session -->
    <section id="mis-practice" class="mis-panel bg-surface-container border border-outline-variant/20 rounded-2xl p-5 sm:p-6" hidden>
      <div class="flex items-center justify-between gap-3 mb-4">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-outline" id="mis-progress"></span>
        <button type="button" id="mis-quit" class="text-on-surface-variant hover:text-on-surface p-1 rounded-full" aria-label="<?= __('mis.back_to_list') ?>">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
      </div>
      <div class="w-full bg-surface-container-highest rounded-full h-1.5 overflow-hidden mb-6">
        <div id="mis-bar" class="bg-gradient-to-r from-teal-400 to-indigo-500 h-1.5 rounded-full transition-all duration-500" style="width:0%"></div>
      </div>

      <div id="mis-q">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-error mb-1"><?= __('mis.wrong_label') ?></p>
        <p class="text-xl sm:text-2xl font-bold text-on-surface mis-wrong break-words" id="mis-q-original" dir="auto"></p>
        <p class="text-sm text-on-surface-variant mt-3 break-words" id="mis-q-sentence-wrap"><span class="text-outline"><?= __('mis.your_sentence') ?></span> <span id="mis-q-sentence" dir="auto"></span></p>

        <form id="mis-form" class="mt-5 flex flex-col sm:flex-row gap-2" autocomplete="off">
          <input type="text" id="mis-input" dir="auto" autocapitalize="off" spellcheck="false"
            class="flex-1 min-w-0 bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-base text-on-surface placeholder-outline focus:outline-none focus:border-primary/60"
            placeholder="<?= htmlspecialchars(__('mis.type_hint')) ?>">
          <div class="flex gap-2">
            <button type="submit" class="flex-1 sm:flex-none bg-primary text-on-primary text-sm font-bold px-5 py-3 rounded-xl hover:opacity-90"><?= __('mis.check') ?></button>
            <button type="button" id="mis-reveal" class="flex-1 sm:flex-none border border-outline-variant/30 text-on-surface-variant hover:text-on-surface text-sm font-semibold px-4 py-3 rounded-xl"><?= __('mis.show_answer') ?></button>
          </div>
        </form>
      </div>

      <div id="mis-a" hidden class="mt-6 border-t border-outline-variant/15 pt-5">
        <p id="mis-typed" class="text-sm font-semibold mb-3" hidden></p>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-400 mb-1"><?= __('mis.correct_label') ?></p>
        <div class="flex items-start gap-2">
          <p class="text-xl sm:text-2xl font-bold text-teal-300 break-words" id="mis-a-corrected" dir="auto"></p>
          <button type="button" id="mis-listen" class="shrink-0 text-on-surface-variant hover:text-primary p-1 rounded-full" aria-label="<?= __('mis.listen') ?>">
            <span class="material-symbols-outlined text-[22px]">volume_up</span>
          </button>
        </div>
        <p class="text-sm text-outline mt-1" id="mis-a-pron" dir="auto"></p>
        <div class="mt-4 bg-surface-container-high/60 rounded-xl p-4" id="mis-a-rule-wrap">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-primary mb-1"><?= __('mis.rule_label') ?></p>
          <p class="text-sm text-on-surface-variant leading-relaxed" id="mis-a-rule"></p>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-2">
          <button type="button" data-grade="again" class="mis-grade border border-error/40 text-error hover:bg-error/10 text-sm font-bold px-4 py-3 rounded-xl"><?= __('mis.again') ?></button>
          <button type="button" data-grade="known" class="mis-grade bg-teal-500/90 text-white hover:bg-teal-500 text-sm font-bold px-4 py-3 rounded-xl"><?= __('mis.known') ?></button>
        </div>
      </div>

      <div id="mis-done" hidden class="text-center py-6">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-teal-500/15 border border-teal-400/30 flex items-center justify-center mb-4">
          <span class="material-symbols-outlined text-teal-400 text-[32px]">task_alt</span>
        </div>
        <h3 class="text-lg font-bold text-on-surface mb-1"><?= __('mis.done_title') ?></h3>
        <p class="text-sm text-on-surface-variant mb-5" id="mis-done-body"></p>
        <button type="button" id="mis-done-btn" class="bg-primary text-on-primary text-sm font-bold px-6 py-3 rounded-xl hover:opacity-90"><?= __('mis.back_to_list') ?></button>
      </div>
    </section>

    <!-- List -->
    <section id="mis-list-wrap">
      <div class="flex gap-2 mb-4" role="tablist">
        <button type="button" class="mis-tab" role="tab" data-tab="active" aria-selected="true"><?= __('mis.tab_active') ?> <span id="tab-active-n"></span></button>
        <button type="button" class="mis-tab" role="tab" data-tab="learned" aria-selected="false"><?= __('mis.tab_learned') ?> <span id="tab-learned-n"></span></button>
      </div>
      <p id="mis-list-empty" class="text-sm text-on-surface-variant text-center py-8" hidden></p>
      <div id="mis-list" class="space-y-3"></div>
    </section>
    <?php endif; ?>
  </div>
</main>

<div id="toast-container" class="fixed bottom-lg right-lg flex flex-col gap-sm z-50 pointer-events-none"></div>

<?php if ($mistakes): ?>
<script>
(function () {
  var mistakes = <?= json_encode($mistakes, $jsonFlags) ?>;
  var T = <?= json_encode([
      'practiceDesc' => __('mis.practice_desc'),
      'practiceNone' => __('mis.practice_none'),
      'progress' => __('mis.progress'),
      'doneBody' => __('mis.done_body'),
      'typedRight' => __('mis.typed_right'),
      'typedWrong' => __('mis.typed_wrong'),
      'markLearned' => __('mis.mark_learned'),
      'unlearn' => __('mis.unlearn'),
      'practiced' => __('mis.practiced'),
      'emptyActive' => __('mis.empty_active'),
      'emptyLearned' => __('mis.empty_learned'),
      'error' => __('mis.error'),
  ], $jsonFlags) ?>;
  var csrf = <?= json_encode(csrf_token(), $jsonFlags) ?>;
  var speechLang = <?= json_encode(\App\Src\Language::speechLocale($targetLang)) ?>;
  var SESSION_SIZE = 10;

  var $ = function (id) { return document.getElementById(id); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return s.replace(/%d/g, function () { return a.shift(); }); };
  mistakes.forEach(function (m) { m.learned = m.learned === true || m.learned === 't' || m.learned === 1; });

  // Rules use **word (pronunciation)** for emphasis, like the chat does.
  function renderRich(el, text) {
    el.textContent = '';
    String(text || '').split(/\*\*(.+?)\*\*/g).forEach(function (part, i) {
      if (!part) return;
      if (i % 2) { var b = document.createElement('strong'); b.className = 'text-on-surface'; b.textContent = part; el.appendChild(b); }
      else el.appendChild(document.createTextNode(part));
    });
  }

  function normalize(s) {
    return String(s || '').normalize('NFC').toLowerCase()
      .replace(/[.,!?;:¡¿。、！？，．"'«»“”‘’`´()\[\]]/g, '')
      .replace(/\s+/g, ' ').trim();
  }

  function speak(text) {
    if (!('speechSynthesis' in window) || !text) return;
    window.speechSynthesis.cancel();
    var u = new SpeechSynthesisUtterance(text);
    u.lang = speechLang;
    window.speechSynthesis.speak(u);
  }

  function toast(msg) {
    var box = $('toast-container'); if (!box) return;
    var t = document.createElement('div');
    t.className = 'bg-error text-on-error text-sm font-semibold px-4 py-3 rounded-xl shadow-lg pointer-events-auto';
    t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }

  function send(m, action) {
    var body = new URLSearchParams({ csrf_token: csrf, id: m.id, action: action });
    return fetch('?page=mistake-review', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) throw new Error(res.error || 'failed');
        m.practice_count = +res.mistake.practice_count;
        m.correct_count = +res.mistake.correct_count;
        m.learned = res.mistake.learned === true || res.mistake.learned === 't' || res.mistake.learned === 1;
        return m;
      })
      .catch(function () { toast(T.error); throw new Error('failed'); });
  }

  // ── Stats + start card ────────────────────────────────────────────
  function activeList() { return mistakes.filter(function (m) { return !m.learned; }); }
  function refreshCounts() {
    var a = activeList().length, l = mistakes.length - a;
    $('stat-active').textContent = a; $('stat-learned').textContent = l;
    $('tab-active-n').textContent = '(' + a + ')'; $('tab-learned-n').textContent = '(' + l + ')';
    $('mis-start-desc').textContent = a ? fmt(T.practiceDesc, a) : T.practiceNone;
    $('mis-start-btn').disabled = a === 0;
  }

  // ── List ──────────────────────────────────────────────────────────
  var tab = 'active';
  function renderList() {
    var list = $('mis-list'); list.textContent = '';
    var items = mistakes.filter(function (m) { return tab === 'active' ? !m.learned : m.learned; });
    $('mis-list-empty').hidden = items.length > 0;
    $('mis-list-empty').textContent = tab === 'active' ? T.emptyActive : T.emptyLearned;
    items.forEach(function (m) {
      var card = document.createElement('article');
      card.className = 'mis-card bg-surface-container border border-outline-variant/20 rounded-2xl p-4';

      var row = document.createElement('div');
      row.className = 'flex flex-wrap items-baseline gap-x-2 gap-y-1';
      var o = document.createElement('span'); o.className = 'text-base text-error/90 mis-wrong break-words'; o.dir = 'auto'; o.textContent = m.original;
      var arrow = document.createElement('span'); arrow.className = 'material-symbols-outlined text-[16px] text-outline self-center'; arrow.textContent = 'arrow_forward';
      var c = document.createElement('span'); c.className = 'text-base font-bold text-teal-300 break-words'; c.dir = 'auto'; c.textContent = m.corrected;
      row.append(o, arrow, c);
      card.appendChild(row);

      if (m.rule) { var r = document.createElement('p'); r.className = 'text-sm text-on-surface-variant mt-2 leading-relaxed'; renderRich(r, m.rule); card.appendChild(r); }
      if (m.sentence) { var s = document.createElement('p'); s.className = 'text-xs text-outline mt-2 break-words'; s.dir = 'auto'; s.textContent = '“' + m.sentence + '”'; card.appendChild(s); }

      var foot = document.createElement('div');
      foot.className = 'flex items-center justify-between gap-2 mt-3';
      var meta = document.createElement('span'); meta.className = 'text-[11px] text-outline';
      meta.textContent = new Date(String(m.created_at).replace(' ', 'T')).toLocaleDateString(document.documentElement.lang || undefined) +
        (m.practice_count > 0 ? ' · ' + fmt(T.practiced, m.practice_count) : '');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-full border ' +
        (m.learned ? 'border-outline-variant/30 text-on-surface-variant hover:text-on-surface' : 'border-teal-400/40 text-teal-300 hover:bg-teal-400/10');
      var ic = document.createElement('span'); ic.className = 'material-symbols-outlined text-[16px]'; ic.textContent = m.learned ? 'undo' : 'check';
      btn.append(ic, document.createTextNode(m.learned ? T.unlearn : T.markLearned));
      btn.addEventListener('click', function () {
        btn.disabled = true;
        send(m, m.learned ? 'unlearn' : 'learned').then(function () { refreshCounts(); renderList(); }, function () { btn.disabled = false; });
      });
      foot.append(meta, btn);
      card.appendChild(foot);
      list.appendChild(card);
    });
  }
  document.querySelectorAll('.mis-tab').forEach(function (b) {
    b.addEventListener('click', function () {
      tab = b.dataset.tab;
      document.querySelectorAll('.mis-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
      renderList();
    });
  });

  // ── Practice session ──────────────────────────────────────────────
  var queue = [], idx = 0, knownInSession = 0, current = null;

  function showPanel(practice) {
    $('mis-practice').hidden = !practice;
    $('mis-start').hidden = practice;
    $('mis-list-wrap').hidden = practice;
  }

  function start() {
    // Least-practiced first, then newest — so fresh mistakes come up soon
    // and one that keeps going wrong doesn't get buried.
    queue = activeList().slice().sort(function (a, b) {
      return (a.correct_count - b.correct_count) || (String(b.created_at) < String(a.created_at) ? -1 : 1);
    }).slice(0, SESSION_SIZE);
    if (!queue.length) return;
    idx = 0; knownInSession = 0;
    showPanel(true);
    $('mis-done').hidden = true;
    ask();
  }

  function ask() {
    current = queue[idx];
    $('mis-progress').textContent = fmt(T.progress, idx + 1, queue.length);
    $('mis-bar').style.width = Math.round(idx / queue.length * 100) + '%';
    $('mis-q').hidden = false; $('mis-a').hidden = true;
    $('mis-q-original').textContent = current.original;
    $('mis-q-sentence').textContent = current.sentence || '';
    $('mis-q-sentence-wrap').hidden = !current.sentence;
    $('mis-input').value = '';
    $('mis-input').disabled = false;
    $('mis-input').focus();
  }

  function reveal(typed) {
    $('mis-input').disabled = true;
    var t = $('mis-typed');
    if (typed) {
      var right = normalize(typed) === normalize(current.corrected);
      t.hidden = false;
      t.className = 'text-sm font-semibold mb-3 ' + (right ? 'text-teal-300' : 'text-error');
      t.textContent = right ? T.typedRight : T.typedWrong;
    } else {
      t.hidden = true;
    }
    $('mis-a-corrected').textContent = current.corrected;
    $('mis-a-pron').textContent = current.pronunciation || '';
    $('mis-a-pron').hidden = !current.pronunciation;
    renderRich($('mis-a-rule'), current.rule);
    $('mis-a-rule-wrap').hidden = !current.rule;
    $('mis-a').hidden = false;
    var focusBtn = document.querySelector('.mis-grade[data-grade="' + (typed && normalize(typed) === normalize(current.corrected) ? 'known' : 'again') + '"]');
    if (focusBtn) focusBtn.focus();
  }

  function finish() {
    $('mis-bar').style.width = '100%';
    $('mis-q').hidden = true; $('mis-a').hidden = true;
    $('mis-done').hidden = false;
    $('mis-done-body').textContent = fmt(T.doneBody, knownInSession, queue.length);
    $('mis-progress').textContent = fmt(T.progress, queue.length, queue.length);
  }

  function backToList() {
    showPanel(false);
    refreshCounts(); renderList();
  }

  $('mis-start-btn').addEventListener('click', start);
  $('mis-quit').addEventListener('click', backToList);
  $('mis-done-btn').addEventListener('click', backToList);
  $('mis-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if ($('mis-a').hidden) reveal($('mis-input').value.trim());
  });
  $('mis-reveal').addEventListener('click', function () { if ($('mis-a').hidden) reveal(''); });
  $('mis-listen').addEventListener('click', function () { speak(current && current.corrected); });
  document.querySelectorAll('.mis-grade').forEach(function (b) {
    b.addEventListener('click', function () {
      var grade = b.dataset.grade;
      document.querySelectorAll('.mis-grade').forEach(function (x) { x.disabled = true; });
      send(current, grade).then(function () {
        if (grade === 'known') knownInSession++;
        idx++;
        if (idx < queue.length) ask(); else finish();
      }, function () {}).finally(function () {
        document.querySelectorAll('.mis-grade').forEach(function (x) { x.disabled = false; });
      });
    });
  });

  refreshCounts();
  renderList();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
