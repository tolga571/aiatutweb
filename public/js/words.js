// Word bank (views/words.php): filter the words of the deck language and add
// any of them to the user's cards — they land in the "Saved" list.
(function () {
  var cfg = window.__WB__;
  if (!cfg) return;
  var words = cfg.words || [];
  var L = cfg.t || {};
  var PAGE = 100;
  var shown = PAGE;
  var $ = function (id) { return document.getElementById(id); };
  var q = $('wb-q'), level = $('wb-level'), cat = $('wb-cat'), onlyNew = $('wb-new');
  var rows = $('wb-rows'), more = $('wb-more');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }
  function fmt(s, vars) { return String(s || '').replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? vars[k] : m; }); }

  function matches() {
    var needle = q.value.trim().toLowerCase();
    return words.filter(function (w) {
      if (level.value && w.level !== level.value) return false;
      if (cat.value && w.category !== cat.value) return false;
      if (onlyNew.checked && w.card_id) return false;
      return !needle || w.word.toLowerCase().indexOf(needle) >= 0 || w.meaning.toLowerCase().indexOf(needle) >= 0 ||
        w.pronunciation.toLowerCase().indexOf(needle) >= 0;
    });
  }

  function actionCell(w, i) {
    return w.card_id
      ? '<span class="fc-words-have"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">check</span>' + esc(L.inDeck) + '</span>'
      : '<button type="button" class="fc-words-add" data-i="' + i + '" aria-label="' + esc(L.add + ': ' + w.word) + '">' +
          '<span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>' + esc(L.add) + '</button>';
  }

  function render() {
    var list = matches();
    $('wb-count').textContent = fmt(L.count, { n: list.length });
    $('wb-none').hidden = list.length > 0;
    var html = '';
    list.slice(0, shown).forEach(function (w) {
      var i = words.indexOf(w);
      html += '<tr>' +
        '<td data-label=""><div class="fc-words-word"><span dir="' + (cfg.rtl ? 'rtl' : 'auto') + '">' + esc(w.word) + '</span>' +
          '<button type="button" class="fc-icon-btn" data-speak="' + i + '" aria-label="' + esc(L.listen) + '"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">volume_up</span></button></div>' +
          (w.pronunciation ? '<div class="fc-words-pron">' + esc(w.pronunciation) + '</div>' : '') + '</td>' +
        '<td class="fc-words-meaning">' + esc(w.meaning) + '</td>' +
        '<td class="fc-words-narrow"><span class="level-badge">' + esc(w.level) + '</span></td>' +
        '<td class="fc-words-cat">' + esc((cfg.cats || {})[w.category] || w.category) + '</td>' +
        '<td class="fc-words-narrow" id="wb-act-' + i + '">' + actionCell(w, i) + '</td>' +
      '</tr>';
    });
    rows.innerHTML = html;
    more.hidden = list.length <= shown;
  }

  function toast(msg, bad) {
    var c = $('toast-container');
    if (!c) return;
    var t = document.createElement('div');
    t.className = 'pointer-events-auto flex items-center gap-sm bg-surface-container-high border border-outline-variant/30 px-lg py-md rounded-2xl shadow-2xl';
    t.setAttribute('role', 'status');
    t.innerHTML = '<span class="material-symbols-outlined ' + (bad ? 'text-red-500' : 'text-green-500') + ' text-[18px]" aria-hidden="true">' + (bad ? 'cancel' : 'check_circle') + '</span>' +
      '<span class="text-xs text-on-surface font-medium">' + esc(msg) + '</span>';
    c.appendChild(t);
    setTimeout(function () { t.remove(); }, 2600);
  }

  rows.addEventListener('click', function (e) {
    var sp = e.target.closest('[data-speak]');
    if (sp) {
      var w0 = words[+sp.dataset.speak];
      if (w0 && 'speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(w0.word);
        u.lang = cfg.speechLocale || 'en-US';
        u.rate = 0.85;
        window.speechSynthesis.speak(u);
      }
      return;
    }
    var btn = e.target.closest('.fc-words-add');
    if (!btn) return;
    var w = words[+btn.dataset.i];
    btn.disabled = true;
    fetch('?page=word-add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ word: w.word, csrf_token: cfg.csrf }),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) { btn.disabled = false; toast(res.message || L.err, true); return; }
        w.card_id = res.card_id;
        var cell = $('wb-act-' + btn.dataset.i);
        if (cell) cell.innerHTML = actionCell(w, +btn.dataset.i);
        toast(fmt(L.added, { word: w.word }));
      })
      .catch(function () { btn.disabled = false; toast(L.err, true); });
  });

  [q, level, cat, onlyNew].forEach(function (el) {
    el.addEventListener(el === q ? 'input' : 'change', function () { shown = PAGE; render(); });
  });
  more.addEventListener('click', function () { shown += PAGE; render(); });
  render();
})();
