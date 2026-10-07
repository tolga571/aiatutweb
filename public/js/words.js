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
      var checkbox = !w.card_id ? '<input type="checkbox" class="wb-bulk-cb w-4 h-4" data-i="'+i+'">' : '';
      html += '<tr class="wb-row" data-i="'+i+'">' +
        '<td>'+checkbox+'</td>' +
        '<td data-label=""><div class="fc-words-word"><span dir="' + (cfg.rtl ? 'rtl' : 'auto') + '">' + esc(w.word) + '</span>' +
          '<button type="button" class="fc-icon-btn" data-speak="' + i + '" aria-label="' + esc(L.listen) + '"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">volume_up</span></button></div>' +
          (w.pronunciation ? '<div class="fc-words-pron">' + esc(w.pronunciation) + '</div>' : '') + 
          '<div class="mt-2"><button type="button" class="text-xs text-primary underline wb-show-context" data-i="'+i+'">'+esc(L.showExample)+'</button></div>' +
          '</td>' +
        '<td class="fc-words-meaning">' + esc(w.meaning) + '</td>' +
        '<td class="fc-words-narrow"><span class="level-badge">' + esc(w.level) + '</span></td>' +
        '<td class="fc-words-cat">' + esc((cfg.cats || {})[w.category] || w.category) + '</td>' +
        '<td class="fc-words-narrow" id="wb-act-' + i + '">' + actionCell(w, i) + '</td>' +
      '</tr><tr id="wb-ctx-row-'+i+'" hidden><td colspan="6" class="bg-surface-dimmer p-4" id="wb-ctx-cell-'+i+'"></td></tr>';
    });
    rows.innerHTML = html;
    more.hidden = list.length <= shown;
    updateBulkToolbar();
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

  var bulkIds = new Set();
  var bulkMaster = $('wb-bulk-master');
  var bulkToolbar = $('wb-bulk-toolbar');
  
  function updateBulkToolbar() {
    var cbs = document.querySelectorAll('.wb-bulk-cb');
    var checked = 0;
    cbs.forEach(function(cb) { if(cb.checked) checked++; });
    if(bulkMaster) bulkMaster.checked = (cbs.length > 0 && checked === cbs.length);
    
    if (bulkIds.size > 0) {
        bulkToolbar.classList.remove('hidden');
        $('wb-bulk-count').textContent = bulkIds.size + ' selected';
    } else {
        bulkToolbar.classList.add('hidden');
    }
  }

  if (bulkMaster) {
      bulkMaster.addEventListener('change', function(e) {
          var cbs = document.querySelectorAll('.wb-bulk-cb');
          cbs.forEach(function(cb) {
              cb.checked = e.target.checked;
              if (e.target.checked) bulkIds.add(cb.dataset.i);
              else bulkIds.delete(cb.dataset.i);
          });
          updateBulkToolbar();
      });
  }

  $('wb-bulk-close')?.addEventListener('click', function() {
      bulkIds.clear();
      document.querySelectorAll('.wb-bulk-cb').forEach(cb => cb.checked = false);
      updateBulkToolbar();
  });

  $('wb-bulk-select-all')?.addEventListener('click', function() {
      document.querySelectorAll('.wb-bulk-cb').forEach(cb => {
          cb.checked = true;
          bulkIds.add(cb.dataset.i);
      });
      updateBulkToolbar();
  });

  $('wb-bulk-add')?.addEventListener('click', function() {
      if (bulkIds.size === 0) return;
      var btn = this;
      btn.disabled = true;
      btn.textContent = 'Adding...';
      var payload = [];
      bulkIds.forEach(function(i) {
          payload.push({word: words[i].word, translation: words[i].meaning});
      });
      
      fetch('?page=cards-bulk', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ words: payload, lists: [], csrf_token: cfg.csrf })
      })
      .then(r => r.json())
      .then(res => {
          btn.disabled = false;
          btn.textContent = L.addSel;
          if (!res.ok) { toast(res.error || L.err, true); return; }
          bulkIds.forEach(function(i) { words[i].card_id = true; });
          bulkIds.clear();
          render();
          toast(fmt(L.bulkAdded, {n: res.added}));
      })
      .catch(() => { btn.disabled = false; btn.textContent = L.addSel; toast(L.err, true); });
  });

  rows.addEventListener('change', function(e) {
      if (e.target.classList.contains('wb-bulk-cb')) {
          if (e.target.checked) bulkIds.add(e.target.dataset.i);
          else bulkIds.delete(e.target.dataset.i);
          updateBulkToolbar();
      }
  });

  // Interactive Popup logic
  function renderContextTokens(tokens, container) {
      var html = '<div class="flex flex-wrap gap-2 text-lg items-center">';
      tokens.forEach(function(t) {
          html += '<div class="group relative cursor-pointer text-center">';
          html += '<div class="text-xs text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity absolute -top-4 left-1/2 -translate-x-1/2 whitespace-nowrap bg-surface px-1 rounded shadow">' + esc(t.pinyin || '') + '</div>';
          html += '<span class="hover:text-primary transition-colors border-b border-transparent hover:border-primary">' + esc(t.token) + '</span>';
          if (t.translation) {
              html += '<div class="text-xs text-on-surface opacity-0 group-hover:opacity-100 transition-opacity absolute top-full left-1/2 -translate-x-1/2 whitespace-nowrap bg-surface-container-high px-2 py-1 rounded shadow-lg z-50">' + esc(t.translation) + '</div>';
          }
          html += '</div>';
      });
      html += '</div>';
      container.innerHTML = html;
  }

  rows.addEventListener('click', function (e) {
    if (e.target.classList.contains('wb-show-context')) {
        var i = e.target.dataset.i;
        var w = words[i];
        var row = $('wb-ctx-row-' + i);
        var cell = $('wb-ctx-cell-' + i);
        if (!row.hidden) {
            row.hidden = true;
            e.target.textContent = L.showExample;
            return;
        }
        row.hidden = false;
        e.target.textContent = L.hideExample;
        if (cell.innerHTML === '') {
            cell.innerHTML = '<div class="flex items-center gap-2 text-sm text-muted-foreground"><span class="material-symbols-outlined animate-spin text-[16px]">sync</span> '+esc(L.loading)+'</div>';
            fetch('?page=words-context&word=' + encodeURIComponent(w.word))
                .then(r => r.json())
                .then(res => {
                    if (!res.ok || !res.data) { cell.innerHTML = '<div class="text-red-500 text-sm">'+esc(L.errLoad)+'</div>'; return; }
                    var d = res.data;
                    var sourceBadge = d.source === 'ai_translation' ? '<span class="text-[10px] bg-primary/20 text-primary px-1 rounded uppercase tracking-wide">'+esc(L.aiGen)+'</span>' : '';
                    var html = '<div class="mb-2">' + sourceBadge + '</div>';
                    if (d.tokens) {
                        html += '<div id="wb-tokens-'+i+'" class="flex flex-wrap gap-x-2 gap-y-3 items-end text-[1.1rem] mt-1"></div>';
                    } else {
                        html += '<div class="text-lg font-medium text-foreground mt-1">' + esc(d.text) + '</div>';
                    }
                    if (d.translation) {
                        html += '<div class="text-sm text-muted-foreground mt-2 italic">' + esc(d.translation) + '</div>';
                    }
                    html += '<div class="mt-4"><button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-primary/10 text-primary text-xs font-semibold tracking-wide hover:bg-primary/20 transition-colors" onclick="var u = new SpeechSynthesisUtterance(\'' + esc(d.text).replace(/'/g, "\'") + '\'); u.lang = \'' + cfg.speechLocale + '\'; window.speechSynthesis.speak(u);" aria-label="Listen"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">volume_up</span> '+esc(L.playSentence)+'</button></div>';
                    cell.innerHTML = html;
                    if (d.tokens) {
                        renderContextTokens(d.tokens, $('wb-tokens-'+i));
                    }
                })
                .catch(() => { cell.innerHTML = '<div class="text-red-500 text-sm">'+esc(L.errLoad)+'</div>'; });
        }
        return;
    }


  
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
