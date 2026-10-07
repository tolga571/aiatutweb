(function () {
  const cfg = window.__FC_CONFIG__;
  if (!cfg) return;

  let cards = cfg.cards || [];
  const isRtl = !!cfg.isRtl;
  const T = cfg.texts;
  const L = cfg.t || {};
  const view = cfg.view || 'all';

  let earnedXp = 0;

  const cardWrapper = document.getElementById('card-wrapper');
  const emptyDeck = document.getElementById('empty-deck');
  const cardsGrid = document.getElementById('cards-grid');

  // A card is "learned" once the user said they know it or SM-2 mastered it
  // (the server sets learned_at in both cases).
  function isLearned(c) { return !!c.learned_at; }
  function isFav(c) { return c.is_favorite === true || c.is_favorite === 't' || c.is_favorite === 1; }
  function catLabel(c) { return (cfg.catLabels && cfg.catLabels[c]) || c || ''; }

  function setDeckVisibility(hasCards) {
    if (cardWrapper) cardWrapper.classList.toggle('hidden', !hasCards);
    if (emptyDeck) emptyDeck.classList.toggle('hidden', hasCards);
    var controls = document.getElementById('card-controls');
    if (controls) controls.classList.toggle('hidden', !hasCards);
  }

  // ── Server calls ──
  function cardAction(action, payload) {
    return fetch('?page=flashcard-card', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action, csrf_token: cfg.csrf || '' }, payload)),
    })
      .then(function (r) { return r.json(); })
      .catch(function () { return { success: false, message: L.errGeneric }; });
  }

  function bindImportButton() {
    var btn = document.getElementById('btn-import-cards');
    if (!btn) return;
    btn.addEventListener('click', function () {
      btn.disabled = true;
      fetch('?page=flashcard-import', { method: 'POST', body: new URLSearchParams({ csrf_token: cfg.csrf || '' }) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            showToast((T.importSuccess || 'Imported %d new words.').replace('%d', data.imported || 0), 'success');
            window.location.reload();
          } else {
            btn.disabled = false;
          }
        })
        .catch(function () { btn.disabled = false; });
    });
  }

  // Word packs: add one CEFR level, then show that level.
  function bindPackButtons() {
    document.querySelectorAll('.pack-add-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var level = btn.dataset.level;
        btn.disabled = true;
        btn.classList.add('is-busy');
        var body = new URLSearchParams({ csrf_token: cfg.csrf || '', level: level });
        fetch('?page=flashcard-import-pack', { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.success) {
              showToast((T.importSuccess || 'Imported %d new words.').replace('%d', data.imported || 0), 'success');
              setTimeout(function () { window.location.href = '?page=flashcards&level=' + encodeURIComponent(level); }, 500);
            } else {
              btn.disabled = false; btn.classList.remove('is-busy');
              showToast(T.packError || 'Error', 'error');
            }
          })
          .catch(function () {
            btn.disabled = false; btn.classList.remove('is-busy');
            showToast(T.packError || 'Error', 'error');
          });
      });
    });
  }
  bindPackButtons();
  bindImportButton();

  // ── Create / edit dialog ──
  const modal = document.getElementById('fc-modal');
  const form = document.getElementById('fc-form');
  const formError = document.getElementById('fc-form-error');
  const delBtn = document.getElementById('fc-delete');
  const FIELDS = ['word', 'translation', 'pronunciation', 'category', 'example', 'example_translation', 'note'];
  let editingIdx = -1;

  function openEditor(idx) {
    if (!modal || !form) return;
    editingIdx = idx;
    const c = idx >= 0 ? cards[idx] : null;
    form.reset();
    FIELDS.forEach(function (f) {
      var v = c ? (c[f] || '') : '';
      if (f === 'category' && v === 'Custom') v = ''; // shown as the placeholder
      form.elements[f].value = v;
    });
    form.elements.is_favorite.checked = c ? isFav(c) : view === 'favorites';
    form.elements.is_favorite.closest('label').classList.toggle('hidden', !!c);
    document.getElementById('fc-modal-title').textContent = c ? L.edit : L.newCard;
    delBtn.classList.toggle('hidden', !c);
    formError.classList.add('hidden');
    if (typeof modal.showModal === 'function') modal.showModal(); else modal.setAttribute('open', '');
    setTimeout(function () { form.elements.word.focus(); }, 30);
  }
  function closeEditor() {
    if (!modal) return;
    if (typeof modal.close === 'function') modal.close(); else modal.removeAttribute('open');
  }

  document.querySelectorAll('#btn-new-card, [data-new-card]').forEach(function (b) {
    b.addEventListener('click', function () { openEditor(-1); });
  });
  if (modal) {
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closeEditor); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeEditor(); });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const data = {};
      FIELDS.forEach(function (f) { data[f] = form.elements[f].value; });
      if (!data.word.trim()) {
        formError.textContent = L.errGeneric;
        formError.classList.remove('hidden');
        form.elements.word.focus();
        return;
      }
      const editing = editingIdx >= 0 ? cards[editingIdx] : null;
      if (!editing) data.is_favorite = form.elements.is_favorite.checked;
      const submit = form.querySelector('[type=submit]');
      submit.disabled = true;
      cardAction(editing ? 'update' : 'create', editing ? { id: editing.id, card: data } : { card: data, list_id: cfg.listId || 0 })
        .then(function (res) {
          submit.disabled = false;
          if (!res.success) {
            formError.textContent = res.message || L.errGeneric;
            formError.classList.remove('hidden');
            return;
          }
          closeEditor();
          showToast(L.saved, 'success');
          if (editing) {
            cards[editingIdx] = res.card;
          } else {
            cards.unshift(res.card);
            listIdsOf(res.card).forEach(function (id) { bumpList(id, 1); });
          }
          rerender();
        });
    });
  }

  if (delBtn) {
    delBtn.addEventListener('click', function () {
      const c = cards[editingIdx];
      if (!c || !window.confirm(L.deleteConfirm)) return;
      cardAction('delete', { id: c.id }).then(function (res) {
        if (!res.success) { showToast(res.message || L.errGeneric, 'error'); return; }
        closeEditor();
        cards.splice(editingIdx, 1);
        showToast(L.deleted, 'success');
        rerender();
      });
    });
  }

  // ── Card actions (buttons on the card face) ──
  window.fcEdit = function (idx, event) {
    if (event) event.stopPropagation();
    openEditor(idx);
  };

  window.fcToggleFav = function (idx, event) {
    if (event) event.stopPropagation();
    const c = cards[idx];
    const on = !isFav(c);
    c.is_favorite = on; // optimistic
    renderCardItemVisuals(idx);
    cardAction('favorite', { id: c.id, on: on }).then(function (res) {
      if (!res.success) {
        c.is_favorite = !on;
        renderCardItemVisuals(idx);
        showToast(res.message || L.errGeneric, 'error');
        return;
      }
      if (view === 'favorites' && !on) dropCard(idx);
    });
  };

  window.fcToggleLearned = function (idx, event) {
    if (event) event.stopPropagation();
    const c = cards[idx];
    const on = !isLearned(c);
    cardAction('learned', { id: c.id, on: on }).then(function (res) {
      if (!res.success) { showToast(res.message || L.errGeneric, 'error'); return; }
      cards[idx] = res.card;
      showToast(on ? L.markedLearned : L.unmarkedLearned, 'success');
      if (view === 'learned' && !on) { dropCard(idx); return; }
      updateProgress();
      renderCardItemVisuals(idx);
      updateListItem(idx);
    });
  };

  function dropCard(idx) {
    cards.splice(idx, 1);
    rerender();
  }

  // ── Speech ──
  window.speakWord = function (idx, event) {
    if (event) event.stopPropagation();
    const word = cards[idx].word;
    if (!('speechSynthesis' in window)) {
      showToast(T.speechNotSupported || 'Not supported', 'error');
      return;
    }
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(word);
    u.lang = cfg.speechLocale || 'en-US';
    u.rate = 0.85;
    window.speechSynthesis.speak(u);
  };

  window.flipCardGrid = function (idx) {
    if (selecting) { toggleSelect(idx); return; }
    const cardInner = document.getElementById('fc-inner-' + idx);
    if (cardInner) cardInner.classList.toggle('is-flipped');
  };

  // ── SM-2 Review ──
  window.reviewCardGrid = function (idx, quality, event) {
    if (event) event.stopPropagation();
    const card = cards[idx];
    fetch('?page=flashcard-review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ vocab_id: card.id, quality: quality, csrf_token: cfg.csrf || '' }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        card.review_status = data.status;
        if (data.status === 'mastered' && !card.learned_at) card.learned_at = new Date().toISOString();

        if (quality === 0) {
          var cardEl = document.getElementById('grid-card-' + idx);
          if (cardEl) {
            cardEl.classList.remove('fc-shake');
            void cardEl.offsetWidth;
            cardEl.classList.add('fc-shake');
            setTimeout(function () { cardEl.classList.remove('fc-shake'); }, 500);
          }
          showToast(L.noted, 'error');
        }

        earnedXp += data.xp;
        var xpEl = document.getElementById('session-xp');
        if (xpEl) xpEl.textContent = T.xpEarned.replace('%d', earnedXp);
        if (data.xp > 0) showToast('+' + data.xp + ' XP', 'success');

        updateProgress();
        updateListItem(idx);
        renderCardItemVisuals(idx);

        setTimeout(function () {
          const cardInner = document.getElementById('fc-inner-' + idx);
          if (cardInner) cardInner.classList.remove('is-flipped');
        }, 800);
      });
  };

  function updateProgress() {
    const learned = cards.filter(isLearned).length;
    const pct = cards.length ? Math.round((learned / cards.length) * 100) : 0;
    const pctEl = document.getElementById('percent-complete');
    if (pctEl) pctEl.textContent = T.percentLearned.replace('%d', pct).replace('%%', '%');
    const barEl = document.getElementById('progress-bar-fill');
    if (barEl) barEl.style.width = pct + '%';
  }

  function renderCardItemVisuals(idx) {
    const card = cards[idx];
    const learned = isLearned(card);
    const frontEl = document.getElementById('fc-front-' + idx);
    if (frontEl) frontEl.classList.toggle('is-learned', learned);
    const fav = document.getElementById('fc-fav-' + idx);
    if (fav) {
      fav.classList.toggle('is-on', isFav(card));
      fav.setAttribute('aria-pressed', isFav(card) ? 'true' : 'false');
      fav.title = isFav(card) ? L.favRemove : L.favAdd;
      fav.setAttribute('aria-label', fav.title);
      fav.firstElementChild.textContent = isFav(card) ? 'star' : 'star_outline';
    }
    const lb = document.getElementById('fc-learn-' + idx);
    if (lb) {
      lb.classList.toggle('is-on', learned);
      lb.setAttribute('aria-pressed', learned ? 'true' : 'false');
      lb.title = learned ? L.unmarkLearned : L.markLearned;
      lb.setAttribute('aria-label', lb.title);
    }
  }

  function listItemClass(learned) {
    return 'grid-item word-list-link flex items-center justify-between p-sm rounded-lg border transition-colors cursor-pointer' +
      (learned ? ' bg-green-500/10 border-green-500/20' : ' bg-transparent border-transparent hover:bg-surface-container-high/40');
  }
  function badgeClass(learned) {
    return learned
      ? 'status-badge text-[9px] font-bold text-green-600 bg-green-500/10 px-1.5 py-0.5 rounded'
      : 'status-badge text-[9px] font-bold text-outline bg-surface-container-high px-1.5 py-0.5 rounded';
  }

  function updateListItem(idx) {
    const el = document.getElementById('word-item-' + idx);
    if (!el) return;
    const learned = isLearned(cards[idx]);
    el.className = listItemClass(learned);
    const badge = el.querySelector('.status-badge');
    if (badge) {
      badge.textContent = learned ? T.learnedBadge : T.remainingBadge;
      badge.className = badgeClass(learned);
    }
  }

  function renderCards() {
    if (!cardsGrid) return;
    cardsGrid.innerHTML = '';
    cards.forEach(function (c, i) {
      const back = [
        c.example ? `<p class="text-xs text-on-surface text-center mt-2" dir="${isRtl ? 'rtl' : 'auto'}">${escHtml(c.example)}</p>` : '',
        c.example_translation ? `<p class="text-[11px] text-on-surface-variant text-center">${escHtml(c.example_translation)}</p>` : '',
        c.note ? `<p class="fc-note">${escHtml(c.note)}</p>` : '',
      ].join('');
      const html = `
        <div class="fc-scene grid-item" id="grid-card-${i}" data-idx="${i}" data-cat="${escAttr(c.category)}">
          <div id="fc-inner-${i}" class="fc-inner" onclick="flipCardGrid(${i})">

            <!-- FRONT -->
            <div id="fc-front-${i}" class="fc-face fc-front p-4 relative flex flex-col">
              <div class="absolute top-3 left-3 right-24 truncate text-[10px] sm:text-[9px] font-bold uppercase text-teal-500/80 tracking-wider">
                ${escHtml(catLabel(c.category))}
              </div>
              <div class="absolute top-2 right-2 flex items-center">
                <button id="fc-fav-${i}" onclick="fcToggleFav(${i}, event)" class="fc-icon-btn fc-fav" aria-pressed="false"><span class="material-symbols-outlined text-[18px]">star_outline</span></button>
                <button onclick="speakWord(${i}, event)" class="fc-icon-btn text-teal-400" title="${escAttr(L.listen)}" aria-label="${escAttr(L.listen)}">
                  <span class="material-symbols-outlined text-[18px]">volume_up</span>
                </button>
              </div>

              <div class="flex-1 flex flex-col items-center justify-center w-full px-2">
                <h2 class="text-2xl font-extrabold text-on-surface text-center mb-1 break-words max-w-full" dir="${isRtl ? 'rtl' : 'auto'}">${escHtml(c.word)}</h2>
                ${c.pronunciation ? `<p class="text-xs text-on-surface-variant italic font-serif text-center">${escHtml(c.pronunciation)}</p>` : ''}
              </div>

              <div class="fc-actions" onclick="event.stopPropagation()">
                <button id="fc-learn-${i}" onclick="fcToggleLearned(${i}, event)" class="fc-icon-btn fc-learn" aria-pressed="false"><span class="material-symbols-outlined text-[18px]">task_alt</span></button>
                <span class="flex-1 text-[10px] text-outline flex items-center justify-center gap-1 opacity-70 pointer-events-none">
                  <span class="material-symbols-outlined text-[14px]">touch_app</span>${escHtml(L.tapToFlip)}
                </span>
                <button onclick="fcSaveTo(${i}, event)" class="fc-icon-btn" title="${escAttr(L.saveTo)}" aria-label="${escAttr(L.saveTo)}"><span class="material-symbols-outlined text-[18px]">playlist_add</span></button>
                <button onclick="fcEdit(${i}, event)" class="fc-icon-btn" title="${escAttr(L.edit)}" aria-label="${escAttr(L.edit)}"><span class="material-symbols-outlined text-[18px]">edit</span></button>
              </div>
            </div>

            <!-- BACK -->
            <div class="fc-face fc-back p-4 relative flex flex-col">
              <div class="absolute top-3 left-3 text-[10px] sm:text-[9px] font-bold uppercase text-indigo-500/80 tracking-wider">
                ${escHtml(L.translation)}
              </div>

              <div class="flex-1 flex flex-col items-center justify-center w-full px-2 mt-4 overflow-hidden">
                <h2 class="text-xl font-bold text-on-surface text-center break-words max-w-full">${escHtml(c.translation)}</h2>
                ${back}
              </div>

              <div class="w-full grid grid-cols-4 gap-1.5 mt-2" onclick="event.stopPropagation()">
                <button onclick="reviewCardGrid(${i}, 0, event)" class="fc-q text-red-500 bg-red-500/10 hover:bg-red-500/20 border-red-500/20">${escHtml(L.again)}</button>
                <button onclick="reviewCardGrid(${i}, 1, event)" class="fc-q text-orange-500 bg-orange-500/10 hover:bg-orange-500/20 border-orange-500/20">${escHtml(L.hard)}</button>
                <button onclick="reviewCardGrid(${i}, 2, event)" class="fc-q text-green-600 bg-green-500/10 hover:bg-green-500/20 border-green-500/20">${escHtml(L.good)}</button>
                <button onclick="reviewCardGrid(${i}, 3, event)" class="fc-q text-teal-500 bg-teal-500/10 hover:bg-teal-500/20 border-teal-500/20">${escHtml(L.easy)}</button>
              </div>
            </div>

          </div>
        </div>
      `;
      cardsGrid.insertAdjacentHTML('beforeend', html);
      renderCardItemVisuals(i);
      if (selected.has(c.id)) document.getElementById('grid-card-' + i).classList.add('is-selected');
    });
  }

  function initWordList() {
    const container = document.getElementById('words-list');
    if (!container) return;
    container.innerHTML = '';
    cards.forEach(function (c, i) {
      const learned = isLearned(c);
      const a = document.createElement('a');
      a.href = '#';
      a.id = 'word-item-' + i;
      a.className = listItemClass(learned);
      a.onclick = function (e) {
        e.preventDefault();
        const cardEl = document.getElementById('grid-card-' + i);
        if (cardEl) {
          cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          cardEl.style.transition = 'transform 0.3s';
          cardEl.style.transform = 'scale(1.05)';
          setTimeout(() => { cardEl.style.transform = 'scale(1)'; }, 300);
        }
      };
      a.innerHTML =
        '<div class="flex flex-col text-left min-w-0">' +
          '<span class="text-xs font-bold font-sans truncate text-on-surface">' + (isFav(c) ? '★ ' : '') + escHtml(c.word) + '</span>' +
          '<span class="text-[10px] text-on-surface-variant mt-0.5 truncate">' + escHtml(c.translation || '') + '</span>' +
        '</div>' +
        '<span class="' + badgeClass(learned) + '">' + (learned ? T.learnedBadge : T.remainingBadge) + '</span>';
      container.appendChild(a);
    });
  }

  function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[m];
    });
  }
  function escAttr(s) { return escHtml(s); }

  var searchInput = document.getElementById('word-search');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      applyFilters(this.value.toLowerCase(), getActiveCategory());
    });
  }

  function getActiveCategory() {
    const activeChip = document.querySelector('.filter-chip.active-filter');
    return activeChip ? activeChip.dataset.cat : 'all';
  }

  document.querySelectorAll('.filter-chip').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.filter-chip').forEach(function (c) {
        c.className = 'filter-chip bg-surface-container-high border border-outline-variant/30 text-on-surface-variant hover:text-on-surface text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all';
      });
      this.className = 'filter-chip active-filter bg-primary text-on-primary text-[10px] px-2.5 py-1 rounded-full font-semibold transition-all hover:opacity-90';
      applyFilters(searchInput ? searchInput.value.toLowerCase() : '', this.dataset.cat);
    });
  });

  function applyFilters(query, cat) {
    let visibleCount = 0;
    cards.forEach(function (c, i) {
      var matchQ = (c.word || '').toLowerCase().includes(query) || (c.translation || '').toLowerCase().includes(query);
      var matchC = (cat === 'all' || c.category === cat);
      var show = matchQ && matchC;
      var listEl = document.getElementById('word-item-' + i);
      if (listEl) listEl.style.display = show ? 'flex' : 'none';
      var gridEl = document.getElementById('grid-card-' + i);
      if (gridEl) gridEl.style.display = show ? 'block' : 'none';
      if (show) visibleCount++;
    });
    const counterEl = document.getElementById('card-counter');
    if (counterEl) counterEl.textContent = T.cardCounter.replace('%d', visibleCount).replace('%d', cards.length);
  }

  function showToast(msg, type) {
    var c = document.getElementById('toast-container');
    if (!c) return;
    var t = document.createElement('div');
    t.className = 'pointer-events-auto flex items-center gap-sm bg-surface-container-high border border-outline-variant/30 px-lg py-md rounded-2xl shadow-2xl transition-all duration-300 transform translate-y-4 opacity-0';
    t.setAttribute('role', 'status');
    t.innerHTML = (type === 'error'
      ? '<span class="material-symbols-outlined text-red-500 text-[18px]">cancel</span>'
      : '<span class="material-symbols-outlined text-green-500 text-[18px]">check_circle</span>') +
      '<span class="text-xs text-on-surface font-medium">' + escHtml(msg) + '</span>';
    c.appendChild(t);
    requestAnimationFrame(function () { t.classList.remove('translate-y-4', 'opacity-0'); });
    setTimeout(function () {
      t.classList.add('translate-y-4', 'opacity-0');
      setTimeout(function () { t.remove(); }, 300);
    }, 3000);
  }

  function rerender() {
    setDeckVisibility(cards.length > 0);
    initWordList();
    renderCards();
    updateProgress();
    applyFilters(searchInput ? searchInput.value.toLowerCase() : '', getActiveCategory());
  }

  // ── Card lists (playlists) ─────────────────────────────────────────
  // cfg.lists: [{id, label, name, is_default, cards, learned}], default first.
  let lists = cfg.lists || [];
  const selected = new Set(); // card ids
  let selecting = false;

  function fmt(s, vars) {
    return String(s || '').replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? vars[k] : m; });
  }
  function listIdsOf(c) {
    return String(c.list_ids || '').split(',').filter(Boolean).map(Number);
  }
  function setListIds(c, ids) { c.list_ids = ids.join(','); }
  function listById(id) { return lists.find(function (l) { return l.id === id; }); }
  function bumpList(id, by) { var l = listById(id); if (l) { l.cards = Math.max(0, l.cards + by); renderListNav(); } }

  function listCall(action, payload) {
    return fetch('?page=card-list', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ action: action, csrf_token: cfg.csrf || '' }, payload)),
    })
      .then(function (r) { return r.json(); })
      .catch(function () { return { success: false, message: L.errGeneric }; });
  }

  // Sidebar list links and the phone chips, kept in step with `lists`.
  function renderListNav() {
    var nav = document.getElementById('fc-list-nav');
    var icon = function (l) { return l.is_default ? 'bookmark' : 'playlist_play'; };
    if (nav) {
      nav.innerHTML = lists.map(function (l) {
        var on = l.id === cfg.listId;
        return '<a href="?page=flashcards&amp;list=' + l.id + '" class="fc-view' + (on ? ' is-active" aria-current="page"' : '"') + '>' +
          '<span class="material-symbols-outlined text-[18px]">' + icon(l) + '</span>' +
          '<span class="flex-1 truncate">' + escHtml(l.label) + '</span><span class="fc-view-n">' + l.cards + '</span></a>';
      }).join('');
    }
    var chips = document.querySelector('.fc-list-chips');
    if (chips) {
      var add = chips.querySelector('[data-new-list]');
      chips.querySelectorAll('a').forEach(function (a) { a.remove(); });
      lists.forEach(function (l) {
        var on = l.id === cfg.listId;
        var a = document.createElement('a');
        a.href = '?page=flashcards&list=' + l.id;
        a.className = 'fc-chip' + (on ? ' is-active' : '');
        if (on) a.setAttribute('aria-current', 'page');
        a.innerHTML = '<span class="material-symbols-outlined text-[15px]">' + icon(l) + '</span>' + escHtml(l.label) + '<span class="fc-view-n">' + l.cards + '</span>';
        chips.insertBefore(a, add);
      });
    }
  }

  function openDialog(d) { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); }
  function closeDialog(d) { if (typeof d.close === 'function') d.close(); else d.removeAttribute('open'); }
  document.querySelectorAll('#fc-save-modal, #fc-list-modal').forEach(function (d) {
    d.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { closeDialog(d); }); });
    d.addEventListener('click', function (e) { if (e.target === d) closeDialog(d); });
  });

  // Cards leaving the open list disappear from it.
  function dropIds(ids) {
    var gone = new Set(ids);
    cards = cards.filter(function (c) { return !gone.has(c.id); });
    ids.forEach(function (id) { selected.delete(id); });
    rerender();
    updateSelbar();
  }

  // ── "Save to…" ──
  const saveModal = document.getElementById('fc-save-modal');
  let saveFor = null; // {single: card} or {ids: [...]}

  function renderSaveRows() {
    var box = document.getElementById('fc-save-list');
    if (!box || !saveFor) return;
    box.innerHTML = '';
    lists.forEach(function (l) {
      var row = document.createElement('div');
      row.className = 'fc-save-row';
      if (saveFor.single) {
        var inIt = listIdsOf(saveFor.single).indexOf(l.id) >= 0;
        row.innerHTML = '<input type="checkbox" id="fc-save-' + l.id + '" class="form-checkbox h-4 w-4 rounded border-outline/50 bg-surface text-primary focus:ring-0 focus:ring-offset-0 cursor-pointer transition-colors"' + (inIt ? ' checked' : '') + '>' +
          '<label class="fc-save-name" for="fc-save-' + l.id + '">' + escHtml(l.label) + '</label><span class="fc-view-n">' + l.cards + '</span>';
        row.querySelector('input').addEventListener('change', function (e) { toggleCardIn(l, e.target); });
      } else {
        var canMove = cfg.listId && l.id !== cfg.listId;
        row.innerHTML = '<span class="material-symbols-outlined text-[18px] text-outline">' + (l.is_default ? 'bookmark' : 'playlist_play') + '</span>' +
          '<span class="fc-save-name">' + escHtml(l.label) + '</span><span class="fc-view-n">' + l.cards + '</span>' +
          (l.id !== cfg.listId ? '<button type="button" class="fc-ghost-btn" data-act="add">' + escHtml(L.listAdd) + '</button>' : '') +
          (canMove ? '<button type="button" class="fc-new-btn" data-act="move">' + escHtml(L.listMove) + '</button>' : '');
        row.querySelectorAll('[data-act]').forEach(function (b) {
          b.addEventListener('click', function () { putSelected(l, b.dataset.act === 'move'); });
        });
      }
      box.appendChild(row);
    });
  }

  function openSaveTo(target) {
    if (!saveModal) return;
    saveFor = target;
    document.getElementById('fc-save-sub').textContent = target.single
      ? fmt(L.saveToOne, { word: target.single.word })
      : fmt(L.saveToMany, { n: target.ids.length });
    renderSaveRows();
    document.getElementById('fc-save-new').reset();
    openDialog(saveModal);
  }

  window.fcSaveTo = function (idx, event) {
    if (event) event.stopPropagation();
    openSaveTo({ single: cards[idx] });
  };

  function toggleCardIn(list, box) {
    var c = saveFor.single;
    var on = box.checked;
    box.disabled = true;
    listCall(on ? 'add' : 'remove', { id: list.id, cards: [c.id] }).then(function (res) {
      box.disabled = false;
      if (!res.success) { box.checked = !on; showToast(res.message || L.errGeneric, 'error'); return; }
      var ids = listIdsOf(c).filter(function (x) { return x !== list.id; });
      if (on) ids.push(list.id);
      setListIds(c, ids);
      list.cards = res.list ? res.list.cards : list.cards;
      renderListNav();
      renderSaveRows();
      showToast(fmt(on ? L.listAdded : L.listRemoved, { name: list.label }), 'success');
      if (!on && list.id === cfg.listId) { closeDialog(saveModal); dropIds([c.id]); }
    });
  }

  function putSelected(list, move) {
    var ids = saveFor.ids.slice();
    listCall('add', { id: list.id, cards: ids, from: move ? cfg.listId : 0 }).then(function (res) {
      if (!res.success) { showToast(res.message || L.errGeneric, 'error'); return; }
      cards.forEach(function (c) {
        if (ids.indexOf(c.id) < 0) return;
        var cur = listIdsOf(c).filter(function (x) { return x !== list.id && !(move && x === cfg.listId); });
        cur.push(list.id);
        setListIds(c, cur);
      });
      list.cards = res.list ? res.list.cards : list.cards;
      if (move) bumpList(cfg.listId, -ids.length);
      renderListNav();
      closeDialog(saveModal);
      showToast(fmt(move ? L.listMoved : L.listAdded, { name: list.label }), 'success');
      if (move) dropIds(ids);
      exitSelect();
    });
  }

  var saveNew = document.getElementById('fc-save-new');
  if (saveNew) {
    saveNew.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = saveNew.elements.name.value.trim();
      if (!name || !saveFor) return;
      var ids = saveFor.single ? [saveFor.single.id] : saveFor.ids.slice();
      listCall('create', { name: name, cards: ids }).then(function (res) {
        if (!res.success) { showToast(res.message || L.errGeneric, 'error'); return; }
        var l = res.list;
        l.label = l.name;
        lists.push(l);
        cards.forEach(function (c) {
          if (ids.indexOf(c.id) >= 0) setListIds(c, listIdsOf(c).concat([l.id]));
        });
        renderListNav();
        saveNew.reset();
        showToast(fmt(L.listAdded, { name: l.label }), 'success');
        if (saveFor.single) renderSaveRows(); else { closeDialog(saveModal); exitSelect(); }
      });
    });
  }

  // ── New list / rename / delete ──
  const listModal = document.getElementById('fc-list-modal');
  const listForm = document.getElementById('fc-list-form');
  let renaming = false;
  function openListForm(rename) {
    if (!listModal) return;
    renaming = rename;
    listForm.reset();
    document.getElementById('fc-list-modal-title').textContent = rename ? L.listRename : L.listNew;
    if (rename) listForm.elements.name.value = (listById(cfg.listId) || {}).name || '';
    document.getElementById('fc-list-error').classList.add('hidden');
    openDialog(listModal);
    setTimeout(function () { listForm.elements.name.focus(); }, 30);
  }
  document.querySelectorAll('[data-new-list]').forEach(function (b) {
    b.addEventListener('click', function () { openListForm(false); });
  });
  var renameBtn = document.getElementById('btn-list-rename');
  if (renameBtn) renameBtn.addEventListener('click', function () { openListForm(true); });
  if (listForm) {
    listForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = listForm.elements.name.value.trim();
      var err = document.getElementById('fc-list-error');
      listCall(renaming ? 'rename' : 'create', renaming ? { id: cfg.listId, name: name } : { name: name }).then(function (res) {
        if (!res.success) { err.textContent = res.message || L.errGeneric; err.classList.remove('hidden'); return; }
        if (renaming) {
          var l = listById(cfg.listId);
          if (l) { l.name = l.label = res.list.name; }
          var h = document.getElementById('fc-list-name');
          if (h) h.textContent = res.list.name;
          cfg.studyName = res.list.name;
          renderListNav();
          closeDialog(listModal);
          showToast(L.saved, 'success');
        } else {
          window.location.href = '?page=flashcards&list=' + res.list.id;
        }
      });
    });
  }
  var deleteListBtn = document.getElementById('btn-list-delete');
  if (deleteListBtn) {
    deleteListBtn.addEventListener('click', function () {
      if (!window.confirm(L.listDeleteConfirm)) return;
      listCall('delete', { id: cfg.listId }).then(function (res) {
        if (!res.success) { showToast(res.message || L.errGeneric, 'error'); return; }
        window.location.href = '?page=flashcards';
      });
    });
  }

  // ── Selecting several cards ──
  const selbar = document.getElementById('fc-selbar');
  function visibleIdx() {
    var out = [];
    cards.forEach(function (c, i) {
      var el = document.getElementById('grid-card-' + i);
      if (!el || el.style.display !== 'none') out.push(i);
    });
    return out;
  }
  function updateSelbar() {
    if (!selbar) return;
    selbar.hidden = !selecting;
    // Sit above the cookie banner while it's showing (it's fixed to the bottom too).
    var cb = document.getElementById('cookie-banner');
    var lift = cb && !cb.classList.contains('translate-y-full') ? cb.offsetHeight : 0;
    selbar.style.bottom = lift ? (lift + 12) + 'px' : '';
    document.getElementById('fc-selbar-n').textContent = fmt(L.selected, { n: selected.size });
    ['fc-sel-save', 'fc-sel-remove'].forEach(function (id) {
      var b = document.getElementById(id);
      if (b) b.disabled = selected.size === 0;
    });
  }
  function toggleSelect(idx) {
    var c = cards[idx];
    if (selected.has(c.id)) selected.delete(c.id); else selected.add(c.id);
    var el = document.getElementById('grid-card-' + idx);
    if (el) el.classList.toggle('is-selected', selected.has(c.id));
    updateSelbar();
  }
  function enterSelect() {
    selecting = true;
    if (cardsGrid) cardsGrid.classList.add('is-selecting');
    updateSelbar();
  }
  function exitSelect() {
    selecting = false;
    selected.clear();
    if (cardsGrid) {
      cardsGrid.classList.remove('is-selecting');
      cardsGrid.querySelectorAll('.is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
    }
    updateSelbar();
  }
  var selectBtn = document.getElementById('btn-select');
  if (selectBtn) selectBtn.addEventListener('click', function () { if (selecting) exitSelect(); else enterSelect(); });
  var selDone = document.getElementById('fc-sel-done');
  if (selDone) selDone.addEventListener('click', exitSelect);
  var selAll = document.getElementById('fc-sel-all');
  if (selAll) {
    selAll.addEventListener('click', function () {
      visibleIdx().forEach(function (i) {
        selected.add(cards[i].id);
        var el = document.getElementById('grid-card-' + i);
        if (el) el.classList.add('is-selected');
      });
      updateSelbar();
    });
  }
  var selSave = document.getElementById('fc-sel-save');
  if (selSave) selSave.addEventListener('click', function () { if (selected.size) openSaveTo({ ids: Array.from(selected) }); });
  var selRemove = document.getElementById('fc-sel-remove');
  if (selRemove) {
    selRemove.addEventListener('click', function () {
      var ids = Array.from(selected);
      if (!ids.length) return;
      listCall('remove', { id: cfg.listId, cards: ids }).then(function (res) {
        if (!res.success) { showToast(res.message || L.errGeneric, 'error'); return; }
        var l = listById(cfg.listId);
        if (l && res.list) { l.cards = res.list.cards; renderListNav(); }
        showToast(fmt(L.listRemoved, { name: l ? l.label : '' }), 'success');
        dropIds(ids);
        exitSelect();
      });
    });
  }

  // ── Study mode: one card at a time; swipe for the next, tap to flip ──
  // Passing a card without flipping it counts as "knew it" (Good), flipping
  // it as "didn't know" (Again) — once per card per round, through the same
  // SM-2 review as the grid buttons.
  const study = document.getElementById('fc-study');
  const sCard = document.getElementById('fc-study-card');
  const S = { base: [], order: [], pos: 0, flippedNow: false, graded: {}, knew: 0, missed: [], shuffle: false };

  function shuffled(a) {
    a = a.slice();
    for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; }
    return a;
  }

  function startStudy(idxs) {
    if (!study) return;
    if (!idxs.length) { showToast(L.studyEmpty, 'error'); return; }
    S.base = idxs.slice();
    S.order = S.shuffle ? shuffled(idxs) : idxs.slice();
    S.pos = 0; S.graded = {}; S.knew = 0; S.missed = [];
    document.getElementById('fc-study-name').textContent = cfg.studyName || '';
    document.getElementById('fc-study-done').hidden = true;
    document.getElementById('fc-study-play').style.display = '';
    study.hidden = false;
    document.body.style.overflow = 'hidden';
    renderStudy();
    document.getElementById('fc-study-flip').focus();
  }

  function renderStudy() {
    var c = cards[S.order[S.pos]];
    S.flippedNow = false;
    sCard.classList.remove('is-flipped');
    document.getElementById('fc-study-cat').textContent = catLabel(c.category);
    document.getElementById('fc-study-word').textContent = c.word || '';
    document.getElementById('fc-study-pron').textContent = c.pronunciation || '';
    document.getElementById('fc-study-trans').textContent = c.translation || '';
    document.getElementById('fc-study-ex').textContent = c.example || '';
    document.getElementById('fc-study-ex-tr').textContent = c.example_translation || '';
    document.getElementById('fc-study-note').textContent = c.note || '';
    document.getElementById('fc-study-count').textContent = (S.pos + 1) + ' / ' + S.order.length;
    document.getElementById('fc-study-fill').style.width = Math.round((S.pos / S.order.length) * 100) + '%';
    document.getElementById('fc-study-prev').disabled = S.pos === 0;
  }

  function studyFlip() {
    sCard.classList.toggle('is-flipped');
    if (sCard.classList.contains('is-flipped')) S.flippedNow = true;
  }

  function gradeCurrent() {
    var idx = S.order[S.pos];
    var c = cards[idx];
    if (!c || c.id in S.graded) return;
    var knew = !S.flippedNow;
    S.graded[c.id] = knew;
    if (knew) S.knew++; else S.missed.push(idx);
    fetch('?page=flashcard-review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ vocab_id: c.id, quality: knew ? 2 : 0, csrf_token: cfg.csrf || '' }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        c.review_status = data.status;
        if (data.status === 'mastered' && !c.learned_at) c.learned_at = new Date().toISOString();
        earnedXp += data.xp || 0;
        var xpEl = document.getElementById('session-xp');
        if (xpEl) xpEl.textContent = T.xpEarned.replace('%d', earnedXp);
      })
      .catch(function () {});
  }

  function slide(dir, then) {
    // dir -1 = out to the left (next), 1 = out to the right (previous)
    sCard.style.transition = 'transform .18s ease-in, opacity .18s';
    sCard.style.transform = 'translateX(' + (dir * 120) + '%) rotate(' + (dir * 8) + 'deg)';
    sCard.style.opacity = '0';
    setTimeout(function () {
      sCard.style.transition = 'none';
      sCard.style.transform = '';
      sCard.style.opacity = '';
      then();
    }, 180);
  }

  function studyNext() {
    gradeCurrent();
    if (S.pos + 1 >= S.order.length) { studyDone(); return; }
    S.pos++;
    renderStudy();
  }
  function studyPrev() {
    if (S.pos === 0) return;
    S.pos--;
    renderStudy();
  }

  function studyDone() {
    document.getElementById('fc-study-fill').style.width = '100%';
    document.getElementById('fc-study-play').style.display = 'none';
    document.getElementById('fc-study-done').hidden = false;
    document.getElementById('fc-study-done-body').textContent = fmt(L.studyDone, { known: S.knew, total: S.order.length });
    var again = document.getElementById('fc-study-flipped');
    again.hidden = S.missed.length === 0;
    again.textContent = fmt(L.studyFlipped, { n: S.missed.length });
    (S.missed.length ? again : document.getElementById('fc-study-again')).focus();
  }

  function closeStudy() {
    if (!study || study.hidden) return;
    study.hidden = true;
    document.body.style.overflow = '';
    rerender();
    var b = document.getElementById('btn-study');
    if (b) b.focus();
  }

  if (study) {
    document.getElementById('btn-study').addEventListener('click', function () {
      var idxs = selecting && selected.size
        ? cards.map(function (c, i) { return selected.has(c.id) ? i : -1; }).filter(function (i) { return i >= 0; })
        : visibleIdx();
      startStudy(idxs);
    });
    document.getElementById('fc-study-close').addEventListener('click', closeStudy);
    document.getElementById('fc-study-exit').addEventListener('click', closeStudy);
    document.getElementById('fc-study-again').addEventListener('click', function () { startStudy(S.base); });
    document.getElementById('fc-study-flipped').addEventListener('click', function () { startStudy(S.missed.slice()); });
    document.getElementById('fc-study-flip').addEventListener('click', studyFlip);
    document.getElementById('fc-study-next').addEventListener('click', function () { slide(-1, studyNext); });
    document.getElementById('fc-study-prev').addEventListener('click', function () { if (S.pos > 0) slide(1, studyPrev); });
    var shuf = document.getElementById('fc-study-shuffle');
    shuf.addEventListener('click', function () {
      S.shuffle = !S.shuffle;
      shuf.setAttribute('aria-pressed', S.shuffle ? 'true' : 'false');
      shuf.classList.toggle('text-primary', S.shuffle);
      // Re-order only what's still ahead in this round.
      var ahead = S.order.slice(S.pos + 1);
      ahead = S.shuffle ? shuffled(ahead) : ahead.sort(function (a, b) { return S.base.indexOf(a) - S.base.indexOf(b); });
      S.order = S.order.slice(0, S.pos + 1).concat(ahead);
    });
    document.getElementById('fc-study-speak').addEventListener('click', function (e) {
      e.stopPropagation();
      var c = cards[S.order[S.pos]];
      if (!c || !('speechSynthesis' in window)) return;
      window.speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(c.word);
      u.lang = cfg.speechLocale || 'en-US';
      u.rate = 0.85;
      window.speechSynthesis.speak(u);
    });

    // Swipe left = next, right = previous; a tap flips.
    var drag = null;
    sCard.addEventListener('pointerdown', function (e) {
      if (e.target.closest('button')) return;
      drag = { x: e.clientX, y: e.clientY, dx: 0, moved: false, id: e.pointerId };
    });
    sCard.addEventListener('pointermove', function (e) {
      if (!drag || e.pointerId !== drag.id) return;
      drag.dx = e.clientX - drag.x;
      if (!drag.moved && Math.abs(drag.dx) > 10 && Math.abs(drag.dx) > Math.abs(e.clientY - drag.y)) {
        drag.moved = true;
        sCard.classList.add('is-dragging');
        try { sCard.setPointerCapture(e.pointerId); } catch (err) {}
      }
      if (drag.moved) sCard.style.transform = 'translateX(' + drag.dx + 'px) rotate(' + (drag.dx / 25) + 'deg)';
    });
    function endDrag(e) {
      if (!drag || e.pointerId !== drag.id) return;
      var d = drag;
      drag = null;
      sCard.classList.remove('is-dragging');
      if (!d.moved) { if (e.type === 'pointerup') studyFlip(); return; }
      if (d.dx < -80) { slide(-1, studyNext); return; }
      if (d.dx > 80 && S.pos > 0) { slide(1, studyPrev); return; }
      sCard.style.transition = 'transform .2s';
      sCard.style.transform = '';
      setTimeout(function () { sCard.style.transition = ''; }, 200);
    }
    sCard.addEventListener('pointerup', endDrag);
    sCard.addEventListener('pointercancel', endDrag);

    document.addEventListener('keydown', function (e) {
      if (study.hidden || !document.getElementById('fc-study-done').hidden && e.key !== 'Escape') return;
      if (e.key === 'Escape') { e.preventDefault(); closeStudy(); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); slide(-1, studyNext); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); if (S.pos > 0) slide(1, studyPrev); }
      else if (e.key === ' ' || e.key === 'Enter' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        if (e.target.closest && e.target.closest('button') && e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
        e.preventDefault();
        studyFlip();
      }
    });
  }

  rerender();
  updateSelbar();
})();
