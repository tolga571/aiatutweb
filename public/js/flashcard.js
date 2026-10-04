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
      cardAction(editing ? 'update' : 'create', editing ? { id: editing.id, card: data } : { card: data })
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

  rerender();
})();
