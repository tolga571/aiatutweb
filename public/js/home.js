// Homepage (views/home.php): feature tabs, the story word demo, monthly /
// yearly prices, and recordings that load only when they come into view.
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function playIfVisible(v) {
    if (!v) return;
    if (!v.src && v.dataset.src) v.src = v.dataset.src;
    if (!reduce) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
  }
  // Recordings start when on screen and pause when scrolled away.
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      var v = e.target;
      if (e.isIntersecting && !v.closest('[hidden]')) playIfVisible(v); else if (!v.paused) v.pause();
    });
  }, { threshold: 0.25 }) : null;
  document.querySelectorAll('.hm-video').forEach(function (v) {
    if (reduce) { v.removeAttribute('autoplay'); v.pause(); }
    if (io) io.observe(v); else playIfVisible(v);
  });

  // Larger player with sound: plays the full Leo narrated recording
  var box = document.getElementById('hm-lightbox');
  var big = document.getElementById('hm-lightbox-video');
  var tip = document.getElementById('hm-lightbox-tip');
  var tipTimer = null;

  function requestFs(el) {
    if (!el) return;
    try {
      if (el.requestFullscreen) {
        el.requestFullscreen().catch(function () {});
      } else if (el.webkitRequestFullscreen) {
        el.webkitRequestFullscreen();
      } else if (el.webkitEnterFullscreen) {
        el.webkitEnterFullscreen();
      } else if (el.msRequestFullscreen) {
        el.msRequestFullscreen();
      }
    } catch (e) {}
  }

  function openLarge(src, title) {
    if (!box || !big || !src) return;
    document.querySelectorAll('.hm-video').forEach(function (v) { v.pause(); });
    big.src = src;
    big.muted = false;
    big.volume = 1;
    var titleEl = document.getElementById('hm-lightbox-title');
    if (titleEl) titleEl.textContent = title || '';
    if (typeof box.showModal === 'function') {
      box.showModal();
    } else {
      box.setAttribute('open', '');
    }
    big.currentTime = 0;
    var p = big.play();
    if (p && p.catch) p.catch(function () {});

    if (tip) {
      tip.style.opacity = '1';
      clearTimeout(tipTimer);
      tipTimer = setTimeout(function () {
        if (tip) tip.style.opacity = '0';
      }, 5000);
    }
  }

  function closeLarge() {
    if (!box || !big) return;
    clearTimeout(tipTimer);
    big.pause();
    big.removeAttribute('src');
    big.load();
    if (typeof box.close === 'function') {
      box.close();
    } else {
      box.removeAttribute('open');
    }
    // resume active tab video preview
    var activePane = document.querySelector('.hm-pane:not([hidden])');
    if (activePane) {
      var v = activePane.querySelector('.hm-video');
      if (v) playIfVisible(v);
    }
  }

  if (box && big) {
    box.addEventListener('close', function () {
      clearTimeout(tipTimer);
      big.pause();
      big.removeAttribute('src');
      big.load();
    });
    box.addEventListener('click', function (e) {
      if (e.target === box) closeLarge();
    });
    var closeBtn = document.getElementById('hm-lightbox-close');
    if (closeBtn) closeBtn.addEventListener('click', closeLarge);
    if (tip) {
      tip.addEventListener('click', function (e) {
        e.stopPropagation();
        requestFs(big);
      });
    }
  }

  // Any click on button or preview wrapper opens the narrated video
  document.querySelectorAll('.hm-expand').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      openLarge(b.dataset.video, b.dataset.title);
    });
  });

  document.querySelectorAll('.hm-video-wrap').forEach(function (wrap) {
    wrap.addEventListener('click', function (e) {
      if (e.target.closest('.hm-expand')) return;
      var b = wrap.querySelector('.hm-expand') || wrap;
      if (b && b.dataset.video) openLarge(b.dataset.video, b.dataset.title);
    });
  });

  // Feature tabs
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.hm-tab'));
  function show(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      var pane = document.getElementById(t.getAttribute('aria-controls'));
      if (pane) {
        pane.hidden = !on;
        var v = pane.querySelector('.hm-video');
        if (v) { if (on) playIfVisible(v); else v.pause(); }
      }
    });
  }
  tabs.forEach(function (t, i) {
    t.addEventListener('click', function () { show(t); });
    t.addEventListener('keydown', function (e) {
      var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (document.documentElement.dir === 'rtl') d = -d;
      if (!d) return;
      var n = tabs[(i + d + tabs.length) % tabs.length];
      show(n);
      n.focus();
    });
  });

  // Story word demo
  var toks = document.querySelectorAll('.hm-try-tok');
  toks.forEach(function (b) {
    b.addEventListener('click', function () {
      toks.forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      document.getElementById('hm-try-word').textContent = b.dataset.word;
      document.getElementById('hm-try-read').textContent = b.dataset.read;
      document.getElementById('hm-try-mean').textContent = b.dataset.mean;
    });
  });

  // Monthly / yearly
  var periodBtns = document.querySelectorAll('.hm-period-btn');
  periodBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      var p = b.dataset.period;
      periodBtns.forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      document.querySelectorAll('.hm-price').forEach(function (el) { el.hidden = el.dataset.period !== p; });
    });
  });
})();
