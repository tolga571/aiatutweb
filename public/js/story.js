// Story reader (views/story.php): tabs, meaning language, the word drawer,
// read-aloud with the browser's own voices, and saving a word to the cards.
(function () {
  'use strict';
  var S = window.__STORY__;
  if (!S) return;
  var $ = function (id) { return document.getElementById(id); };
  var meaning = S.meaning;

  // ── Tabs ────────────────────────────────────────────────────
  var tabs = [$('st-tab-words'), $('st-tab-text')];
  function showTab(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      $(t.getAttribute('aria-controls')).hidden = !on;
    });
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { showTab(t); });
    t.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
        showTab(next);
        next.focus();
      }
    });
  });

  // ── Meaning language ────────────────────────────────────────
  var meanBtns = document.querySelectorAll('.st-mean');
  function setMeaning(l) {
    meaning = l;
    meanBtns.forEach(function (b) {
      var on = b.dataset.lang === l;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    document.querySelectorAll('.st-tok').forEach(function (b) {
      var tk = S.sentences[+b.dataset.s].tokens[+b.dataset.t];
      var m = b.querySelector('.st-m');
      m.textContent = (tk[2] && tk[2][l]) || '';
      m.lang = l;
      m.dir = S.dirs[l] || 'ltr';
    });
    try { localStorage.setItem('jl_story_meaning', l); } catch (e) {}
  }
  meanBtns.forEach(function (b) { b.addEventListener('click', function () { setMeaning(b.dataset.lang); }); });
  // A guest's own earlier pick wins over the browser language.
  if (!S.native) {
    try {
      var saved = localStorage.getItem('jl_story_meaning');
      if (saved && saved !== meaning && S.names[saved]) setMeaning(saved);
    } catch (e) {}
  }

  // ── Speech: only the browser's own voices; no voice → no buttons ──
  var synth = window.speechSynthesis;
  var voice = null;
  var prefix = (S.speechLocale || S.lang).toLowerCase().slice(0, 2);
  function findVoice() {
    if (!synth) return null;
    var vs = synth.getVoices() || [];
    var exact = vs.filter(function (v) { return (v.lang || '').toLowerCase().replace('_', '-') === S.speechLocale.toLowerCase(); });
    var near = vs.filter(function (v) { return (v.lang || '').toLowerCase().slice(0, 2) === prefix; });
    return exact[0] || near[0] || null;
  }
  function refreshVoice() {
    voice = findVoice();
    $('st-audio').hidden = !voice;
    $('st-d-listen').hidden = !voice;
  }
  if (synth) {
    refreshVoice();
    if (typeof synth.addEventListener === 'function') synth.addEventListener('voiceschanged', refreshVoice);
    else synth.onvoiceschanged = refreshVoice;
  }
  var rate = 1;
  function speak(text, onEnd) {
    if (!voice) return;
    var u = new SpeechSynthesisUtterance(text);
    u.voice = voice;
    u.lang = voice.lang || S.speechLocale;
    u.rate = rate;
    if (onEnd) u.onend = onEnd;
    synth.speak(u);
  }

  // Whole story, sentence by sentence, with the sentence marked.
  var playing = false, current = -1, runId = 0;
  function mark(i) {
    document.querySelectorAll('.st-sent.is-reading, .st-line.is-reading').forEach(function (el) { el.classList.remove('is-reading'); });
    if (i < 0) return;
    document.querySelectorAll('.st-sent[data-s="' + i + '"], .st-line[data-s="' + i + '"]').forEach(function (el) { el.classList.add('is-reading'); });
    var vis = document.querySelector('.st-panel:not([hidden]) [data-s="' + i + '"]');
    if (vis && vis.scrollIntoView) vis.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  function setPlayUi() {
    var b = $('st-play');
    b.setAttribute('aria-pressed', playing ? 'true' : 'false');
    b.querySelector('.material-symbols-outlined').textContent = playing ? 'pause' : 'play_arrow';
    $('st-play-label').textContent = playing ? S.t.pause : (current > 0 ? S.t.resume : S.t.listen);
  }
  function playFrom(i) {
    if (i >= S.sentences.length) { playing = false; current = -1; mark(-1); setPlayUi(); return; }
    current = i;
    mark(i);
    var id = runId;
    speak(S.sentences[i].text, function () { if (playing && id === runId) playFrom(i + 1); });
  }
  function stop() { runId++; if (synth) synth.cancel(); }
  $('st-play').addEventListener('click', function () {
    if (playing) { playing = false; stop(); setPlayUi(); return; }
    stop();
    playing = true;
    setPlayUi();
    playFrom(current > 0 ? current : 0);
  });
  document.querySelectorAll('.st-rate').forEach(function (b) {
    b.addEventListener('click', function () {
      rate = parseFloat(b.dataset.rate);
      document.querySelectorAll('.st-rate').forEach(function (x) {
        var on = x === b;
        x.classList.toggle('is-on', on);
        x.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      if (playing) { stop(); playing = true; playFrom(current); }
    });
  });
  window.addEventListener('pagehide', stop);

  // ── Word drawer ─────────────────────────────────────────────
  var drawer = $('st-drawer'), open = null, opener = null;
  function clean(t) { return t.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '') || t; }
  function openWord(btn) {
    var si = +btn.dataset.s, ti = +btn.dataset.t;
    var tk = S.sentences[si].tokens[ti];
    open = { s: si, t: ti, word: clean(tk[0]) };
    opener = btn;
    document.querySelectorAll('.st-tok.is-open').forEach(function (b) { b.classList.remove('is-open'); });
    btn.classList.add('is-open');
    $('st-d-word').textContent = open.word;
    $('st-d-read').textContent = tk[1];
    $('st-d-mlabel').textContent = S.t.meaning + ' · ' + (S.names[meaning] || meaning);
    var mean = $('st-d-mean');
    mean.textContent = (tk[2] && tk[2][meaning]) || '';
    mean.lang = meaning;
    mean.dir = S.dirs[meaning] || 'ltr';
    // The learner's own language too, when the drawer shows another one.
    var other = $('st-d-other');
    var o = S.native && S.native !== meaning && S.names[S.native] ? S.native : '';
    other.hidden = !o;
    if (o) {
      other.textContent = '';
      other.appendChild(document.createTextNode(S.names[o] + ': '));
      var sp = document.createElement('span');
      sp.lang = o;
      sp.dir = S.dirs[o] || 'ltr';
      sp.textContent = (tk[2] && tk[2][o]) || '';
      other.appendChild(sp);
    }
    $('st-d-msg').textContent = '';
    var save = $('st-d-save');
    if (save) {
      var done = btn.classList.contains('is-saved');
      save.setAttribute('aria-pressed', done ? 'true' : 'false');
      save.classList.toggle('is-done', done);
      $('st-d-save-label').textContent = done ? S.t.saved : S.t.save;
    }
    if (typeof drawer.showModal === 'function') { if (!drawer.open) drawer.showModal(); } else drawer.setAttribute('open', '');
  }
  function closeDrawer() {
    if (drawer.open && typeof drawer.close === 'function') drawer.close(); else drawer.removeAttribute('open');
  }
  drawer.addEventListener('close', function () {
    document.querySelectorAll('.st-tok.is-open').forEach(function (b) { b.classList.remove('is-open'); });
    if (opener) opener.focus();
  });
  // A click on the backdrop (the dialog itself, outside its box) closes it.
  drawer.addEventListener('click', function (e) {
    if (e.target !== drawer) return;
    var r = drawer.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) closeDrawer();
  });
  $('st-d-close').addEventListener('click', closeDrawer);
  document.querySelector('.st-words').addEventListener('click', function (e) {
    var b = e.target.closest('.st-tok');
    if (b) openWord(b);
  });
  $('st-d-listen').addEventListener('click', function () {
    if (!open) return;
    playing = false; stop(); setPlayUi();
    speak(open.word);
  });

  var saveBtn = $('st-d-save');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      if (!open || saveBtn.classList.contains('is-done')) return;
      var target = open;
      saveBtn.disabled = true;
      fetch('?page=story-word-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ csrf_token: S.csrf, slug: S.slug, lang: S.lang, s: target.s, t: target.t })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d || !d.success) throw new Error((d && d.message) || S.t.failed);
        document.querySelectorAll('.st-tok[data-s="' + target.s + '"][data-t="' + target.t + '"]').forEach(function (b) { b.classList.add('is-saved'); });
        if (open === target) {
          saveBtn.classList.add('is-done');
          saveBtn.setAttribute('aria-pressed', 'true');
          $('st-d-save-label').textContent = S.t.saved;
        }
      }).catch(function (err) {
        $('st-d-msg').textContent = err.message || S.t.failed;
      }).then(function () { saveBtn.disabled = false; });
    });
  }
})();
