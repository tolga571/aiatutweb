// Card lists + study mode, end to end on local staging (web UI).
// usage: node lists_test.js <outDir> [phone]
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8090/';
const [, , OUT = '.', MODE = ''] = process.argv;
const phone = MODE === 'phone';
const sql = q => execSync(`psql ${process.env.PSQL_ARGS || '-h /tmp/jlpg -p 5433 -d aitut'} -qAtc "${q}"`).toString().trim();
// Sign-up ends on "check your email": confirm the address in the DB, then sign in.
async function signInVerified(page, email, pass) {
  sql(`UPDATE users SET email_verified_at = now() WHERE email = '${email}'`);
  await page.goto(BASE + '?page=login');
  await page.fill('#login-form input[name=email]', email); await page.fill('#login-form input[name=password]', pass);
  await Promise.all([page.waitForNavigation(), page.click('#login-submit-btn')]);
}
const results = [];
function rec(ok, name, d = '') { results.push({ ok, name, d }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (d ? '  — ' + d : '')); }
async function check(name, fn) { try { const r = await fn(); rec(true, name, typeof r === 'string' ? r : ''); } catch (e) { rec(false, name, String(e.message || e).split('\n').slice(0, 9).join(' ¦ ').slice(0, 900)); } }
function assert(c, m) { if (!c) throw new Error(m); }

(async () => {
  sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  const ctx = await browser.newContext(phone
    ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'en-US' }
    : { viewport: { width: 1366, height: 860 }, locale: 'en-US' });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', e => errs.push(e.message));
  page.on('response', r => { if (r.url().startsWith(BASE) && r.status() >= 500) errs.push(r.status() + ' ' + r.url()); });
  page.on('dialog', d => d.accept());
  const stamp = Date.now().toString(36);
  const email = `lists-${stamp}@example.test`, pass = 'Lists-pass-' + stamp;
  const noErr = () => { const e = errs.splice(0); assert(!e.length, e.join(' | ').slice(0, 200)); };
  const listCall = (body) => page.evaluate(async (b) => {
    const r = await fetch('?page=card-list', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ csrf_token: window.__FC_CONFIG__.csrf }, b)) });
    return r.json();
  }, body);

  await check('setup: register, onboarding (en → es), trial', async () => {
    await page.goto(BASE + '?page=register');
    await page.fill('input[name=name]', 'Lists User'); await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', pass); await page.fill('input[name=password_confirm]', pass);
    await page.check('#terms'); await page.click('#register-submit-btn'); await page.waitForLoadState();
    await signInVerified(page, email, pass);
    await page.evaluate(() => { selectLang('native', 'en', 'us', 'English'); selectLang('target', 'es', 'es', 'Spanish'); });
    for (const n of ['cefr_level', 'learning_goal', 'interest_area']) await page.locator(`input[name=${n}]`).first().check({ force: true });
    await Promise.all([page.waitForNavigation(), page.locator('#onboarding-form [type=submit]').click()]);
    await page.goto(BASE + '?page=start-trial');
    assert(/chat/.test(page.url()), page.url());
  });

  await page.goto(BASE + '?page=flashcards'); await page.waitForTimeout(800);
  await check('flashcards page: Saved list exists and is empty', async () => {
    const lists = await page.evaluate(() => window.__FC_CONFIG__.lists);
    assert(lists.length === 1 && lists[0].is_default && lists[0].label === 'Saved' && lists[0].cards === 0, JSON.stringify(lists));
    noErr();
  });

  await check('new card goes to Saved', async () => {
    await page.click('#btn-new-card');
    await page.fill('#fc-form [name=word]', 'manzana' + stamp); await page.fill('#fc-form [name=translation]', 'apple');
    await page.click('#fc-form [type=submit]'); await page.waitForTimeout(1000);
    const saved = await page.evaluate(() => window.__FC_CONFIG__.lists[0]);
    const nav = await page.locator('#fc-list-nav, .fc-list-chips').first().innerText();
    assert(/Saved\s*1/.test(nav.replace(/\n/g, ' ')), 'nav: ' + nav);
    noErr();
  });

  await check('create list "Food" from the sidebar/chips', async () => {
    await page.locator('[data-new-list]:visible').first().click();
    await page.fill('#fc-list-form [name=name]', 'Food ' + stamp);
    await Promise.all([page.waitForNavigation(), page.click('#fc-list-form [type=submit]')]);
    assert(/list=\d+/.test(page.url()), page.url());
    const h = await page.locator('#fc-list-name').innerText(); assert(h === 'Food ' + stamp, h);
    assert(await page.locator('#empty-deck').isVisible(), 'empty list should show empty state');
    noErr();
  });
  const foodId = +page.url().match(/list=(\d+)/)[1];

  await check('validation: empty name, too long, list limit message', async () => {
    const a = await listCall({ action: 'create', name: '   ' }); assert(!a.success && a.error === 'list_name_required', JSON.stringify(a));
    const b = await listCall({ action: 'create', name: 'x'.repeat(61) }); assert(!b.success && b.error === 'too_long', JSON.stringify(b));
    const c = await listCall({ action: 'rename', id: (await page.evaluate(() => window.__FC_CONFIG__.lists[0].id)), name: 'Nope' });
    assert(!c.success && c.error === 'list_default', JSON.stringify(c));
  });

  await check('"Save to…" on a card in All: tick Food, untick Saved', async () => {
    await page.goto(BASE + '?page=flashcards'); await page.waitForTimeout(800);
    const i = await page.$$eval('#cards-grid .grid-item', (els, s) => +els.find(e => e.textContent.includes(s)).dataset.idx, 'manzana' + stamp);
    await page.locator(`#grid-card-${i} [onclick^="fcSaveTo"]`).click();
    assert(await page.locator('#fc-save-modal').isVisible(), 'modal not open');
    const rows = await page.$$eval('#fc-save-list .fc-save-row', rs => rs.map(r => [r.innerText.trim(), r.querySelector('input').checked]));
    assert(rows.length === 2 && rows[0][1] === true && rows[1][1] === false, JSON.stringify(rows));
    await page.locator('#fc-save-list input').nth(1).check(); await page.waitForTimeout(700);
    await page.locator('#fc-save-list input').nth(0).uncheck(); await page.waitForTimeout(700);
    await page.locator('#fc-save-modal [data-close]').click();
    const db = sql(`select l.name || '|' || l.is_default from card_list_items i join card_lists l on l.id=i.list_id join vocabulary_words v on v.id=i.vocab_id where v.word='manzana${stamp}'`);
    assert(db === `Food ${stamp}|false`, 'db: ' + db);
    noErr();
  });

  await check('select many starter cards → Add to Food', async () => {
    await page.click('#btn-select');
    for (const n of [1, 2, 3]) await page.click(`#fc-inner-${n}`);
    const t = await page.locator('#fc-selbar-n').innerText(); assert(/3 selected/.test(t), t);
    await page.click('#fc-sel-save');
    await page.locator('#fc-save-list .fc-save-row', { hasText: 'Food ' + stamp }).locator('[data-act=add]').click();
    await page.waitForTimeout(900);
    const n = sql(`select count(*) from card_list_items where list_id=${foodId}`); assert(n === '4', 'food has ' + n);
    assert(!(await page.locator('#fc-selbar').isVisible()), 'selection bar should close');
    noErr();
  });

  await check('in Food: select 2, move them to a new list made in the dialog', async () => {
    await page.goto(BASE + '?page=flashcards&list=' + foodId); await page.waitForTimeout(800);
    assert(await page.locator('#cards-grid .grid-item').count() === 4, 'food view count');
    await page.click('#btn-select');
    await page.click('#fc-inner-0'); await page.click('#fc-inner-1');
    await page.click('#fc-sel-save');
    // make "Travel" with the selected cards (adds), then move remaining? — create adds; then move one via Move
    await page.fill('#fc-save-new [name=name]', 'Travel ' + stamp);
    await page.click('#fc-save-new [type=submit]'); await page.waitForTimeout(900);
    const travel = sql(`select id from card_lists where name='Travel ${stamp}'`);
    assert(sql(`select count(*) from card_list_items where list_id=${travel}`) === '2', 'travel should have 2');
    // now move 1 card from Food to Travel
    await page.click('#btn-select'); await page.click('#fc-inner-3');
    await page.click('#fc-sel-save');
    await page.locator('#fc-save-list .fc-save-row', { hasText: 'Travel ' + stamp }).locator('[data-act=move]').click();
    await page.waitForTimeout(900);
    assert(sql(`select count(*) from card_list_items where list_id=${foodId}`) === '3', 'food should have 3 after move');
    assert(sql(`select count(*) from card_list_items where list_id=${travel}`) === '3', 'travel should have 3 after move');
    assert(await page.locator('#cards-grid .grid-item').count() === 3, 'moved card should leave the Food view');
    noErr();
  });

  await check('remove from list keeps the card in the deck', async () => {
    const before = sql(`select count(*) from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}'`);
    await page.click('#btn-select'); await page.click('#fc-inner-0'); await page.click('#fc-sel-remove'); await page.waitForTimeout(800);
    assert(sql(`select count(*) from card_list_items where list_id=${foodId}`) === '2', 'food should have 2');
    const after = sql(`select count(*) from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}'`);
    assert(before === after, `cards ${before} → ${after}`);
    noErr();
  });

  await check('rename list', async () => {
    await page.click('#btn-list-rename');
    await page.fill('#fc-list-form [name=name]', 'Kitchen ' + stamp);
    await page.click('#fc-list-form [type=submit]'); await page.waitForTimeout(700);
    assert((await page.locator('#fc-list-name').innerText()) === 'Kitchen ' + stamp);
    assert(sql(`select name from card_lists where id=${foodId}`) === 'Kitchen ' + stamp);
    noErr();
  });

  await check('study mode: swipe/flip/next, grades go to SM-2', async () => {
    await page.click('#btn-study');
    assert(await page.locator('#fc-study').isVisible(), 'study not open');
    const c1 = await page.locator('#fc-study-count').innerText(); assert(c1 === '1 / 2', c1);
    // card 1: pass without flipping (knew)
    const w1 = await page.locator('#fc-study-word').innerText();
    await page.click('#fc-study-next'); await page.waitForTimeout(400);
    // card 2: flip (tap on the card), then next via keyboard
    const box = await page.locator('#fc-study-card').boundingBox();
    await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2); await page.waitForTimeout(500);
    assert(await page.locator('#fc-study-card.is-flipped').count() === 1, 'tap did not flip');
    const w2 = await page.locator('#fc-study-word').innerText();
    await page.keyboard.press('ArrowRight'); await page.waitForTimeout(1200);
    assert(await page.locator('#fc-study-done').isVisible(), 'done panel not shown');
    const body = await page.locator('#fc-study-done-body').innerText(); assert(/1 of 2/.test(body), body);
    const again = await page.locator('#fc-study-flipped').innerText(); assert(/\(1\)/.test(again), again);
    const q = (w) => sql(`select coalesce(uf.correct_count,0)||'/'||coalesce(uf.incorrect_count,0) from vocabulary_words v join user_flashcards uf on uf.vocab_id=v.id join users u on u.id=v.user_id where u.email='${email}' and v.word='${w.replace(/'/g, "''")}'`);
    const g1 = q(w1), g2 = q(w2);
    assert(g1 === '1/0' && g2 === '0/1', `grades ${w1}=${g1} ${w2}=${g2}`);
    noErr(); return `${w1}: knew, ${w2}: flipped`;
  });

  await check('study: repeat flipped → 1 card, Escape closes', async () => {
    await page.click('#fc-study-flipped'); await page.waitForTimeout(300);
    assert((await page.locator('#fc-study-count').innerText()) === '1 / 1');
    await page.keyboard.press('Escape'); await page.waitForTimeout(300);
    assert(!(await page.locator('#fc-study').isVisible()), 'still open');
    noErr();
  });

  await check('study: real touch-style drag swipes to next card', async () => {
    await page.click('#btn-study'); await page.waitForTimeout(300);
    const box = await page.locator('#fc-study-card').boundingBox();
    const y = box.y + box.height / 2;
    await page.mouse.move(box.x + box.width * 0.8, y); await page.mouse.down();
    for (let k = 1; k <= 8; k++) await page.mouse.move(box.x + box.width * 0.8 - k * 30, y);
    await page.mouse.up(); await page.waitForTimeout(500);
    const c = await page.locator('#fc-study-count').innerText(); assert(c === '2 / 2', 'after swipe: ' + c);
    assert(await page.locator('#fc-study-card.is-flipped').count() === 0, 'swipe should not flip');
    await page.keyboard.press('Escape');
    noErr();
  });

  await check('security: other user cannot see or touch these lists', async () => {
    const other = await browser.newContext(); const p2 = await other.newPage();
    const e2 = `lists2-${stamp}@example.test`;
    sql('DELETE FROM login_attempts; DELETE FROM trial_grants;');
    await p2.goto(BASE + '?page=register');
    await p2.fill('input[name=name]', 'Other User'); await p2.fill('input[name=email]', e2);
    await p2.fill('input[name=password]', pass); await p2.fill('input[name=password_confirm]', pass);
    await p2.check('#terms'); await p2.click('#register-submit-btn'); await p2.waitForLoadState();
    await signInVerified(p2, e2, pass);
    await p2.evaluate(() => { selectLang('native', 'en', 'us', 'English'); selectLang('target', 'es', 'es', 'Spanish'); });
    for (const n of ['cefr_level', 'learning_goal', 'interest_area']) await p2.locator(`input[name=${n}]`).first().check({ force: true });
    await Promise.all([p2.waitForNavigation(), p2.locator('#onboarding-form [type=submit]').click()]);
    await p2.goto(BASE + '?page=start-trial');
    await p2.goto(BASE + '?page=flashcards&list=' + foodId); await p2.waitForTimeout(500);
    const cfgList = await p2.evaluate(() => window.__FC_CONFIG__.listId); assert(cfgList === 0, 'opened foreign list');
    const call = (b) => p2.evaluate(async (b) => (await fetch('?page=card-list', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ csrf_token: window.__FC_CONFIG__.csrf }, b)) })).json(), b);
    const myCard = +sql(`select v.id from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}' and v.word='manzana${stamp}'`);
    for (const b of [{ action: 'rename', id: foodId, name: 'hacked' }, { action: 'delete', id: foodId }, { action: 'remove', id: foodId, cards: [myCard] }, { action: 'add', id: foodId, cards: [myCard] }]) {
      const r = await call(b); assert(!r.success, b.action + ' allowed: ' + JSON.stringify(r));
    }
    // their own list + someone else's card id → refused
    const mine = await call({ action: 'create', name: 'Mine' });
    const r = await call({ action: 'add', id: mine.list.id, cards: [myCard] }); assert(!r.success, 'added foreign card');
    assert(sql(`select name from card_lists where id=${foodId}`) === 'Kitchen ' + stamp, 'list changed');
    const noCsrf = await p2.evaluate(async () => (await fetch('?page=card-list', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'create', name: 'x' }) })).json());
    assert(!noCsrf.success, 'no-CSRF create allowed');
    await other.close();
  });

  await check('XSS: list name with markup renders as text', async () => {
    const r = await listCall({ action: 'create', name: '<img src=x onerror=window.__x=1>L' });
    assert(r.success, JSON.stringify(r));
    await page.goto(BASE + '?page=flashcards&list=' + r.list.id); await page.waitForTimeout(600);
    assert(!(await page.evaluate(() => window.__x)), 'XSS ran');
    assert((await page.locator('#fc-list-name').innerText()).includes('<img'), 'name not shown as text');
    noErr();
  });

  await check('delete list keeps cards', async () => {
    await page.goto(BASE + '?page=flashcards&list=' + foodId); await page.waitForTimeout(500);
    const before = sql(`select count(*) from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}'`);
    await Promise.all([page.waitForNavigation(), page.click('#btn-list-delete')]);
    assert(sql(`select count(*) from card_lists where id=${foodId}`) === '0', 'list still there');
    assert(sql(`select count(*) from vocabulary_words v join users u on u.id=v.user_id where u.email='${email}'`) === before, 'cards deleted');
    noErr();
  });

  await page.goto(BASE + '?page=flashcards'); await page.waitForTimeout(600);
  await page.screenshot({ path: `${OUT}/lists-${phone ? 'phone' : 'desk'}-all.png` });
  await page.click('#btn-study'); await page.waitForTimeout(400);
  await page.screenshot({ path: `${OUT}/lists-${phone ? 'phone' : 'desk'}-study.png` });
  const box = await page.locator('#fc-study-card').boundingBox();
  await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2); await page.waitForTimeout(600);
  await page.screenshot({ path: `${OUT}/lists-${phone ? 'phone' : 'desk'}-study-back.png` });
  await page.keyboard.press('Escape');
  const ov = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  rec(ov <= 2, 'no horizontal overflow', ov + 'px');

  await browser.close();
  const fails = results.filter(r => !r.ok);
  console.log(`TOTAL ${results.length} PASS ${results.length - fails.length} FAIL ${fails.length} [${phone ? 'phone' : 'desk'}]`);
  fs.writeFileSync(`${OUT}/lists-result.txt`, `${email}\n`);
})();
