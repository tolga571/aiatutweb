/* AiTut motion layer — runtime.
   Loaded on every non-admin page (see views/partials/head.php).
   Owns: motion tokens (read from CSS), Lenis smooth scroll on marketing
   pages, and the readiness/failsafe handshake. Section choreography is added
   in later steps and hangs off `Motion.onReady`. */
(function () {
  'use strict';

  var root = document.documentElement;
  var cfg = window.__motionCfg || {};
  var reduceMq = window.matchMedia('(prefers-reduced-motion: reduce)');
  var mobileMq = window.matchMedia('(max-width: 768px)');

  // ── Tokens: CSS is the source of truth ────────────────────────────────
  var cs = getComputedStyle(root);
  function num(name, fallback) {
    var v = parseFloat(cs.getPropertyValue(name));
    return isNaN(v) ? fallback : v;
  }
  var scale = mobileMq.matches ? 0.8 : 1; // mobile: shorter, calmer
  var tokens = {
    ease: { out: 'expo.out', inOut: 'power3.inOut', in: 'power2.in' }, // GSAP names of the CSS curves
    dur: {
      micro:  num('--dur-micro', 150) / 1000 * scale,
      ui:     num('--dur-ui', 300) / 1000 * scale,
      reveal: num('--dur-reveal', 600) / 1000 * scale,
      hero:   num('--dur-hero', 900) / 1000 * scale
    },
    stagger: {
      item: num('--stagger-item', 60) / 1000,
      word: num('--stagger-word', 45) / 1000
    },
    dist: {
      micro:  num('--dist-micro', 6),
      reveal: num('--dist-reveal', 20) * scale
    }
  };

  var readyCbs = [];
  var Motion = {
    tokens: tokens,
    cfg: cfg,
    reduced: function () { return reduceMq.matches; },
    mobile: function () { return mobileMq.matches; },
    lenis: null,
    ready: false,
    onReady: function (fn) { Motion.ready ? fn(Motion) : readyCbs.push(fn); }
  };
  window.Motion = Motion;

  function markReady() {
    Motion.ready = true;
    window.__motionReady = true; // cancels the 3s failsafe in head.php
    root.classList.add('motion-ready');
    readyCbs.splice(0).forEach(function (fn) { fn(Motion); });
  }

  // Reduced motion: no js-motion gate, no smooth scroll, no choreography.
  if (reduceMq.matches) { markReady(); return; }

  // ── Lenis (marketing pages) ───────────────────────────────────────────
  function startLenis() {
    if (!cfg.smooth || !window.Lenis || Motion.lenis) return;
    var lenis = new window.Lenis({ lerp: 0.1, smoothWheel: true, autoRaf: true });
    Motion.lenis = lenis;

    // Modals lock page scroll: pause Lenis while one is open (pricing).
    var modalIds = ['payment-loading-modal', 'action-modal'];
    var modals = modalIds.map(function (id) { return document.getElementById(id); }).filter(Boolean);
    if (modals.length) {
      var sync = function () {
        var open = modals.some(function (m) { return !m.classList.contains('hidden'); });
        open ? lenis.stop() : lenis.start();
      };
      var mo = new MutationObserver(sync);
      modals.forEach(function (m) { mo.observe(m, { attributes: true, attributeFilter: ['class'] }); });
      sync();
    }
  }

  function stopLenis() {
    if (Motion.lenis) { Motion.lenis.destroy(); Motion.lenis = null; }
  }

  // Visitor flips "reduce motion" on mid-session: stand everything down.
  reduceMq.addEventListener('change', function () {
    if (reduceMq.matches) { stopLenis(); root.classList.remove('js-motion'); }
  });

  startLenis();
  markReady();
})();
