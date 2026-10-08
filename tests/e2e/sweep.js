// Full-site sweep against local staging (http://127.0.0.1:8090).
// usage: node sweep.js <adminSid> <outDir> [sections e.g. guest,user,i18n,admin,api]
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8090/'; // BASE=https://jumplearner.com/ for production (guest section only)
const [, , ADMIN_SID, OUT, SECT = 'guest,user,i18n,admin,api'] = process.argv;
const ON = new Set(SECT.split(','));
const LANGS = ['en', 'de', 'fr', 'es', 'ar', 'ja', 'zh', 'ru', 'el', 'hi', 'hy'];
const PUBLIC = ['home', 'pricing', 'about', 'contact', 'faq', 'blog', 'alphabet', 'privacy-policy', 'terms-and-conditions',
  'refund-policy', 'license-agreement', 'cookie-policy', 'login', 'register', 'forgot-password'];
const KEY_RE = /\b(nav|auth|chat|common|home|pricing|fc|flashcards|onboarding|error|dashboard|dash|mis|mistakes|footer|account|alphabet|languages|admin|api|faq|about|contact|blog|legal)\.[a-z0-9_]{3,}\b/;
const PHP_RE = /(Fatal error|Warning:|Notice:|Deprecated:|Parse error|Uncaught|Stack trace)/;
const TR_RE = /\b(Merhaba|Giriş yap|Kayıt ol|Kelimeler|Ayarlar|Çıkış|Fiyatlandırma|Hesabım)\b/;
const sql = q => process.env.BASE ? '' : execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();

const results = [];
const log = fs.createWriteStream(OUT + '/sweep2.log', { flags: 'a' });
function rec(ok, name, detail = '') { results.push({ ok, name, detail }); const l = (ok ? 'PASS ' : 'FAIL ') + name + (detail ? '  — ' + detail : ''); console.log(l); log.write(l + '\n'); }
async function check(name, fn) {
  try { const r = await fn(); rec(true, name, typeof r === 'string' ? r : ''); }
  catch (e) { rec(false, name, String(e.message || e).split('\n')[0].slice(0, 220)); }
}
function assert(c, m) { if (!c) throw new Error(m); }

function watch(page, bag) {
  page.on('pageerror', e => bag.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error' && !/flagcdn|fonts\.g|favicon|ERR_NAME|ERR_INTERNET|googletagmanager|accounts\.google|gsi|cdn\.|Failed to load resource/.test(m.text())) bag.push('console: ' + m.text().slice(0, 160)); });
  page.on('response', r => { if (r.url().startsWith(BASE) && r.status() >= 500) bag.push(r.status() + ' ' + r.url()); });
}
async function pageSane(page, bag, { lang, mobile } = {}) {
  const html = await page.content();
  const text = await page.evaluate(() => document.body ? document.body.innerText : '');
  assert(!PHP_RE.test(html), 'PHP error text: ' + (html.match(PHP_RE) || [])[0]);
  const k = text.match(KEY_RE); assert(!k, 'raw i18n key visible: ' + (k && k[0]));
  if (lang && lang !== 'en') { const t = text.match(TR_RE); assert(!t, 'Turkish text: ' + (t && t[0])); }
  if (lang) {
    const d = await page.evaluate(() => [document.documentElement.lang, document.documentElement.dir]);
    if (lang === 'ar') assert(d[1] === 'rtl', 'arabic page not rtl (dir=' + d[1] + ')');
    else assert(d[1] !== 'rtl', 'non-arabic page rtl');
  }
  if (mobile) {
    const ov = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    assert(ov <= 2, 'horizontal overflow ' + ov + 'px');
  }
  const errs = bag.splice(0); assert(errs.length === 0, errs.join(' | ').slice(0, 300));
}

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, headless: true });
  const stamp = Date.now().toString(36);

  // ── 1. Guest pages in every UI language, desktop + phone ─────────────
  if (ON.has('guest')) for (const lang of LANGS) {
    for (const mobile of [false, true]) {
      const ctx = await browser.newContext({ locale: lang, viewport: mobile ? { width: 390, height: 844 } : { width: 1366, height: 860 }, isMobile: mobile, hasTouch: mobile });
      await ctx.addCookies([{ name: 'jl_lang', value: lang, url: BASE }]);
      const page = await ctx.newPage(); const bag = []; watch(page, bag);
      for (const p of PUBLIC) {
        await check(`guest ${lang} ${mobile ? 'phone' : 'desk'} ${p}`, async () => {
          const r = await page.goto(BASE + '?page=' + p, { waitUntil: 'domcontentloaded' });
          assert(r.status() < 400, 'HTTP ' + r.status());
          await page.waitForTimeout(150);
          await pageSane(page, bag, { lang, mobile });
        });
      }
      await ctx.close();
    }
  }

  // ── 2. New-user journey through the UI (the reported bug case) ───────
  const email = `sweep-${stamp}@example.test`, pass = 'Sweep-pass-' + stamp;
  if (ON.has('user') || ON.has('i18n')) {
  const ctx = await browser.newContext({ locale: 'de-DE', viewport: { width: 1366, height: 860 } });
  const page = await ctx.newPage(); const bag = []; watch(page, bag);
  page.on('dialog', d => d.accept());

  await check('register: mismatched passwords rejected', async () => {
    await page.goto(BASE + '?page=register');
    await page.fill('input[name=name]', 'Sweep User'); await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', pass); await page.fill('input[name=password_confirm]', pass + 'x');
    await page.check('#terms'); await page.click('#register-submit-btn'); await page.waitForLoadState();
    assert(/page=register/.test(page.url()) || await page.locator('input[name=password_confirm]').count(), 'not kept on register');
    await pageSane(page, bag, { lang: 'de' });
  });
  await check('register: success → check your email', async () => {
    await page.goto(BASE + '?page=register');
    await page.fill('input[name=name]', 'Sweep User'); await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', pass); await page.fill('input[name=password_confirm]', pass);
    await page.check('#terms'); await page.click('#register-submit-btn'); await page.waitForLoadState();
    assert(/awaiting-verification/.test(page.url()), 'landed on ' + page.url());
    await pageSane(page, bag, { lang: 'de' });
  });
  await check('login: refused until the email is confirmed → resend page with the address', async () => {
    await page.goto(BASE + '?page=login');
    await page.fill('#login-form input[name=email]', email); await page.fill('#login-form input[name=password]', pass);
    await Promise.all([page.waitForNavigation(), page.click('#login-submit-btn')]);
    assert(/awaiting-verification/.test(page.url()), 'landed on ' + page.url());
    assert(await page.inputValue('#resend-email') === email, 'address not prefilled');
    const r = await page.request.get(BASE + '?page=dashboard', { maxRedirects: 0 }); assert(r.status() === 302, 'dashboard reachable unverified');
  });
  await check('resend verification: same answer, page stays', async () => {
    await Promise.all([page.waitForNavigation(), page.locator('form[action*=resend-verification] button[type=submit]').click()]);
    assert(/awaiting-verification/.test(page.url()), page.url());
    assert(await page.locator('[role=status]').count(), 'no confirmation shown');
    await pageSane(page, bag, { lang: 'de' });
  });
  await check('verify link → login → onboarding', async () => {
    const token = 'sweep' + stamp;
    sql(`INSERT INTO email_verifications (user_id, token_hash, expires_at) SELECT id, encode(sha256('${token}'), 'hex'), now() + interval '1 day' FROM users WHERE email = '${email}'`);
    await page.goto(BASE + '?page=verify-email&token=' + token); await page.waitForLoadState();
    assert(/email_verified=1/.test(page.url()), 'verify failed: ' + page.url());
    await page.fill('#login-form input[name=email]', email); await page.fill('#login-form input[name=password]', pass);
    await Promise.all([page.waitForNavigation(), page.click('#login-submit-btn')]);
    assert(/onboarding/.test(page.url()), 'landed on ' + page.url());
    await pageSane(page, bag, { lang: 'de' });
  });
  await check('onboarding: native=German, target=English', async () => {
    await page.evaluate(() => { selectLang('native', 'de', 'de', 'Deutsch'); selectLang('target', 'en', 'gb', 'English'); });
    for (const n of ['cefr_level', 'learning_goal', 'interest_area']) await page.locator(`input[name=${n}]`).first().check({ force: true });
    await Promise.all([page.waitForNavigation(), page.locator('#onboarding-form [type=submit]').click()]);
    assert(!/onboarding/.test(page.url()), 'still on onboarding: ' + page.url());
    await pageSane(page, bag, { lang: 'de' });
  });
  await check('trial start → chat', async () => {
    await page.goto(BASE + '?page=start-trial'); await page.waitForLoadState();
    assert(/page=chat/.test(page.url()), 'landed ' + page.url());
    await pageSane(page, bag, { lang: 'de' });
  });
  await check('chat: target shows English, placeholder names the language', async () => {
    const t = await page.locator('#lang-selector-btn').innerText();
    assert(/Englisch|English/.test(t), 'selector says ' + t);
    const ph = await page.getAttribute('#chat-input', 'placeholder'); assert(!/\bEN\b/.test(ph), 'placeholder ' + ph);
  });
  await check('chat: dropdown excludes native German and current English', async () => {
    const hrefs = await page.$$eval('#lang-dropdown a', as => as.map(a => a.getAttribute('href')));
    assert(hrefs.length > 3, 'only ' + hrefs.length + ' options');
    assert(!hrefs.some(h => /lang=(de|en)$/.test(h)), 'has ' + hrefs.join(','));
  });
  await check('navbar dropdown excludes native', async () => {
    const hrefs = await page.$$eval('#nav-lang-dropdown a', as => as.map(a => a.getAttribute('href')));
    assert(!hrefs.some(h => /lang=(de|en)$/.test(h)), 'has ' + hrefs.join(','));
  });
  await check('chat: send message gets AI reply', async () => {
    await page.fill('#chat-input', 'Hello, I is Sweep and I like learning');
    const resp = page.waitForResponse(r => r.url().includes('page=chat') && r.request().method() === 'POST', { timeout: 20000 });
    await page.click('#btn-send');
    const j = await (await resp).json().catch(() => null);
    assert(j && j.content && !j.error, 'reply json: ' + JSON.stringify(j).slice(0, 150));
    await page.waitForTimeout(1500);
    await pageSane(page, bag);
  });
  await check('chat: speaker buttons work (no JS error)', async () => {
    await page.evaluate(() => { if (window.speechSynthesis) window.speechSynthesis.speak = () => {}; });
    const btns = page.locator('#vocab-words-list [onclick*="speakText"], #chat-messages [data-text]');
    const n = await btns.count(); assert(n > 0, 'no speaker buttons');
    await btns.first().click({ force: true }); await page.waitForTimeout(300); await pageSane(page, bag); return n + ' buttons';
  });
  await check('chat: switch to French via dropdown', async () => {
    await page.click('#lang-selector-btn');
    await Promise.all([page.waitForNavigation(), page.click('#lang-dropdown a[href$="lang=fr"]')]);
    const t = await page.locator('#lang-selector-btn').innerText();
    assert(/Französisch|French/.test(t), 'selector says ' + t);
  });
  await check('chat: switch back to English via dropdown (reported bug)', async () => {
    await page.click('#lang-selector-btn');
    await Promise.all([page.waitForNavigation(), page.click('#lang-dropdown a[href$="lang=en"]')]);
    const t = await page.locator('#lang-selector-btn').innerText();
    assert(/Englisch|English/.test(t), 'selector says ' + t);
  });
  await check('update_lang to native shows notice, keeps target', async () => {
    await page.goto(BASE + '?page=update_lang&lang=de'); await page.waitForLoadState();
    const t = await page.locator('#lang-selector-btn').innerText();
    assert(/Englisch|English/.test(t), 'target changed to ' + t);
    const body = await page.evaluate(() => document.body.innerText);
    assert(/Muttersprache|native/i.test(body), 'no notice shown');
  });
  await check('update_lang invalid code ignored', async () => {
    const r = await page.goto(BASE + '?page=update_lang&lang=xx%3Cscript%3E'); assert(r.status() < 500);
    const t = await page.locator('#lang-selector-btn').innerText(); assert(/Englisch|English/.test(t), t);
    await pageSane(page, bag);
  });

  if (ON.has('user')) {
  for (const p of ['dashboard', 'flashcards', 'mistakes', 'alphabet', 'pricing', 'account-delete-confirm', 'chat-tips', 'home']) {
    await check(`user page ${p}`, async () => {
      const r = await page.goto(BASE + '?page=' + p, { waitUntil: 'domcontentloaded' });
      assert(r.status() < 400, 'HTTP ' + r.status()); await page.waitForTimeout(400);
      await pageSane(page, bag, { lang: 'de' });
    });
  }

  // flashcards
  await page.goto(BASE + '?page=flashcards'); await page.waitForTimeout(800);
  await check('flashcards: deck language selector (no native, no tr)', async () => {
    const opts = await page.$$eval('#fc-lang option', os => os.map(o => o.value));
    assert(opts.length >= 3, 'options ' + opts); assert(!opts.includes('de'), 'native in deck langs: ' + opts); assert(!opts.includes('tr'), 'turkish in deck langs');
    return opts.join(',');
  });
  await check('flashcards: import a starter pack', async () => {
    const b = page.locator('#fc-packs button:not([disabled])').first();
    if (await b.count() === 0) return 'no packs offered';
    await b.click(); await page.waitForTimeout(1500); await pageSane(page, bag);
  });
  await check('flashcards: create card (XSS payload escaped)', async () => {
    await page.click('#btn-new-card');
    const f = page.locator('#fc-form');
    await f.locator('[name=word]').fill('<img src=x onerror=window.__xss=1>sweep' + stamp);
    await f.locator('[name=translation]').fill('Testwort');
    await f.locator('[type=submit]').click(); await page.waitForTimeout(1200);
    assert(!(await page.evaluate(() => window.__xss)), 'XSS executed');
    assert(await page.locator('#cards-grid', { hasText: 'sweep' + stamp }).count(), 'card not shown');
    await pageSane(page, bag);
  });
  await check('flashcards: card persists after reload', async () => {
    await page.reload(); await page.waitForTimeout(800);
    assert(await page.locator('#cards-grid', { hasText: 'sweep' + stamp }).count(), 'card gone after reload');
  });
  const myIdx = async () => page.$$eval('#cards-grid .grid-item', (els, s) => { const e = els.find(x => x.textContent.includes(s)); return e ? +e.dataset.idx : -1; }, 'sweep' + stamp);
  await check('flashcards: edit card', async () => {
    const i = await myIdx(); assert(i >= 0, 'card idx');
    await page.locator(`#grid-card-${i} [onclick^="fcEdit"]`).click();
    await page.locator('#fc-form [name=translation]').fill('Geändert');
    await page.locator('#fc-form [type=submit]').click(); await page.waitForTimeout(1000);
    assert(await page.locator(`#grid-card-${i}`, { hasText: 'Geändert' }).count(), 'edit not shown');
  });
  await check('flashcards: favorite toggles and persists', async () => {
    const i = await myIdx();
    await page.click(`#fc-fav-${i}`); await page.waitForTimeout(800);
    await page.reload(); await page.waitForTimeout(800);
    const j = await myIdx(); const p = await page.getAttribute(`#fc-fav-${j}`, 'aria-pressed');
    assert(p === 'true', 'aria-pressed=' + p);
  });
  await check('flashcards: learned toggles and persists', async () => {
    const i = await myIdx();
    await page.click(`#fc-learn-${i}`); await page.waitForTimeout(800);
    await page.reload(); await page.waitForTimeout(800);
    const j = await myIdx(); const p = await page.getAttribute(`#fc-learn-${j}`, 'aria-pressed');
    assert(p === 'true', 'aria-pressed=' + p);
  });
  await check('flashcards: flip + review "Good" saves', async () => {
    await page.click('#fc-inner-0'); await page.waitForTimeout(700);
    const resp = page.waitForResponse(r => r.url().includes('flashcard-review'), { timeout: 8000 });
    await page.evaluate(() => document.querySelector('#grid-card-0 .fc-back button:nth-child(3)').click());
    const j = await (await resp).json(); assert(j.success, JSON.stringify(j));
    await pageSane(page, bag); return 'xp ' + j.xp;
  });
  await check('flashcards: real mouse click on back-face "Good" button', async () => {
    await page.click('#fc-inner-1'); await page.waitForTimeout(800);
    const resp = page.waitForResponse(r => r.url().includes('flashcard-review'), { timeout: 8000 });
    await page.locator('#grid-card-1 .fc-back button').nth(2).click({ timeout: 5000 });
    const j = await (await resp).json(); assert(j.success, JSON.stringify(j));
  });
  await check('flashcards: search filter', async () => {
    await page.fill('#word-search', 'sweep' + stamp); await page.waitForTimeout(500);
    const vis = await page.$$eval('#cards-grid .grid-item', els => els.filter(e => e.offsetParent !== null).length);
    assert(vis === 1, vis + ' visible'); await page.fill('#word-search', '');
  });
  await check('flashcards: delete card', async () => {
    const i = await myIdx();
    await page.locator(`#grid-card-${i} [onclick^="fcEdit"]`).click();
    await page.click('#fc-delete'); await page.waitForTimeout(1200);
    await page.reload(); await page.waitForTimeout(800);
    assert(!(await page.locator('#cards-grid', { hasText: 'sweep' + stamp }).count()), 'still there');
    await pageSane(page, bag);
  });
  await check('flashcards: switch deck language to Spanish', async () => {
    await Promise.all([page.waitForNavigation(), page.selectOption('#fc-lang', 'es')]); await page.waitForTimeout(800);
    assert(await page.$eval('#fc-lang', s => s.value) === 'es', 'not es'); await pageSane(page, bag);
  });

  // mistakes
  await check('mistakes: correction from chat listed', async () => {
    await page.goto(BASE + '?page=mistakes'); await page.waitForTimeout(600);
    const n = await page.locator('#mis-list > *').count(); assert(n > 0, 'no mistakes listed'); return n + ' items';
  });
  await check('mistakes: practice round (start → answer → grade)', async () => {
    await page.click('#mis-start-btn'); await page.waitForTimeout(400);
    assert(await page.locator('#mis-practice').isVisible(), 'practice not shown');
    await page.fill('#mis-input', 'yo soy');
    await page.locator('#mis-form [type=submit]').click();
    assert(await page.locator('#mis-a').isVisible(), 'answer not revealed');
    const resp = page.waitForResponse(r => r.url().includes('mistake-review'), { timeout: 8000 });
    await page.click('.mis-grade[data-grade=known]');
    const j = await (await resp).json(); assert(j.ok, JSON.stringify(j));
    await page.waitForTimeout(600); await pageSane(page, bag);
  });

  await check('account export downloads JSON with my email', async () => {
    const r = await page.request.get(BASE + '?page=account-export'); const j = await r.json();
    assert(JSON.stringify(j).includes(email), 'email not in export');
  });
  await check('CSRF: flashcard-review without token rejected', async () => {
    const r = await page.request.post(BASE + '?page=flashcard-review', { data: { vocab_id: 1, quality: 2 } });
    const t = await r.text(); assert(!/"success":\s*true/.test(t), t.slice(0, 100));
  });
  await check('CSRF: chat POST without token rejected', async () => {
    const r = await page.request.post(BASE + '?page=chat', { data: { message: 'hi there friend' } });
    const t = await r.text(); assert(!/"content"/.test(t), t.slice(0, 100));
  });
  await check('admin pages refused for normal user', async () => {
    await page.goto(BASE + '?page=admin-users'); assert(/admin-login/.test(page.url()), page.url());
  });

  // phone view as this user
  const ph = await browser.newContext({ storageState: await ctx.storageState(), viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'de-DE' });
  const pp = await ph.newPage(); const pbag = []; watch(pp, pbag);
  for (const p of ['chat', 'dashboard', 'flashcards', 'mistakes', 'alphabet', 'pricing']) {
    await check(`phone user page ${p}`, async () => {
      await pp.goto(BASE + '?page=' + p); await pp.waitForTimeout(500); await pageSane(pp, pbag, { lang: 'de', mobile: true });
    });
  }
  await check('phone: mobile menu learning chips exclude native German', async () => {
    await pp.goto(BASE + '?page=dashboard');
    const hrefs = await pp.$$eval('#mobileMenu a[href*="update_lang"]', as => as.map(a => a.getAttribute('href')));
    assert(hrefs.length > 0, 'no chips'); assert(!hrefs.some(h => /lang=de$/.test(h)), hrefs.join(','));
    const cur = await pp.$$eval('#mobileMenu a[aria-current]:not([lang])', as => as.map(a => a.innerText.trim()));
    assert(cur.length === 1 && /Englisch|English/.test(cur[0]), 'current chip ' + cur);
  });
  await ph.close();

  await check('logout then wrong password rejected', async () => {
    await page.goto(BASE + '?page=logout'); assert(/login/.test(page.url()));
    await page.fill('input[name=email]', email); await page.fill('input[name=password]', 'wrong-pass-123');
    await page.click('#login-submit-btn'); await page.waitForLoadState();
    assert(/login/.test(page.url()), 'logged in with wrong password!');
  });
  await check('login keeps German UI + English target', async () => {
    await page.fill('input[name=email]', email); await page.fill('input[name=password]', pass);
    await page.click('#login-submit-btn'); await page.waitForLoadState();
    await page.goto(BASE + '?page=chat');
    const lang = await page.evaluate(() => document.documentElement.lang); assert(lang === 'de', 'ui lang ' + lang);
    const t = await page.locator('#lang-selector-btn').innerText(); assert(/Englisch|English/.test(t), t);
  });
  }

  // ── 3. Every UI language as a logged-in user ───────────────────────
  if (ON.has('i18n')) for (const lang of LANGS) {
    await check(`logged-in UI in ${lang} (chat, dashboard, flashcards, mistakes, pricing, alphabet)`, async () => {
      sql(`UPDATE users SET ui_lang='${lang}' WHERE email='${email}'`);
      const c2 = await browser.newContext({ storageState: await ctx.storageState(), locale: lang });
      const p2 = await c2.newPage(); const b2 = []; watch(p2, b2);
      try {
        for (const p of ['chat', 'dashboard', 'flashcards', 'mistakes', 'pricing', 'alphabet']) {
          await p2.goto(BASE + '?page=' + p); await p2.waitForTimeout(300);
          const hl = await p2.evaluate(() => document.documentElement.lang); assert(hl === lang, p + ' html lang=' + hl);
          try { await pageSane(p2, b2, { lang }); } catch (e) { throw new Error(p + ': ' + e.message); }
        }
      } finally { await c2.close(); }
    });
  }
  sql(`UPDATE users SET ui_lang='de' WHERE email='${email}'`);
  await ctx.close();
  }

  // ── 4. Admin panel (every page) ─────────────────────────────────────
  if (ON.has('admin') && ADMIN_SID) {
    const ac = await browser.newContext({ viewport: { width: 1366, height: 860 } });
    await ac.addCookies([{ name: 'PHPSESSID', value: ADMIN_SID, url: BASE }]);
    const ap = await ac.newPage(); const abag = []; watch(ap, abag);
    for (const p of ['admin-dashboard', 'admin-users', 'admin-admins', 'admin-payments', 'admin-activity', 'admin-conversations',
      'admin-ai-usage', 'admin-settings', 'admin-health', 'admin-audit', 'admin-2fa', 'admin-languages', 'admin-language-strings&lang=hy', 'admin-lexicon']) {
      await check(`admin ${p}`, async () => {
        const r = await ap.goto(BASE + '?page=' + p); assert(r.status() < 400, 'HTTP ' + r.status());
        assert(!/admin-login/.test(ap.url()), 'redirected to login');
        await pageSane(ap, abag, {});
        const t = await ap.evaluate(() => document.body.innerText); const tr = t.match(TR_RE); assert(!tr, 'Turkish in admin: ' + (tr && tr[0]));
      });
    }
    await check('admin: user detail page', async () => {
      await ap.goto(BASE + '?page=admin-users');
      const a = ap.locator('a[href*="page=admin-user&"]').first(); assert(await a.count(), 'no user links');
      await a.click(); await ap.waitForLoadState(); await pageSane(ap, abag, {});
    });
    await check('admin: languages list has ru/el/hi/hy', async () => {
      await ap.goto(BASE + '?page=admin-languages'); const t = await ap.evaluate(() => document.body.innerText);
      for (const n of ['Русский', 'Ελληνικά', 'हिन्दी', 'Հայերեն']) assert(t.includes(n), 'missing ' + n);
    });
    await ac.close();
  }

  // ── 5. Mobile API (what the Expo app calls) ─────────────────────────
  if (ON.has('api')) {
  const api = await browser.newContext(); const rq = api.request;
  const A = (m, p, data, tok) => rq.fetch(BASE + 'api/v1/' + p, Object.assign({ method: m, headers: tok ? { Authorization: 'Bearer ' + tok } : {} }, data ? { data } : {}));
  const J = async r => { const s = r.status(); const j = await r.json().catch(() => ({ _raw: 'nonjson' })); assert(s < 500, 'HTTP ' + s); return j; };
  const tokOf = j => j.token || (j.data && j.data.token);
  let tok = '', tokB = '', cardId = 0, convId = 0;
  await check('api config + i18n for every language', async () => {
    await J(await A('GET', 'config'));
    for (const l of LANGS) { const j = await J(await A('GET', 'i18n/' + l)); assert(!j.error, l + ': ' + JSON.stringify(j).slice(0, 80)); }
    const tr = await J(await A('GET', 'i18n/tr')); return 'tr → ' + JSON.stringify(tr).slice(0, 60);
  });
  await check('api register needs terms', async () => {
    const j = await J(await A('POST', 'auth/register', { name: 'Api Sweep', email: `api0-${stamp}@example.test`, password: pass })); assert(j.error, 'accepted without terms');
  });
  await check('api register → awaiting verification, login refused until confirmed', async () => {
    const em = `api-${stamp}@example.test`;
    const j = await J(await A('POST', 'auth/register', { name: 'Api Sweep', email: em, password: pass, accept_terms: true }));
    assert(j.data && j.data.awaiting_verification && !tokOf(j), JSON.stringify(j).slice(0, 150));
    const l = await J(await A('POST', 'auth/login', { email: em, password: pass })); assert(l.error && l.error.code === 'email_unverified', JSON.stringify(l).slice(0, 150));
  });
  await check('api login after confirmation + onboarding (native ru, target en)', async () => {
    sql(`UPDATE users SET email_verified_at = now() WHERE email = 'api-${stamp}@example.test'`);
    const j = await J(await A('POST', 'auth/login', { email: `api-${stamp}@example.test`, password: pass }));
    tok = tokOf(j); assert(tok, JSON.stringify(j).slice(0, 150));
    const o = await J(await A('POST', 'onboarding', { native_lang: 'ru', target_lang: 'en', cefr_level: 'A1', learning_goal: 'travel', interest_area: 'travel' }, tok));
    assert(!o.error, JSON.stringify(o).slice(0, 150));
    const me = await J(await A('GET', 'me', null, tok)); const s = JSON.stringify(me);
    assert(/"target_lang":"en"/.test(s), 'target not en: ' + s.slice(0, 200));
  });
  await check('api PATCH me refuses target == native', async () => {
    await J(await A('PATCH', 'me', { target_lang: 'ru' }, tok));
    const me = await J(await A('GET', 'me', null, tok)); assert(!/"target_lang":"ru"/.test(JSON.stringify(me)), 'target became native');
  });
  await check('api flashcard languages exclude native ru and tr', async () => {
    const s = JSON.stringify(await J(await A('GET', 'flashcards/languages', null, tok)));
    assert(!/"code":"ru"/.test(s), s.slice(0, 200)); assert(!/"code":"tr"/.test(s), 'tr listed');
  });
  await check('api read endpoints', async () => {
    for (const p of ['dashboard', 'topics', 'conversations', 'flashcards', 'flashcards/stats', 'flashcards/categories', 'flashcards/packs', 'mistakes', 'alphabet'])
    { const j = await J(await A('GET', p, null, tok)); assert(!j.error, p + ': ' + JSON.stringify(j).slice(0, 120)); }
  });
  await check('api chat send + conversation fetch', async () => {
    const j = await J(await A('POST', 'chat', { message: 'Hello, I is api sweep' }, tok)); assert(!j.error, JSON.stringify(j).slice(0, 150));
    const c = await J(await A('GET', 'conversations', null, tok)); convId = +(JSON.stringify(c).match(/"id":(\d+)/) || [])[1];
    assert(convId, 'no conversation id'); const one = await J(await A('GET', 'conversations/' + convId, null, tok)); assert(!one.error, JSON.stringify(one).slice(0, 100));
  });
  await check('api flashcard create/get/update/favorite/learned/review', async () => {
    const c = await J(await A('POST', 'flashcards', { word: 'apple' + stamp, translation: 'яблоко', lang: 'en' }, tok)); assert(!c.error, JSON.stringify(c).slice(0, 150));
    const cs = JSON.stringify(c); cardId = +(cs.match(/"id":(\d+)/) || [])[1]; assert(cardId, 'no id ' + cs.slice(0, 150));
    for (const [m, p, d] of [['GET', 'flashcards/' + cardId], ['PATCH', 'flashcards/' + cardId, { translation: 'яблоко!' }], ['POST', `flashcards/${cardId}/favorite`, { on: true }],
      ['POST', `flashcards/${cardId}/learned`, { on: true }], ['POST', 'flashcards/review', { vocab_id: cardId, quality: 2 }]]) {
      const j = await J(await A(m, p, d, tok)); assert(!j.error, m + ' ' + p + ': ' + JSON.stringify(j).slice(0, 120));
    }
    const gs = JSON.stringify(await J(await A('GET', 'flashcards/' + cardId, null, tok)));
    assert(gs.includes('яблоко!'), 'update lost'); assert(/"is_favorite":(true|"t"|1)/.test(gs), 'favorite lost: ' + gs.slice(0, 300)); assert(/"learned_at":"/.test(gs), 'learned lost');
  });
  await check('api IDOR: user B cannot read/edit/delete user A card or conversation', async () => {
    await J(await A('POST', 'auth/register', { name: 'Api Sweep B', email: `apib-${stamp}@example.test`, password: pass, accept_terms: true }));
    sql(`UPDATE users SET email_verified_at = now() WHERE email = 'apib-${stamp}@example.test'`);
    const j = await J(await A('POST', 'auth/login', { email: `apib-${stamp}@example.test`, password: pass }));
    tokB = tokOf(j); assert(tokB, 'no token B');
    await J(await A('POST', 'onboarding', { native_lang: 'fr', target_lang: 'es', cefr_level: 'A1', learning_goal: 'travel', interest_area: 'travel' }, tokB));
    for (const [m, p, d] of [['GET', 'flashcards/' + cardId], ['PATCH', 'flashcards/' + cardId, { translation: 'hacked' }], ['DELETE', 'flashcards/' + cardId], ['GET', 'conversations/' + convId]]) {
      const r = await J(await A(m, p, d, tokB)); assert(r.error || r.ok === false, m + ' ' + p + ' allowed: ' + JSON.stringify(r).slice(0, 100));
    }
    const g = await J(await A('GET', 'flashcards/' + cardId, null, tok)); assert(!JSON.stringify(g).includes('hacked') && !g.error, 'A card changed/deleted');
  });
  await check('api flashcard delete', async () => {
    await J(await A('DELETE', 'flashcards/' + cardId, null, tok)); const g = await J(await A('GET', 'flashcards/' + cardId, null, tok)); assert(g.error, 'still exists');
  });
  await check('api bad token / no token rejected (no 5xx)', async () => {
    const a = await J(await A('GET', 'me', null, 'nope')); const b = await J(await A('GET', 'me')); assert(a.error && b.error);
  });
  await check('api unknown route / wrong method → error, no 5xx', async () => {
    const a = await J(await A('GET', 'nope/route')); const b = await J(await A('DELETE', 'config')); assert(a.error && b.error);
  });
  await check('api account export + delete (user B)', async () => {
    const e = await J(await A('GET', 'account/export', null, tokB)); assert(JSON.stringify(e).includes('apib-' + stamp), 'export missing email');
    const bad = await J(await A('DELETE', 'account', { password: 'wrong' }, tokB)); assert(bad.error, 'deleted with wrong password');
    const d = await J(await A('DELETE', 'account', { password: pass }, tokB)); assert(!d.error, JSON.stringify(d).slice(0, 120));
    const me = await J(await A('GET', 'me', null, tokB)); assert(me.error, 'token still valid after delete');
  });
  await api.close();
  }

  await browser.close();
  const fails = results.filter(r => !r.ok);
  fs.writeFileSync(OUT + '/sweep2-result-' + SECT.replace(/,/g, '_') + '.json', JSON.stringify({ email, total: results.length, fails }, null, 1));
  const l = `TOTAL ${results.length}  PASS ${results.length - fails.length}  FAIL ${fails.length}  [${SECT}]`;
  console.log(l); log.write(l + '\n'); log.end();
})();
