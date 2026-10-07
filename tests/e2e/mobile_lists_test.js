// Mobile app (Expo web build on :8083, API on :8090): card lists + study mode.
// usage: node mobile_lists_test.js <outDir>
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execSync } = require('child_process');
const APP = 'http://localhost:8083/';
const API = 'http://127.0.0.1:8090/api/v1/';
const [, , OUT = '.'] = process.argv;
const sql = q => execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();
// API sign-up returns no token until the email is confirmed: confirm it in the DB, then sign in.
async function apiSignUp(api, body) {
  const post = (p, b) => fetch(api + p, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) }).then(r => r.json());
  const reg = await post('auth/register', body);
  if (!reg.ok) return reg;
  sql(`UPDATE users SET email_verified_at = now() WHERE email = '${body.email}'`);
  return post('auth/login', { email: body.email, password: body.password });
}
const results = [];
function rec(ok, name, d = '') { results.push({ ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (d ? '  — ' + d : '')); }
async function check(name, fn) { try { const r = await fn(); rec(true, name, typeof r === 'string' ? r : ''); } catch (e) { rec(false, name, String(e.message || e).split('\n').slice(0, 6).join(' ¦ ').slice(0, 600)); } }
function assert(c, m) { if (!c) throw new Error(m); }

(async () => {
  sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
  const stamp = Date.now().toString(36);
  const email = `mob-${stamp}@example.test`, pass = 'Mob-pass-' + stamp;
  // Account made through the API the app uses; the UI test signs in with it.
  const reg = await apiSignUp(API, { name: 'Mob Test', email, password: pass, accept_terms: true });
  const tok = reg.data.token;
  await fetch(API + 'onboarding', { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + tok }, body: JSON.stringify({ native_lang: 'en', target_lang: 'es', cefr_level: 'A1' }) });

  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'en-US' });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', e => errs.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/favicon|DevTools|Download the React|ERR_/.test(m.text())) errs.push('console: ' + m.text().slice(0, 150)); });
  const noErr = () => { const e = errs.splice(0); assert(!e.length, e.join(' | ').slice(0, 300)); };
  const byText = (t) => page.getByText(t, { exact: true });

  await check('app sign-in', async () => {
    await page.goto(APP); await page.waitForTimeout(2500);
    if (await byText('Sign in').count()) await byText('Sign in').first().click();
    await page.waitForTimeout(800);
    const inputs = page.locator('input');
    await inputs.nth(0).fill(email); await inputs.nth(1).fill(pass);
    await page.getByRole('button', { name: 'Sign in', exact: true }).last().click();
    await page.waitForTimeout(3000);
    assert(await page.getByRole('tab').count() > 0, 'tabs not shown after sign-in');
  });

  await check('cards tab shows My lists with Saved', async () => {
    await page.getByRole('tab').nth(2).click(); await page.waitForTimeout(2500);
    assert(await byText('MY LISTS').count() || await byText('My lists').count(), 'no lists label');
    assert(await page.getByRole('radio', { name: /Saved/ }).count(), 'no Saved chip');
    noErr();
  });

  await check('new list from the chips', async () => {
    await byText('New list').first().click(); await page.waitForTimeout(500);
    await page.getByPlaceholder('List name').fill('Animals ' + stamp);
    await page.getByRole('button', { name: 'Save', exact: true }).click(); await page.waitForTimeout(1500);
    assert(sql(`select count(*) from card_lists l join users u on u.id=l.user_id where u.email='${email}' and l.name='Animals ${stamp}'`) === '1', 'not in db');
    assert(await byText('This list is empty. Use the list button on any card to save it here.').count(), 'empty text missing');
    noErr();
  });

  await check('All tab: save a card to Animals with the list button', async () => {
    await page.getByRole('radio', { name: 'All cards' }).click(); await page.waitForTimeout(2000);
    await page.getByRole('button', { name: 'Save to list', exact: true }).first().click(); await page.waitForTimeout(600);
    await page.getByRole('checkbox', { name: new RegExp('Animals ' + stamp) }).click(); await page.waitForTimeout(1200);
    const checked = await page.getByRole('checkbox', { name: new RegExp('Animals ' + stamp) }).getAttribute('aria-checked');
    assert(checked === 'true', 'aria-checked=' + checked);
    await page.getByRole('button', { name: 'Done', exact: true }).last().click(); await page.waitForTimeout(400);
    const n = sql(`select count(*) from card_list_items i join card_lists l on l.id=i.list_id where l.name='Animals ${stamp}'`);
    assert(n === '1', 'items ' + n);
    noErr();
  });

  await check('select 3 → Save to list → Add to Animals', async () => {
    await byText('Select').first().click(); await page.waitForTimeout(400);
    const boxes = page.getByRole('button', { name: /./ }).filter({ has: page.locator('text=check_box_outline_blank') });
    // rows: tap the word text to toggle
    const rows = page.locator('[role=button]').filter({ hasText: /·/ });
    for (const i of [1, 2, 3]) await rows.nth(i).click();
    assert(await byText('3 selected').count(), 'count text');
    await page.getByRole('button', { name: /Save to list/ }).last().click(); await page.waitForTimeout(500);
    await page.getByRole('button', { name: 'Add', exact: true }).last().click(); await page.waitForTimeout(1500);
    const n = sql(`select count(*) from card_list_items i join card_lists l on l.id=i.list_id where l.name='Animals ${stamp}'`);
    assert(n === '4', 'items ' + n);
    noErr();
  });

  await check('open Animals: 4 cards; study mode flips and swipes; grades saved', async () => {
    await page.getByRole('radio', { name: new RegExp('Animals ' + stamp) }).click(); await page.waitForTimeout(2000);
    await byText('Study').first().click(); await page.waitForTimeout(1500);
    assert(await byText('1 / 4').count(), 'counter not 1/4');
    const w1 = (await page.getByRole('button', { name: /./ }).filter({ hasText: /\S/ }).nth(1).getAttribute('aria-label')) || '';
    await page.getByRole('button', { name: 'Next', exact: true }).click(); await page.waitForTimeout(700);           // knew
    assert(await byText('2 / 4').count(), 'counter not 2/4');
    await page.getByRole('button', { name: 'Flip', exact: true }).click(); await page.waitForTimeout(600);           // flip → didn't know
    // swipe left on the card
    const box = await page.locator('text=Translation').first().boundingBox();
    await page.mouse.move(300, 420); await page.mouse.down();
    for (let k = 1; k <= 10; k++) await page.mouse.move(300 - k * 25, 420);
    await page.mouse.up(); await page.waitForTimeout(900);
    assert(await byText('3 / 4').count(), 'swipe did not advance');
    await page.getByRole('button', { name: 'Next', exact: true }).click(); await page.waitForTimeout(600);
    await page.getByRole('button', { name: 'Next', exact: true }).click(); await page.waitForTimeout(900);
    assert(await byText('Round complete!').count(), 'no done screen');
    assert(await byText('You knew 3 of 4 without flipping.').count(), 'summary text');
    assert(await byText('Repeat the flipped ones (1)').count(), 'repeat button');
    await page.screenshot({ path: `${OUT}/mobile-study-done.png` });
    const g = sql(`select sum(coalesce(uf.correct_count,0))||'/'||sum(coalesce(uf.incorrect_count,0)) from user_flashcards uf join users u on u.id=uf.user_id where u.email='${email}'`);
    assert(g === '3/1', 'grades ' + g);
    noErr(); return w1;
  });

  await check('repeat flipped → 1 card, close', async () => {
    await byText('Repeat the flipped ones (1)').click(); await page.waitForTimeout(800);
    assert(await byText('1 / 1').count(), 'not 1/1');
    await page.getByRole('button', { name: 'Flip', exact: true }).click(); await page.waitForTimeout(600);
    await page.screenshot({ path: `${OUT}/mobile-study-back.png` });
    await page.getByRole('button', { name: 'Close', exact: true }).first().click(); await page.waitForTimeout(1500);
    noErr();
  });

  await check('in Animals: select 1 → Remove from list; card stays in deck', async () => {
    await byText('Select').first().click(); await page.waitForTimeout(300);
    await page.locator('[role=button]').filter({ hasText: /·/ }).first().click();
    await page.getByRole('button', { name: 'Remove from list', exact: true }).click(); await page.waitForTimeout(1200);
    assert(sql(`select count(*) from card_list_items i join card_lists l on l.id=i.list_id where l.name='Animals ${stamp}'`) === '3', 'not 3');
    noErr();
  });

  await check('rename then delete the list (two taps to confirm)', async () => {
    await page.getByRole('button', { name: 'Rename list', exact: true }).click(); await page.waitForTimeout(400);
    await page.getByPlaceholder('List name').fill('Pets ' + stamp);
    await page.getByRole('button', { name: 'Save', exact: true }).click(); await page.waitForTimeout(1200);
    assert(sql(`select count(*) from card_lists where name='Pets ${stamp}'`) === '1', 'rename failed');
    await page.getByRole('button', { name: 'Rename list', exact: true }).click(); await page.waitForTimeout(400);
    await page.getByRole('button', { name: 'Delete list', exact: true }).click(); await page.waitForTimeout(300);
    await page.getByRole('button', { name: /Delete this list/ }).click(); await page.waitForTimeout(1200);
    assert(sql(`select count(*) from card_lists where name='Pets ${stamp}'`) === '0', 'delete failed');
    await page.screenshot({ path: `${OUT}/mobile-cards.png` });
    noErr();
  });

  await browser.close();
  const f = results.filter(r => !r.ok).length;
  console.log(`TOTAL ${results.length} PASS ${results.length - f} FAIL ${f} [mobile]`);
})();
