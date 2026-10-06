const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const BASE = process.env.BASE || 'https://jumplearner.com/';
const LANGS = ['en','de','fr','es','ar','ja','zh','ru','el','hi','hy'];
const PAGES = ['home','pricing','about','faq','blog','alphabet','login','register','forgot-password','privacy-policy'];
(async () => {
  const b = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined });
  let pass = 0, fails = [];
  for (const lang of LANGS) {
    const c = await b.newContext({ locale: lang }); await c.addCookies([{ name: 'jl_lang', value: lang, url: BASE }]);
    const p = await c.newPage(); let errs = [];
    p.on('pageerror', e => errs.push(e.message));
    for (const pg of PAGES) {
      errs = [];
      try {
        const r = await p.goto(BASE + '?page=' + pg, { waitUntil: 'domcontentloaded', timeout: 30000 }); await p.waitForTimeout(400);
        const html = await p.content(); const text = await p.evaluate(() => document.body.innerText);
        const [hl, dir] = await p.evaluate(() => [document.documentElement.lang, document.documentElement.dir]);
        const prob = [];
        if (r.status() >= 400) prob.push('HTTP ' + r.status());
        const minLen = ['login', 'register', 'forgot-password'].includes(pg) ? 60 : 200;
        if (text.trim().length < minLen) prob.push('blank-ish page');
        if (/Fatal error|Warning:|Uncaught/.test(html)) prob.push('PHP error');
        if (hl !== lang) prob.push('lang=' + hl);
        if ((lang === 'ar') !== (dir === 'rtl')) prob.push('dir=' + dir);
        if (errs.length) prob.push('JS: ' + errs.join('|').slice(0, 120));
        if (prob.length) fails.push(`${lang} ${pg}: ${prob.join(', ')}`); else pass++;
      } catch (e) { fails.push(`${lang} ${pg}: ${e.message.split('\n')[0]}`); }
    }
    await c.close();
  }
  const api = await (await b.newContext()).request.get(BASE + 'api/v1/config');
  console.log('api/v1/config', api.status(), (await api.text()).slice(0, 60));
  console.log(`PASS ${pass}  FAIL ${fails.length}`); fails.forEach(f => console.log('FAIL ' + f));
  await b.close();
})();
