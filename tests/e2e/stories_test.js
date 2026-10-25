// Stories (list, reader, word drawer, save to cards, API, admin) + the new
// homepage and footer, on local staging.
// usage: BASE=http://127.0.0.1:8090/ node stories_test.js <outDir> [adminSid]
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execSync } = require('child_process');
const BASE = process.env.BASE || 'http://127.0.0.1:8090/';
const API = BASE + 'api/v1/';
const [, , OUT = '.', ADMIN_SID = ''] = process.argv;
const sql = q => execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();
const LANGS = ['en', 'de', 'fr', 'es', 'ar', 'ja', 'zh', 'ru', 'el', 'hi', 'hy'];
const results = [];
function rec(ok, name, d = '') { results.push(ok); console.log((ok ? 'PASS ' : 'FAIL ') + name + (d ? '  — ' + d : '')); }
async function check(name, fn) { try { const r = await fn(); rec(true, name, typeof r === 'string' ? r : ''); } catch (e) { rec(false, name, String(e.message || e).split('\n').slice(0, 5).join(' ¦ ').slice(0, 500)); } }
function assert(c, m) { if (!c) throw new Error(m); }
// Raw translation keys (stories.read, home.cta_title …) must never reach a page.
const RAW = /\b(stories|home|footer|nav|pricing|faq)\.[a-z][a-z0-9_]+\b/;

(async () => {
  sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
  const slugs = sql("SELECT slug FROM stories WHERE status = 'published' ORDER BY id").split('\n').filter(Boolean);
  assert(slugs.length, 'no published stories on this database');
  const slug = slugs.includes('pied-piper-of-hamelin') ? 'pied-piper-of-hamelin' : slugs[0];
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 860 }, locale: 'en-US' });
  await ctx.addCookies([{ name: 'cookie_consent', value: '1', url: BASE }]);
  const page = await ctx.newPage();
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  page.on('response', r => { if (r.url().startsWith(BASE) && r.status() >= 500) errs.push(r.status() + ' ' + r.url()); });
  const noErr = () => { const e = errs.splice(0); assert(!e.length, e.join(' | ')); };

  // ── Homepage + footer in every interface language ──
  for (const ui of LANGS) {
    await check(`home (${ui}): no raw keys, every section, footer columns`, async () => {
      await page.goto(BASE + '?ui_lang=' + ui); await page.waitForLoadState();
      const body = await page.locator('body').innerText();
      assert(!RAW.test(body), 'raw key: ' + (body.match(RAW) || [])[0]);
      for (const id of ['hm-hero-title', 'hm-how-title', 'hm-tour-title', 'hm-langs-title', 'hm-price-title', 'hm-faq-title', 'hm-cta-title']) {
        assert(await page.locator('#' + id).count() === 1, 'missing #' + id);
      }
      assert(await page.locator('.jl-footer nav').count() === 3, 'footer columns');
      assert(await page.locator('.hm-tab').count() === 7, 'feature tabs');
      assert(await page.locator('html').getAttribute('dir') === (ui === 'ar' ? 'rtl' : 'ltr'), 'dir');
      noErr();
    });
  }
  await check('home: every recording file exists and is served as video/mp4', async () => {
    await page.goto(BASE + '?ui_lang=en');
    const srcs = await page.$$eval('video', vs => vs.map(v => v.getAttribute('src') || v.dataset.src));
    assert(srcs.length === 8, 'videos: ' + srcs.length);
    for (const s of new Set(srcs)) {
      const r = await page.request.get(BASE.replace(/\/$/, '') + s);
      assert(r.ok() && /video\/mp4/.test(r.headers()['content-type'] || ''), s + ' ' + r.status() + ' ' + r.headers()['content-type']);
    }
    return srcs.length + ' videos';
  });
  await check('home: feature tabs switch panes; the story word demo shows reading + meaning', async () => {
    await page.click('#hm-tab-stories');
    assert(await page.locator('#hm-pane-stories').isVisible(), 'stories pane');
    assert(await page.locator('#hm-pane-chat').isHidden(), 'chat pane hidden');
    if (await page.locator('.hm-try-tok').count()) {
      const t = page.locator('.hm-try-tok').nth(2);
      await t.click();
      assert(await page.locator('#hm-try-word').innerText() === await t.getAttribute('data-word'), 'word');
      assert((await page.locator('#hm-try-mean').innerText()).trim() !== '', 'meaning');
    }
    await page.click('.hm-period-btn[data-period="yearly"]');
    assert(await page.locator('.hm-price[data-period="yearly"]').first().isVisible(), 'yearly prices');
    noErr();
  });
  await check('navbar: Pages menu keeps every page and adds Stories', async () => {
    await page.click('#pagesBtn');
    const hrefs = await page.$$eval('#pagesMenu a', as => as.map(a => a.getAttribute('href')));
    for (const h of ['?page=dashboard', '?page=chat-tips', '?page=alphabet', '?page=mistakes', '/stories', '?page=about', '?page=contact', '?page=faq', '?page=blog',
      '?page=privacy-policy', '?page=terms-and-conditions', '?page=refund-policy', '?page=cookie-policy', '?page=license-agreement']) {
      assert(hrefs.includes(h), 'missing ' + h);
    }
    return hrefs.length + ' links';
  });

  // ── Story list ──
  await check('stories list: 11 language links, filters are plain links, cards link to /stories/<slug>/<lang>', async () => {
    await page.goto(BASE + 'stories?lang=ja'); noErr();
    assert(await page.locator('.st-filter .st-chip[lang]').count() === 11, 'language chips');
    const href = await page.locator('.st-card').first().getAttribute('href');
    assert(/^\/stories\/[a-z0-9-]+\/ja$/.test(href), href);
    await page.goto(BASE + 'stories?lang=en&cat=bedtime&len=long');
    const cards = await page.locator('.st-card').count(), empty = await page.locator('.st-empty').count();
    assert(cards + empty >= 1, 'cards or empty state');
  });
  for (const ui of LANGS) {
    await check(`stories list (${ui} UI): no raw keys`, async () => {
      await page.goto(BASE + 'stories?ui_lang=' + ui); await page.waitForLoadState();
      const body = await page.locator('main').innerText();
      assert(!RAW.test(body), 'raw key: ' + (body.match(RAW) || [])[0]);
    });
  }

  // ── Reader in every story language ──
  for (const l of LANGS) {
    await check(`reader ${slug}/${l}: lang + dir, words with readings and meanings, plain text`, async () => {
      const r = await page.goto(BASE + `stories/${slug}/${l}`); noErr();
      assert(r.status() === 200, 'status ' + r.status());
      assert(await page.locator('.st-words').getAttribute('lang') === l, 'lang');
      assert(await page.locator('.st-words').getAttribute('dir') === (l === 'ar' ? 'rtl' : 'ltr'), 'dir');
      const toks = await page.locator('.st-tok').count();
      assert(toks > 10, 'tokens ' + toks);
      const emptyR = await page.$$eval('.st-tok .st-r', rs => rs.filter(r => !r.textContent.trim()).length);
      assert(emptyR === 0, emptyR + ' words without a reading');
      const emptyM = await page.$$eval('.st-tok .st-m', ms => ms.filter(m => !m.textContent.trim()).length);
      assert(emptyM <= Math.ceil(toks * 0.02), emptyM + ' words without a meaning');
      assert(await page.locator('link[rel=alternate][hreflang]').count() === 11, 'hreflang');
      await page.click('#st-tab-text');
      assert((await page.locator('.st-text').innerText()).length > 50, 'plain text');
      return toks + ' words';
    });
  }
  await check('reader: meaning language switch rewrites every meaning', async () => {
    await page.goto(BASE + `stories/${slug}/es`);
    const before = await page.locator('.st-tok .st-m').first().innerText();
    await page.click('.st-mean[data-lang="ja"]');
    const after = await page.locator('.st-tok .st-m').first().innerText();
    assert(await page.locator('.st-tok .st-m').first().getAttribute('lang') === 'ja', 'lang attr');
    assert(before !== after, `${before} → ${after}`);
  });
  await check('reader (guest): word drawer shows word, reading, meaning and the sign-up offer', async () => {
    await page.locator('.st-tok').nth(3).click();
    assert(await page.locator('#st-drawer').isVisible(), 'drawer');
    assert((await page.locator('#st-d-word').innerText()).trim() !== '', 'word');
    assert((await page.locator('#st-d-read').innerText()).trim() !== '', 'reading');
    assert(await page.locator('.st-d-guest a[href="?page=register"]').count() === 1, 'sign-up link');
    const login = await page.locator('.st-d-guest a[href*="page=login"]').getAttribute('href');
    assert(login.includes('redirect=stories%2F'), login);
    await page.keyboard.press('Escape');
    assert(await page.locator('#st-drawer').isHidden(), 'closes with Escape');
  });
  await check('reader: unknown story 404, no language redirects', async () => {
    const r = await page.goto(BASE + 'stories/no-such-story/en'); assert(r.status() === 404, 'status ' + r.status());
    const r2 = await page.request.get(BASE + `stories/${slug}`, { maxRedirects: 0 });
    assert(r2.status() === 302 && /\/stories\/[a-z0-9-]+\/[a-z]{2}$/.test(r2.headers()['location']), r2.status() + ' ' + r2.headers()['location']);
  });
  await check('reader on a phone: no horizontal scroll (es, ar, ja)', async () => {
    const p2 = await (await browser.newContext({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true })).newPage();
    for (const l of ['es', 'ar', 'ja']) {
      await p2.goto(BASE + `stories/${slug}/${l}`);
      const over = await p2.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      assert(over <= 1, `${l}: ${over}px wider than the screen`);
    }
    await p2.goto(BASE + '?ui_lang=en');
    const over = await p2.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    assert(over <= 1, `home: ${over}px wider than the screen`);
    await p2.context().close();
  });

  // ── Saving a word (signed in) ──
  const stamp = Date.now().toString(36);
  const email = `st-${stamp}@example.test`, pass = 'St-pass-' + stamp;
  // The hash has "$" in it: escape it for the shell that runs psql.
  const hash = execSync(`php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' '${pass}'`).toString().replace(/\$/g, '\\$');
  sql(`INSERT INTO users (email,password,name,native_lang,target_lang,cefr_level,plan_status,has_paid,onboarding_completed,email_verified_at) VALUES ('${email}','${hash}','St User','de','es','A2','pro',1,1,now())`);
  const uid = sql(`SELECT id FROM users WHERE email='${email}'`);
  await check('signed in: saving a word creates a card (German meaning, the sentence as example) in the Saved list', async () => {
    await page.goto(BASE + '?page=login');
    await page.fill('input[name=email]', email); await page.fill('input[name=password]', pass);
    await Promise.all([page.waitForNavigation(), page.click('form button[type=submit]')]);
    await page.goto(BASE + `stories/${slug}/es`);
    assert(await page.locator('.st-mean.is-on').getAttribute('data-lang') === 'de', 'meanings default to the native language');
    const tok = page.locator('.st-tok').nth(5);
    await tok.click();
    await page.click('#st-d-save');
    await page.waitForFunction(() => document.getElementById('st-d-save').classList.contains('is-done'));
    const row = sql(`SELECT vw.word || '|' || vw.translation || '|' || vw.language || '|' || (vw.example <> '') FROM vocabulary_words vw WHERE vw.user_id = ${uid} AND vw.category = 'Stories'`);
    const [w, tr, lang, ex] = row.split('|');
    assert(lang === 'es' && tr && (ex === 't' || ex === 'true'), row);
    const inSaved = sql(`SELECT COUNT(*) FROM card_list_items i JOIN card_lists l ON l.id = i.list_id WHERE l.user_id = ${uid} AND l.language = 'es'`);
    assert(+inSaved >= 1, 'not in a list');
    assert(await tok.evaluate(e => e.classList.contains('is-saved')), 'word marked saved');
    return `${w} → ${tr}`;
  });
  await check('signed in: saving the same word again keeps one card', async () => {
    await page.reload();
    await page.locator('.st-tok').nth(5).click();
    await page.click('#st-d-save');
    await page.waitForTimeout(1200);
    assert(sql(`SELECT COUNT(*) FROM vocabulary_words WHERE user_id = ${uid} AND category = 'Stories'`) === '1', 'duplicate card');
  });
  await check('save endpoint: CSRF required, guests refused, never a 5xx', async () => {
    const bad = await page.request.post(BASE + '?page=story-word-save', { data: { slug, lang: 'es', s: 0, t: 0 } });
    const j = await bad.json();
    assert(bad.status() === 200 && j.success === false && j.error === 'invalid_request', JSON.stringify(j));
    const guest = await (await browser.newContext()).request.post(BASE + '?page=story-word-save', { data: { slug, lang: 'es', s: 0, t: 0, csrf_token: 'x' } });
    assert((await guest.json()).success === false, 'guest');
  });

  // ── API ──
  await check('API: stories list, one story, save requires sign-in', async () => {
    const list = await (await fetch(API + 'stories?lang=hy')).json();
    assert(list.ok && list.data.stories.length === slugs.length && list.data.lang === 'hy', JSON.stringify(list).slice(0, 200));
    const one = await (await fetch(API + `stories/${slug}?lang=ar`)).json();
    assert(one.ok && one.data.dir === 'rtl' && one.data.sentences[0].tokens[0].r !== undefined, JSON.stringify(one).slice(0, 200));
    const save = await (await fetch(API + `stories/${slug}/words`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{"lang":"es","s":0,"t":0}' })).json();
    assert(save.ok === false && save.error.code === 'unauthorized', JSON.stringify(save));
    const nf = await fetch(API + 'stories/nope');
    assert(nf.status === 404, 'status ' + nf.status);
  });
  await check('API: signed-in save with a bearer token', async () => {
    const login = await (await fetch(API + 'auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password: pass }) })).json();
    assert(login.ok, JSON.stringify(login));
    const r = await (await fetch(API + `stories/${slug}/words`, { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + login.data.token }, body: '{"lang":"ja","s":0,"t":0}' })).json();
    assert(r.ok && r.data.card_id > 0, JSON.stringify(r));
    assert(sql(`SELECT language FROM vocabulary_words WHERE id = ${r.data.card_id} AND user_id = ${uid}`) === 'ja', 'deck language');
  });

  // ── Admin ──
  if (ADMIN_SID) {
    await check('admin: stories list and edit pages load', async () => {
      const a = await (await browser.newContext()).newPage();
      await a.context().addCookies([{ name: 'PHPSESSID', value: ADMIN_SID, url: BASE }]);
      await a.goto(BASE + '?page=admin-stories');
      assert(await a.locator('table tbody tr').count() >= slugs.length, 'rows');
      const id = sql(`SELECT id FROM stories WHERE slug = '${slug}'`);
      await a.goto(BASE + '?page=admin-story&id=' + id);
      assert(await a.locator('#build-list li').count() === 11, 'language rows');
      await a.goto(BASE + '?page=admin-story');
      assert(await a.locator('textarea[name=body]').count() === 1, 'new story form');
    });
  }

  await page.screenshot({ path: OUT + '/stories-last.png' });
  await browser.close();
  const pass_ = results.filter(Boolean).length;
  console.log(`\n${pass_}/${results.length} passed`);
  process.exit(pass_ === results.length ? 0 : 1);
})();
