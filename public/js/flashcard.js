(function () {
  const cfg = window.__FC_CONFIG__;
  if (!cfg) return;

  let cards = cfg.cards || [];
  const targetLang = cfg.targetLang;
  const isRtl = !!cfg.isRtl;
  const T = cfg.texts;

  let learnedCount = 0;
  let earnedXp = 0;

  const cardStage = document.getElementById('card-stage');
  const cardWrapper = document.getElementById('card-wrapper');
  const emptyDeck = document.getElementById('empty-deck');
  const cardsGrid = document.getElementById('cards-grid');

  function countLearned() {
    learnedCount = 0;
    cards.forEach(function (c) {
      if (c.review_status === 'mastered' || c.review_status === 'review') learnedCount++;
    });
  }

  function setDeckVisibility(hasCards) {
    if (cardWrapper) cardWrapper.classList.toggle('hidden', !hasCards);
    if (emptyDeck) emptyDeck.classList.toggle('hidden', hasCards);
    var controls = document.getElementById('card-controls');
    if (controls) {
      controls.classList.toggle('hidden', !hasCards);
    }
  }

  function bindImportButton() {
    var btn = document.getElementById('btn-import-cards');
    if (!btn) return;
    btn.addEventListener('click', function () {
      btn.disabled = true;
      fetch('?page=flashcard-import', { method: 'POST' })
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

  if (!cards.length) {
    setDeckVisibility(false);
    bindImportButton();
    return;
  }

  countLearned();

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
    const map = { de: 'de-DE', fr: 'fr-FR', es: 'es-ES', zh: 'zh-CN', ja: 'ja-JP', ar: 'ar-SA', tr: 'tr-TR', en: 'en-US' };
    u.lang = map[targetLang] || 'en-US';
    u.rate = 0.85;
    window.speechSynthesis.speak(u);
  };

  window.flipCardGrid = function (idx) {
    const cardInner = document.getElementById('fc-inner-' + idx);
    if (cardInner) {
      cardInner.classList.toggle('is-flipped');
    }
  };

  // ── SM-2 Review ──
  window.reviewCardGrid = function (idx, quality, event) {
    if (event) event.stopPropagation();
    const card = cards[idx];
    fetch('?page=flashcard-review', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ vocab_id: card.id, quality: quality }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        
        const wasLearned = (card.review_status === 'mastered' || card.review_status === 'review');
        card.review_status = data.status;
        const isLearnedNow = (data.status === 'mastered' || data.status === 'review');

        if (quality === 0) {
          var cardEl = document.getElementById('grid-card-' + idx);
          if (cardEl) {
            cardEl.classList.remove('fc-shake');
            void cardEl.offsetWidth;
            cardEl.classList.add('fc-shake');
            setTimeout(function () { cardEl.classList.remove('fc-shake'); }, 500);
          }
          showToast('Noted – come back to this', 'error');
        }
        
        earnedXp += data.xp;
        var xpEl = document.getElementById('session-xp');
        if (xpEl) xpEl.textContent = T.xpEarned.replace('%d', earnedXp);
        
        if (data.xp > 0) showToast('+' + data.xp + ' XP', 'success');

        if (!wasLearned && isLearnedNow) learnedCount++;
        else if (wasLearned && !isLearnedNow) learnedCount--;
        
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
    const pct = cards.length ? Math.round((learnedCount / cards.length) * 100) : 0;
    const pctEl = document.getElementById('percent-complete');
    if (pctEl) pctEl.textContent = T.percentLearned.replace('%d', pct);
    const barEl = document.getElementById('progress-bar-fill');
    if (barEl) barEl.style.width = pct + '%';
  }

  function renderCardItemVisuals(idx) {
    const card = cards[idx];
    const isLearned = (card.review_status === 'mastered' || card.review_status === 'review');
    const frontEl = document.getElementById('fc-front-' + idx);
    if (frontEl) {
      if (isLearned) {
        frontEl.style.borderColor = 'rgba(16, 185, 129, 0.4)'; // green-ish
        frontEl.style.boxShadow = '0 0 0 1px rgba(16, 185, 129, 0.2)';
      } else {
        frontEl.style.borderColor = '';
        frontEl.style.boxShadow = '';
      }
    }
  }

  function updateListItem(idx) {
    const el = document.getElementById('word-item-' + idx);
    if (!el) return;
    const c = cards[idx];
    const learned = c.review_status === 'mastered' || c.review_status === 'review';
    el.className = 'grid-item word-list-link flex items-center justify-between p-sm rounded-lg border transition-colors cursor-pointer' +
      (learned ? ' bg-green-500/10 border-green-500/20' : ' bg-transparent border-transparent hover:bg-surface-container-high/40');
    const badge = el.querySelector('.status-badge');
    if (badge) {
      badge.textContent = learned ? T.learnedBadge : T.remainingBadge;
      badge.className = learned
        ? 'status-badge text-[9px] font-bold text-green-600 bg-green-500/10 px-1.5 py-0.5 rounded'
        : 'status-badge text-[9px] font-bold text-outline bg-surface-container-high px-1.5 py-0.5 rounded';
    }
  }

  function renderCards() {
    if (!cardsGrid) return;
    cardsGrid.innerHTML = '';
    cards.forEach(function (c, i) {
      const html = `
        <div class="fc-scene grid-item" id="grid-card-${i}" data-idx="${i}" data-cat="${escAttr(c.category)}" data-word="${escAttr(c.word.toLowerCase())}" data-trans="${escAttr((c.translation||'').toLowerCase())}">
          <div id="fc-inner-${i}" class="fc-inner" onclick="flipCardGrid(${i})">
            
            <!-- FRONT -->
            <div id="fc-front-${i}" class="fc-face fc-front p-4 relative flex flex-col">
              <div class="absolute top-3 left-3 text-[9px] font-bold uppercase text-teal-500/80 tracking-wider">
                ${escHtml(c.category || T.category_label || 'Word')}
              </div>
              <div class="absolute top-2 right-2">
                 <button onclick="speakWord(${i}, event)" class="w-8 h-8 rounded-full text-teal-400 flex items-center justify-center hover:bg-teal-500/10 transition-colors" title="${escAttr(T.audio_btn || 'Listen')}">
                  <span class="material-symbols-outlined text-[18px]">volume_up</span>
                </button>
              </div>
              
              <div class="flex-1 flex flex-col items-center justify-center w-full px-2">
                <h2 class="text-2xl font-extrabold text-on-surface text-center mb-1" dir="${isRtl ? 'rtl' : 'ltr'}">${escHtml(c.word)}</h2>
                ${c.pronunciation ? `<p class="text-xs text-on-surface-variant italic font-serif text-center">${escHtml(c.pronunciation)}</p>` : ''}
              </div>
              
              <div class="absolute bottom-3 left-0 w-full text-[10px] text-outline flex items-center justify-center gap-1 opacity-70">
                <span class="material-symbols-outlined text-[14px]">touch_app</span>
                Tap to flip
              </div>
            </div>

            <!-- BACK -->
            <div class="fc-face fc-back p-4 relative flex flex-col">
              <div class="absolute top-3 left-3 text-[9px] font-bold uppercase text-indigo-500/80 tracking-wider">
                Translation
              </div>
              
              <div class="flex-1 flex flex-col items-center justify-center w-full px-2 mt-4">
                <h2 class="text-xl font-bold text-on-surface text-center" dir="ltr">${escHtml(c.translation)}</h2>
              </div>
              
              <div class="w-full grid grid-cols-2 gap-1.5 mt-auto" onclick="event.stopPropagation()">
                <button onclick="reviewCardGrid(${i}, 0, event)" class="py-1.5 text-[10px] font-bold rounded bg-red-500/10 hover:bg-red-500/20 text-red-500 transition-colors border border-red-500/20">
                  Again
                </button>
                <button onclick="reviewCardGrid(${i}, 1, event)" class="py-1.5 text-[10px] font-bold rounded bg-orange-500/10 hover:bg-orange-500/20 text-orange-500 transition-colors border border-orange-500/20">
                  Hard
                </button>
                <button onclick="reviewCardGrid(${i}, 2, event)" class="py-1.5 text-[10px] font-bold rounded bg-green-500/10 hover:bg-green-500/20 text-green-600 transition-colors border border-green-500/20">
                  Good
                </button>
                <button onclick="reviewCardGrid(${i}, 3, event)" class="py-1.5 text-[10px] font-bold rounded bg-teal-500/10 hover:bg-teal-500/20 text-teal-500 transition-colors border border-teal-500/20">
                  Easy
                </button>
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
      const learned = c.review_status === 'mastered' || c.review_status === 'review';
      const a = document.createElement('a');
      a.href = '#';
      a.id = 'word-item-' + i;
      a.className = 'grid-item word-list-link flex items-center justify-between p-sm rounded-lg border transition-colors cursor-pointer' +
        (learned ? ' bg-green-500/10 border-green-500/20' : ' bg-transparent border-transparent hover:bg-surface-container-high/40');
      a.onclick = function (e) { 
        e.preventDefault(); 
        const cardEl = document.getElementById('grid-card-' + i);
        if (cardEl) {
          cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          // Highlight briefly
          cardEl.style.transition = 'transform 0.3s';
          cardEl.style.transform = 'scale(1.05)';
          setTimeout(() => { cardEl.style.transform = 'scale(1)'; }, 300);
        }
      };
      a.innerHTML =
        '<div class="flex flex-col text-left min-w-0">' +
          '<span class="text-xs font-bold font-sans truncate text-on-surface">' + escHtml(c.word) + '</span>' +
          '<span class="text-[10px] text-on-surface-variant mt-0.5 truncate">' + escHtml(c.translation || '') + '</span>' +
        '</div>' +
        '<span class="status-badge ' +
          (learned
            ? 'text-[9px] font-bold text-green-600 bg-green-500/10 px-1.5 py-0.5 rounded'
            : 'text-[9px] font-bold text-outline bg-surface-container-high px-1.5 py-0.5 rounded') +
        '">' + (learned ? T.learnedBadge : T.remainingBadge) + '</span>';
      container.appendChild(a);
    });
  }

  function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, function(m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
    });
  }
  function escAttr(s) {
    return escHtml(s);
  }

  var searchInput = document.getElementById('word-search');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase();
      applyFilters(q, getActiveCategory());
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
      var cat = this.dataset.cat;
      var q = searchInput ? searchInput.value.toLowerCase() : '';
      applyFilters(q, cat);
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
    
    // Update counter
    const counterEl = document.getElementById('card-counter');
    if (counterEl) {
      counterEl.textContent = T.cardCounter.replace('%d', visibleCount).replace('%d', cards.length);
    }
  }

  function showToast(msg, type) {
    var c = document.getElementById('toast-container');
    if (!c) return;
    var t = document.createElement('div');
    t.className = 'pointer-events-auto flex items-center gap-sm bg-surface-container-high border border-outline-variant/30 px-lg py-md rounded-2xl shadow-2xl transition-all duration-300 transform translate-y-4 opacity-0';
    t.innerHTML = (type === 'error'
      ? '<span class="material-symbols-outlined text-red-500 text-[18px]">cancel</span>'
      : '<span class="material-symbols-outlined text-green-500 text-[18px]">check_circle</span>') +
      '<span class="text-xs text-on-surface font-medium">' + msg + '</span>';
    c.appendChild(t);
    requestAnimationFrame(function () { t.classList.remove('translate-y-4', 'opacity-0'); });
    setTimeout(function () {
      t.classList.add('translate-y-4', 'opacity-0');
      setTimeout(function () { t.remove(); }, 300);
    }, 3000);
  }

  setDeckVisibility(true);
  initWordList();
  renderCards();
  updateProgress();
})();
