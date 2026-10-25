// Screen recordings of the real site for the homepage (public/media/home/).
// Every clip is the actual Jumplearner interface, driven like a learner
// would — nothing is mocked up. Run against local staging with a real
// Gemini key (GEMINI_FAKE unset) and a signed-up user that has a plan:
//
//   BASE=http://127.0.0.1:8095 USER_EMAIL=... USER_PASS=... node scripts/media/record_home.js [scene ...]
//
// Then scripts/media/encode.sh turns the raw .webm files into small .mp4
// clips with a poster frame.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
const path = require('path');

const BASE = (process.env.BASE || 'http://127.0.0.1:8095').replace(/\/$/, '');
const OUT = process.env.OUT || '/tmp/jl-rec';
const SIZE = { width: 1280, height: 800 };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

async function typeSlow(page, sel, text) {
  await page.click(sel);
  await page.keyboard.type(text, { delay: 55 });
}

async function moveTo(page, locator) {
  const box = await locator.boundingBox();
  if (box) await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 18 });
}

const scenes = {
  async chat(page) {
    await page.goto(BASE + '/?page=chat');
    await page.waitForSelector('#chat-input');
    await wait(1200);
    await typeSlow(page, '#chat-input', 'Hola, yo es Alex y me gusta mucho aprender español.');
    await wait(500);
    const before = await page.locator('#chat-messages .message-row').count();
    await page.click('#btn-send');
    await page.waitForFunction((n) => document.querySelectorAll('#chat-messages .message-row').length >= n + 2, before, { timeout: 60000 });
    await wait(9000);
    await page.mouse.wheel(0, 500);
    await wait(2500);
  },
  async cards(page) {
    await page.goto(BASE + '/?page=flashcards');
    await page.waitForSelector('#btn-study');
    await wait(1500);
    await moveTo(page, page.locator('#btn-study'));
    await page.click('#btn-study');
    await wait(2000);
    await page.click('#fc-study-flip');
    await wait(2200);
    for (let i = 0; i < 3; i++) {
      await page.click('#fc-study-next');
      await wait(1500);
    }
    await page.click('#fc-study-flip');
    await wait(2500);
  },
  async words(page) {
    await page.goto(BASE + '/?page=words');
    await page.waitForSelector('#wb-rows');
    await wait(1500);
    await page.mouse.wheel(0, 300);
    await wait(1200);
    await typeSlow(page, '#wb-q', 'casa');
    await wait(1800);
    const add = page.locator('#wb-rows .fc-words-add').first();
    if (await add.count()) {
      await moveTo(page, add);
      await add.click();
    }
    await wait(2500);
  },
  // A deck of your own: create a list, add a word to it, rename it.
  async lists(page) {
    await page.goto(BASE + '/?page=flashcards');
    await page.waitForSelector('#fc-list-nav');
    await wait(1500);
    const btn = page.locator('[data-new-list]').first();
    await moveTo(page, btn);
    await btn.click();
    await wait(700);
    await page.keyboard.type('Travel', { delay: 90 });
    await wait(500);
    await page.keyboard.press('Enter');
    await wait(2200);
    await moveTo(page, page.locator('#btn-new-card'));
    await page.click('#btn-new-card');
    await wait(800);
    await typeSlow(page, '#fc-form input[name=word]', 'aeropuerto');
    await typeSlow(page, '#fc-form input[name=translation]', 'airport');
    await wait(500);
    await page.locator('#fc-form [type=submit]').click();
    await wait(2200);
    await moveTo(page, page.locator('#btn-list-rename'));
    await page.click('#btn-list-rename');
    await wait(700);
    await page.keyboard.press('End');
    await page.keyboard.type(' 2026', { delay: 110 });
    await page.keyboard.press('Enter');
    await wait(2800);
  },
  async mistakes(page) {
    await page.goto(BASE + '/?page=mistakes');
    await wait(2500);
    const start = page.locator('#mis-start-btn');
    if (await start.isVisible().catch(() => false)) {
      await moveTo(page, start);
      await start.click();
      await wait(2000);
      if (await page.locator('#mis-input').isVisible().catch(() => false)) {
        await typeSlow(page, '#mis-input', 'yo soy Alex');
        await wait(600);
        await page.keyboard.press('Enter');
        await wait(3500);
      }
    } else {
      await page.mouse.wheel(0, 300);
      await wait(3000);
    }
  },
  async alphabet(page) {
    await page.goto(BASE + '/?page=alphabet&target=es');
    await page.waitForSelector('.alphabet-card');
    await wait(1500);
    const cards = page.locator('.alphabet-card');
    for (const i of [0, 3, 13]) {
      await moveTo(page, cards.nth(i));
      await cards.nth(i).click();
      await wait(1400);
    }
    await page.mouse.wheel(0, 400);
    await wait(2000);
  },
  async stories(page) {
    await page.goto(BASE + '/stories?lang=es');
    await page.waitForSelector('.st-card');
    await wait(1800);
    const card = page.locator('.st-card', { hasText: 'Hamelín' }).first();
    await moveTo(page, card);
    await card.click();
    await page.waitForSelector('.st-tok');
    await wait(1500);
    await moveTo(page, page.locator('#st-play'));
    await page.click('#st-play');
    await wait(5000);
    await page.click('#st-play');
    const toks = page.locator('.st-tok');
    await moveTo(page, toks.nth(4));
    await toks.nth(4).click();
    await wait(2200);
    if (await page.locator('#st-d-save').isVisible().catch(() => false)) {
      await page.click('#st-d-save');
      await wait(1800);
    }
    await page.click('#st-d-close');
    await wait(800);
    const de = page.locator('.st-mean[data-lang="de"]');
    if (await de.count()) { await moveTo(page, de); await de.click(); await wait(1600); }
    await page.click('#st-tab-text');
    await wait(2500);
  },
};

(async () => {
  const pick = process.argv.slice(2);
  const run = pick.length ? pick : Object.keys(scenes);
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  // Sign in once, without recording.
  const login = await browser.newContext({ viewport: SIZE, locale: 'en-US' });
  await login.addCookies([{ name: 'cookie_consent', value: '1', url: BASE }]);
  const lp = await login.newPage();
  await lp.goto(BASE + '/?page=login');
  await lp.fill('input[name=email]', process.env.USER_EMAIL);
  await lp.fill('input[name=password]', process.env.USER_PASS);
  await Promise.all([lp.waitForNavigation(), lp.click('form button[type=submit]')]);
  const state = await login.storageState();
  await login.close();

  for (const name of run) {
    const dir = path.join(OUT, name);
    fs.rmSync(dir, { recursive: true, force: true });
    const ctx = await browser.newContext({ viewport: SIZE, locale: 'en-US', storageState: state, recordVideo: { dir, size: SIZE } });
    const page = await ctx.newPage();
    try {
      await scenes[name](page);
      console.log('ok', name);
    } catch (e) {
      console.log('FAILED', name, e.message.split('\n')[0]);
      await page.screenshot({ path: path.join(OUT, name + '-error.png') });
    }
    await ctx.close();
  }
  await browser.close();
})();
