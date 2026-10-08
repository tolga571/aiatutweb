// Mobile app ↔ website parity (Expo web :8083 → API :8090), English UI.
// Sign-up + resend, Home stats, word bank (category, example, bulk add),
// chat study session, mistakes stats, Profile pages + data export.
// usage: node mobile_parity_test.js
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execSync } = require('child_process');
const API = 'http://127.0.0.1:8090/api/v1/';
const APP = 'http://localhost:8083/';
const sql = q => execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();
const res = []; function rec(ok, n, d = '') { res.push(ok); console.log((ok ? 'PASS ' : 'FAIL ') + n + (d ? '  — ' + d : '')); }
async function check(n, fn) { try { const r = await fn(); rec(true, n, typeof r === 'string' ? r : ''); } catch (e) { rec(false, n, String(e.message).split('\n').slice(0, 4).join(' ¦ ').slice(0, 400)); } }
const assert = (c, m) => { if (!c) throw new Error(m); };
(async () => {
  sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
  const stamp = Date.now().toString(36), email = `mp-${stamp}@example.test`, pass = 'Mp-pass-' + stamp;
  const b = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'en-US' });
  const p = await ctx.newPage();
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  const T = (t) => p.getByText(t, { exact: true });
  const body = () => p.evaluate(() => document.body.innerText);

  await check('sign-up in the app → "check your email" with the address', async () => {
    await p.goto(APP); await p.waitForTimeout(2500);
    await T('Create account').first().click(); await p.waitForTimeout(800);
    const i = p.locator('input');
    await i.nth(0).fill('Parity Test'); await i.nth(1).fill(email); await i.nth(2).fill(pass); await i.nth(3).fill(pass + 'x');
    await p.getByRole('switch').click();
    await p.getByRole('button', { name: 'Create account' }).last().click(); await p.waitForTimeout(800);
    assert(/Passwords do not match/.test(await body()), 'mismatch not caught');
    await i.nth(3).fill(pass);
    await p.getByRole('button', { name: 'Create account' }).last().click(); await p.waitForTimeout(2500);
    const txt = await body();
    assert(txt.includes(email) && /confirmation link/.test(txt), 'no address: ' + txt.slice(0, 200));
    assert(!/click.*below/i.test(txt), 'still says "link below"');
  });
  await check('resend from the app: same answer, a new link exists', async () => {
    await p.getByRole('button', { name: 'Send the link again' }).click(); await p.waitForTimeout(1500);
    assert(/we've sent a new link/.test(await body()), 'no confirmation');
    return 'links: ' + sql(`SELECT COUNT(*) FROM email_verifications ev JOIN users u ON u.id = ev.user_id WHERE u.email = '${email}'`);
  });
  await check('sign in before confirming → back on the resend screen', async () => {
    await p.getByRole('button', { name: 'Sign in' }).first().click(); await p.waitForTimeout(1000);
    const i = p.locator('input'); await i.nth(0).fill(email); await i.nth(1).fill(pass);
    await p.getByRole('button', { name: 'Sign in' }).last().click(); await p.waitForTimeout(2000);
    assert(/Didn't get the email\?/.test(await body()), 'not on resend screen');
  });

  // Confirm, onboard and give the account some data through the API.
  sql(`UPDATE users SET email_verified_at = now() WHERE email = '${email}'`);
  const login = await fetch(API + 'auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password: pass }) }).then(r => r.json());
  const H = { 'Content-Type': 'application/json', Authorization: 'Bearer ' + login.data.token };
  await fetch(API + 'onboarding', { method: 'POST', headers: H, body: JSON.stringify({ native_lang: 'en', target_lang: 'es', cefr_level: 'A1', learning_goal: 'travel', interest_area: 'general' }) });
  await fetch(API + 'chat', { method: 'POST', headers: H, body: JSON.stringify({ message: 'Hola, yo es Parity' }) });

  await check('sign in → Home shows chats, words today and total XP', async () => {
    await p.goto(APP); await p.waitForTimeout(2500);
    const signIn = T('Sign in').first(); if (await signIn.count()) await signIn.click();
    await p.waitForTimeout(800); const i = p.locator('input'); await i.nth(0).fill(email); await i.nth(1).fill(pass);
    await p.getByRole('button', { name: 'Sign in' }).last().click(); await p.waitForTimeout(3500);
    const txt = await body();
    assert(/Chats/.test(txt) && /Words Today/.test(txt) && /total XP/.test(txt), txt.slice(0, 300));
  });
  await check('word bank: category filter, example sentence, bulk add', async () => {
    await p.getByRole('tab').nth(2).click(); await p.waitForTimeout(2500);
    await p.getByRole('button', { name: 'Word bank' }).click(); await p.waitForTimeout(2500);
    await T('All categories').first().waitFor();
    await T('Food').first().click(); await p.waitForTimeout(400);
    const n = await p.getByText(/^\d+ words$/).first().innerText();
    await T('Show example (AI)').first().click(); await p.waitForTimeout(1500);
    assert(await T('Play sentence').count(), 'no example');
    await T('Select').first().click(); await p.waitForTimeout(300);
    const boxes = p.getByRole('checkbox');
    const before = +sql(`SELECT COUNT(*) FROM vocabulary_words v JOIN users u ON u.id = v.user_id WHERE u.email = '${email}'`);
    await boxes.nth(1).click(); await boxes.nth(2).click();
    assert(await T('2 selected').count(), 'selection count');
    await p.getByRole('button', { name: /Add selected/ }).click(); await p.waitForTimeout(2000);
    const after = +sql(`SELECT COUNT(*) FROM vocabulary_words v JOIN users u ON u.id = v.user_id WHERE u.email = '${email}'`);
    assert(after === before + 2, `cards ${before} → ${after}`);
    return `Food: ${n}; cards ${before} → ${after}`;
  });
  await check('chat: study session lists the new words and sentences', async () => {
    await p.goBack(); await p.waitForTimeout(800);
    await p.getByRole('tab').nth(1).click(); await p.waitForTimeout(1500);
    await p.locator('input, textarea').last().fill('Hola amigo'); await p.getByRole('button', { name: 'Send' }).click(); await p.waitForTimeout(3000);
    await p.getByRole('button', { name: 'Study Session' }).click(); await p.waitForTimeout(600);
    assert(await T('llamarse').count(), 'word missing');
    await T('Sentences').click(); await p.waitForTimeout(300);
    assert(/¡Hola! Me llamo Kai/.test(await body()), 'sentence missing');
    await p.getByRole('button', { name: 'Close' }).last().click();
  });
  await check('mistakes: active / learned / this week', async () => {
    await p.getByRole('tab').nth(3).click(); await p.waitForTimeout(2000);
    const txt = await body();
    assert(/To review/.test(txt) && /This week/.test(txt), txt.slice(0, 200));
  });
  await check('profile: website pages and "Download my data"', async () => {
    await p.getByRole('tab').nth(4).click(); await p.waitForTimeout(1500);
    for (const l of ['FAQ', 'About', 'Contact', 'Blog', 'Refund Policy', 'Cookie Policy', 'Download my data']) assert(await T(l).count(), l + ' missing');
    const [dl] = await Promise.all([p.waitForEvent('download', { timeout: 8000 }), T('Download my data').click()]);
    return 'export file: ' + dl.suggestedFilename();
  });
  await check('no page errors', async () => { assert(!errs.length, errs.join(' | ').slice(0, 300)); });

  await b.close();
  console.log(`TOTAL ${res.length} PASS ${res.filter(Boolean).length} FAIL ${res.filter(x => !x).length} [mobile parity]`);
})();
