// Word bank page + API on local staging. usage: node words_test.js <outDir>
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8090/';
const [, , OUT = '.'] = process.argv;
const sql = q => execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();
// Sign-up ends on "check your email": confirm the address in the DB, then sign in.
async function signInVerified(page, email, pass) {
  sql(`UPDATE users SET email_verified_at = now() WHERE email = '${email}'`);
  await page.goto(BASE + '?page=login');
  await page.fill('#login-form input[name=email]', email); await page.fill('#login-form input[name=password]', pass);
  await Promise.all([page.waitForNavigation(), page.click('#login-submit-btn')]);
}
// API sign-up returns no token until the email is confirmed: confirm it in the DB, then sign in.
async function apiSignUp(api, body) {
  const post = (p, b) => fetch(api + p, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) }).then(r => r.json());
  const reg = await post('auth/register', body);
  if (!reg.ok) return reg;
  sql(`UPDATE users SET email_verified_at = now() WHERE email = '${body.email}'`);
  return post('auth/login', { email: body.email, password: body.password });
}
const results = [];
function rec(ok, name, d = '') { results.push(ok); console.log((ok ? 'PASS ' : 'FAIL ') + name + (d ? '  — ' + d : '')); }
async function check(name, fn) { try { const r = await fn(); rec(true, name, typeof r === 'string' ? r : ''); } catch (e) { rec(false, name, String(e.message || e).split('\n').slice(0, 5).join(' ¦ ').slice(0, 500)); } }
function assert(c, m) { if (!c) throw new Error(m); }

async function newUser(browser, opts, native, target, ui) {
  const stamp = Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
  const email = `wb-${stamp}@example.test`, pass = 'Wb-pass-' + stamp;
  const ctx = await browser.newContext(opts); const page = await ctx.newPage();
  await page.goto(BASE + '?page=register');
  await page.fill('input[name=name]', 'Wb User'); await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', pass); await page.fill('input[name=password_confirm]', pass);
  await page.check('#terms'); await page.click('#register-submit-btn'); await page.waitForLoadState();
  await signInVerified(page, email, pass);
  await page.evaluate(([n, t]) => { selectLang('native', n, n, n); selectLang('target', t, t, t); }, [native, target]);
  for (const n of ['cefr_level', 'learning_goal', 'interest_area']) await page.locator(`input[name=${n}]`).first().check({ force: true });
  await Promise.all([page.waitForNavigation(), page.locator('#onboarding-form [type=submit]').click()]);
  await page.goto(BASE + '?page=start-trial');
  return { ctx, page, email, pass };
}

(async () => {
  sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  const { page, email } = await newUser(browser, { viewport: { width: 1366, height: 860 }, locale: 'en-US' }, 'en', 'es');
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  page.on('response', r => { if (r.url().startsWith(BASE) && r.status() >= 500) errs.push(r.status() + ' ' + r.url()); });
  const noErr = () => { const e = errs.splice(0); assert(!e.length, e.join(' | ')); };

  await check('word bank opens from the flashcards header', async () => {
    await page.goto(BASE + '?page=flashcards'); await page.waitForTimeout(500);
    await Promise.all([page.waitForNavigation(), page.click('a[href="?page=words"]')]);
    assert(/page=words/.test(page.url()), page.url());
    const count = await page.locator('#wb-count').innerText();
    assert(/^3\d\d words$/i.test(count), count); // 50 starter + ~260 pack
    noErr(); return count;
  });
  await check('starter words show "In your cards", pack words show Add', async () => {
    const have = await page.locator('.fc-words-have').count();
    const add = await page.locator('.fc-words-add').count();
    assert(have === 50 && add === 50, `have ${have} add ${add} (first page of 100)`);
  });
  await check('search by meaning + level filter + only-new', async () => {
    await page.fill('#wb-q', 'apple'); await page.waitForTimeout(200);
    const rows = await page.$$eval('#wb-rows tr', t => t.map(r => r.innerText.replace(/\s+/g, ' ')));
    assert(rows.length >= 1 && rows.some(r => /manzana/i.test(r)), JSON.stringify(rows));
    await page.fill('#wb-q', '');
    await page.selectOption('#wb-level', 'A2'); await page.waitForTimeout(200);
    const lv = await page.$$eval('#wb-rows .level-badge', b => [...new Set(b.map(x => x.textContent))]);
    assert(lv.length === 1 && lv[0] === 'A2', lv.join(','));
    await page.selectOption('#wb-level', '');
    await page.check('#wb-new'); await page.waitForTimeout(200);
    assert(await page.locator('.fc-words-have').count() === 0, 'only-new still shows owned words');
    await page.uncheck('#wb-new');
  });
  let added = '';
  await check('Add puts the word in my cards and in Saved', async () => {
    await page.fill('#wb-q', ''); await page.check('#wb-new'); await page.waitForTimeout(200);
    const btn = page.locator('.fc-words-add').first();
    added = (await btn.getAttribute('aria-label')).replace(/^Add: /, '');
    await btn.click(); await page.waitForTimeout(1200);
    assert(await page.locator('.fc-words-have').count() === 1, 'row did not switch to In your cards');
    await page.uncheck('#wb-new');
    const db = sql(`select v.source||'|'||coalesce((select string_agg(case when l.is_default then 'Saved' else l.name end, ',') from card_list_items i join card_lists l on l.id=i.list_id where i.vocab_id=v.id),'') from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}' and v.word='${added}'`);
    assert(db === 'pack|Saved', 'db: ' + db);
    noErr(); return added;
  });
  await check('adding again is harmless (no duplicate card)', async () => {
    const r = await page.evaluate(async (w) => (await fetch('?page=word-add', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ word: w, csrf_token: window.__WB__.csrf }) })).json(), added);
    assert(r.success, JSON.stringify(r));
    assert(sql(`select count(*) from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}' and lower(v.word)=lower('${added}')`) === '1', 'duplicate');
  });
  await check('free text / CSRF refused', async () => {
    const a = await page.evaluate(async () => (await fetch('?page=word-add', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ word: '<script>x</script>', csrf_token: window.__WB__.csrf }) })).json());
    assert(!a.success && a.error === 'word_not_in_bank', JSON.stringify(a));
    const b = await page.evaluate(async () => (await fetch('?page=word-add', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ word: 'manzana' }) })).json());
    assert(!b.success, 'no CSRF accepted');
  });
  await check('Saved list shows it on the flashcards page', async () => {
    await page.goto(BASE + '?page=flashcards'); await page.waitForTimeout(500);
    const saved = await page.evaluate(() => window.__FC_CONFIG__.lists[0]);
    assert(saved.cards === 1, JSON.stringify(saved));
    noErr();
  });
  await check('pack panel counts the single word', async () => {
    const t = await page.locator('#fc-packs').innerText().catch(() => '');
    assert(/\+\d+/.test(t), 'pack button should show remaining count: ' + t.replace(/\s+/g, ' ').slice(0, 120));
  });
  await check('sidebar word list keeps a usable height on a short screen', async () => {
    await page.setViewportSize({ width: 1366, height: 600 }); await page.waitForTimeout(300);
    const h = await page.evaluate(() => document.getElementById('words-list').clientHeight);
    assert(h >= 190, 'height ' + h);
    await page.setViewportSize({ width: 1366, height: 860 });
    return h + 'px';
  });
  await page.goto(BASE + '?page=words'); await page.waitForTimeout(400);
  await page.screenshot({ path: `${OUT}/words-desk.png` });

  await check('phone layout: rows as cards, no overflow', async () => {
    await page.setViewportSize({ width: 390, height: 844 }); await page.reload(); await page.waitForTimeout(500);
    const ov = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    assert(ov <= 2, 'overflow ' + ov);
    await page.screenshot({ path: `${OUT}/words-phone.png` });
    noErr();
  });

  // Every UI language renders the page (meanings in that language).
  for (const [native, target] of [['de', 'en'], ['ar', 'fr'], ['ru', 'es'], ['hy', 'en'], ['ja', 'zh']]) {
    await check(`page in ${native} UI learning ${target}`, async () => {
      sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
      const u = await newUser(browser, { viewport: { width: 1366, height: 860 } }, native, target);
      const e2 = []; u.page.on('pageerror', e => e2.push(e.message));
      await u.page.goto(BASE + '?page=words'); await u.page.waitForTimeout(500);
      const [hl, dir] = await u.page.evaluate(() => [document.documentElement.lang, document.documentElement.dir]);
      assert(hl === native, 'lang ' + hl); assert((native === 'ar') === (dir === 'rtl'), 'dir ' + dir);
      const n = await u.page.locator('#wb-count').innerText();
      const meaning = await u.page.locator('.fc-words-meaning').first().innerText();
      const text = await u.page.evaluate(() => document.body.innerText);
      assert(!/\bfc\.[a-z_]+/.test(text), 'raw key');
      assert(!e2.length, e2.join('|'));
      await u.ctx.close();
      return `${n} · first meaning "${meaning}"`;
    });
  }

  await check('API: GET words + POST words', async () => {
    sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
    const A = 'http://127.0.0.1:8090/api/v1/';
    const j = (r) => r.json();
    const reg = await apiSignUp(A, { name: 'Api Wb', email: `apiwb-${Date.now()}@example.test`, password: 'Api-wb-pass-1', accept_terms: true });
    const H = { 'Content-Type': 'application/json', Authorization: 'Bearer ' + reg.data.token };
    await fetch(A + 'onboarding', { method: 'POST', headers: H, body: JSON.stringify({ native_lang: 'ru', target_lang: 'de' }) });
    const w = await fetch(A + 'words?lang=de', { headers: H }).then(j);
    assert(w.ok && w.data.words.length > 300, 'words ' + (w.data && w.data.words.length));
    const pick = w.data.words.find(x => !x.card_id);
    const add = await fetch(A + 'words?lang=de', { method: 'POST', headers: H, body: JSON.stringify({ word: pick.word }) }).then(j);
    assert(add.ok && add.data.card.list_ids, JSON.stringify(add).slice(0, 200));
    const bad = await fetch(A + 'words?lang=de', { method: 'POST', headers: H, body: JSON.stringify({ word: 'nope-not-a-word' }) });
    assert(bad.status === 404, 'bad word status ' + bad.status);
    const unauth = await fetch(A + 'words?lang=de'); assert(unauth.status === 401, 'unauth ' + unauth.status);
    return `${w.data.words.length} words; meaning for ru native: "${w.data.words[60].meaning}"`;
  });

  await browser.close();
  const f = results.filter(x => !x).length;
  console.log(`TOTAL ${results.length} PASS ${results.length - f} FAIL ${f} [words]`);
})();
