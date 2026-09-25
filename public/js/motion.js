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

  // ── Marketing choreography (GSAP: home + pricing) ─────────────────────
  // Elements opt in with data-m="rise|card|wordmark|orb". Those with a
  // data-m-at (seconds) belong to the hero timeline; the rest reveal as they
  // scroll into view, in small batches. GSAP tweens two custom properties
  // (--m-o / --m-y) that motion.css maps to opacity / translateY, and .m-done
  // returns each element to its natural styles afterwards.
  function revealEverything() {
    root.classList.remove('js-motion');
    document.querySelectorAll('[data-m]').forEach(function (el) { el.classList.add('m-done'); });
  }

  function initMarketing() {
    var g = window.gsap;
    var T = tokens;
    var t0 = performance.now();
    var els = Array.prototype.slice.call(document.querySelectorAll('[data-m]'));
    if (!els.length) return;

    function done(el) { el.classList.add('m-done'); }
    function rise(list, baseDelay) {
      if (!list.length) return;
      g.fromTo(list, { '--m-o': 0, '--m-y': 1 }, {
        '--m-o': 1, '--m-y': 0,
        duration: T.dur.reveal, ease: T.ease.out,
        delay: function (i) { return baseDelay + Math.min(i, 5) * T.stagger.item; }, // mobile-friendly cap
        onComplete: function () { list.forEach(done); }
      });
    }

    // Hero timeline (items with data-m-at)
    var hero = els.filter(function (el) { return el.hasAttribute('data-m-at'); });
    var tl = g.timeline();
    hero.forEach(function (el) {
      var at = parseFloat(el.getAttribute('data-m-at')) || 0;
      if (el.getAttribute('data-m') === 'wordmark') {
        tl.fromTo(el, { '--m-wy': 110 }, { '--m-wy': 0, duration: T.dur.hero, ease: T.ease.out, onComplete: function () { done(el); } }, at);
      } else {
        tl.fromTo(el, { '--m-o': 0, '--m-y': 1 }, { '--m-o': 1, '--m-y': 0, duration: T.dur.reveal * 0.9, ease: T.ease.out, onComplete: function () { done(el); } }, at);
      }
    });

    // Wordmark glint: once, right after it lands, then only occasionally
    var mark = document.querySelector('.shine-text');
    if (mark && hero.length) {
      var glint = function () {
        if (document.hidden) return;
        var r = mark.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) return;
        mark.classList.remove('is-glinting');
        void mark.offsetWidth; // restart the animation
        mark.classList.add('is-glinting');
      };
      mark.addEventListener('animationend', function () { mark.classList.remove('is-glinting'); });
      tl.call(glint, null, 1.1);
      setInterval(glint, 13000);
    }

    // Everything else: reveal on entering the viewport, batched
    var later = els.filter(function (el) {
      var m = el.getAttribute('data-m');
      return !el.hasAttribute('data-m-at') && (m === 'rise' || m === 'card');
    });
    if (later.length && 'IntersectionObserver' in window) {
      var queue = [], timer = null;
      var flush = function () {
        timer = null;
        var batch = queue.splice(0).sort(function (a, b) {
          return (a.compareDocumentPosition(b) & 4) ? -1 : 1; // DOM order
        });
        // first batch on the home page waits for the hero to have started
        var wait = hero.length ? Math.max(0, 0.75 - (performance.now() - t0) / 1000) : 0.05;
        rise(batch, wait);
      };
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          io.unobserve(e.target);
          queue.push(e.target);
        });
        if (queue.length && !timer) timer = setTimeout(flush, 30);
      }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
      later.forEach(function (el) { io.observe(el); });
    } else {
      later.forEach(done);
    }

    // Ambient orbs: very slow drift, desktop only
    if (!mobileMq.matches && window.matchMedia('(min-width: 1024px)').matches) {
      els.filter(function (el) { return el.getAttribute('data-m') === 'orb'; }).forEach(function (orb, i) {
        g.to(orb, { x: i ? -34 : 30, y: i ? 22 : -20, duration: 20 + i * 4, ease: 'sine.inOut', yoyo: true, repeat: -1 });
      });
    }
  }

  function initChoreography() {
    if (!cfg.gsap) return;
    try {
      if (!window.gsap) throw new Error('gsap unavailable');
      initMarketing();
    } catch (err) {
      revealEverything(); // never leave content hidden
    }
  }

  startLenis();
  initChoreography();
  markReady();
})();
