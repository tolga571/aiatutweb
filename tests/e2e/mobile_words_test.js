// Mobile app word bank (Expo web :8083 → API :8090). usage: node mobile_words_test.js
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execSync } = require('child_process');
const API = 'http://127.0.0.1:8090/api/v1/';
const sql = q => execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();
const res = []; function rec(ok, n, d = '') { res.push(ok); console.log((ok ? 'PASS ' : 'FAIL ') + n + (d ? '  — ' + d : '')); }
async function check(n, fn) { try { const r = await fn(); rec(true, n, typeof r === 'string' ? r : ''); } catch (e) { rec(false, n, String(e.message).split('\n').slice(0, 4).join(' ¦ ').slice(0, 400)); } }
const assert = (c, m) => { if (!c) throw new Error(m); };
(async () => {
  sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
  const stamp = Date.now().toString(36), email = `mw-${stamp}@example.test`, pass = 'Mw-pass-' + stamp;
  const reg = await fetch(API + 'auth/register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: 'Mw', email, password: pass, accept_terms: true }) }).then(r => r.json());
  await fetch(API + 'onboarding', { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + reg.data.token }, body: JSON.stringify({ native_lang: 'ru', target_lang: 'en', cefr_level: 'A1' }) });
  const b = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  const p = await (await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true })).newPage();
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  const T = (t) => p.getByText(t, { exact: true });
  await check('sign in (Russian UI)', async () => {
    await p.goto('http://localhost:8083/'); await p.waitForTimeout(2500);
    const signIn = p.getByText(/Sign in|Войти/).first(); if (await signIn.count()) await signIn.click();
    await p.waitForTimeout(800); const i = p.locator('input'); await i.nth(0).fill(email); await i.nth(1).fill(pass);
    await p.getByRole('button', { name: /^(Sign in|Войти)$/ }).last().click(); await p.waitForTimeout(3500);
    assert(await p.getByRole('tab').count(), 'no tabs');
  });
  await check('open word bank from Cards', async () => {
    await p.getByRole('tab').nth(2).click(); await p.waitForTimeout(2500);
    await p.getByRole('button', { name: 'Банк слов' }).click(); await p.waitForTimeout(2500);
    assert(await T('Банк слов').count(), 'title missing');
    const c = await p.getByText(/^Слов: \d+/).first().innerText(); return c;
  });
  await check('meanings shown in Russian (starter + pack)', async () => {
    const txt = await p.evaluate(() => document.body.innerText);
    assert(/Привет/.test(txt), 'starter meaning not Russian');
    await p.getByPlaceholder('Поиск по словам или значениям…').fill('dog'); await p.waitForTimeout(500);
    const t2 = await p.evaluate(() => document.body.innerText);
    return /собака/.test(t2) ? 'dog → собака' : 'dog meaning: ' + (t2.match(/dog[\s\S]{0,60}/) || [''])[0].replace(/\s+/g, ' ');
  });
  await check('add a word → card in Saved', async () => {
    await p.getByPlaceholder('Поиск по словам или значениям…').fill(''); await p.waitForTimeout(300);
    await p.getByRole('checkbox').click(); await p.waitForTimeout(300);
    const btn = p.getByRole('button', { name: /^Добавить: / }).first();
    const word = (await btn.getAttribute('aria-label')).replace('Добавить: ', '');
    await btn.click(); await p.waitForTimeout(1500);
    const db = sql(`select l.is_default from card_list_items i join card_lists l on l.id=i.list_id join vocabulary_words v on v.id=i.vocab_id join users u on u.id=v.user_id where u.email='${email}' and v.word='${word.replace(/'/g, "''")}'`);
    assert(db === 't' || db === 'true', 'db: ' + db);
    assert(!errs.length, errs.join('|'));
    await p.screenshot({ path: 'mobile-words.png' });
    return word;
  });
  await b.close();
  const f = res.filter(x => !x).length; console.log(`TOTAL ${res.length} PASS ${res.length - f} FAIL ${f} [mobile words]`);
})();
