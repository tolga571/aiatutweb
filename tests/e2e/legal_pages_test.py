# Legal pages Google Play reviews (privacy, terms, cookies, account deletion) in
# all 11 UI languages: follows the ui_lang redirect, then fails on raw keys,
# unfilled placeholders, PHP errors, wrong lang/dir, missing text or a stale
# Paddle mention. usage: python3 tests/e2e/legal_pages_test.py [BASE] [langdir]
import subprocess, re, sys, html, json, os, tempfile
BASE = sys.argv[1] if len(sys.argv) > 1 else os.environ.get('BASE', 'http://127.0.0.1:8090/')
LANGDIR = sys.argv[2] if len(sys.argv) > 2 else 'lang'
pages = ['delete-account', 'privacy-policy', 'terms-and-conditions', 'cookie-policy']
must = {  # a key whose translated text must appear on the page
  'delete-account': ['delacc.heading', 'delacc.kept_1', 'delacc.subs_title'],
  'privacy-policy': ['privacy.s1_5', 'privacy.s5_title', 'privacy.s6_body', 'privacy.updated'],
  'terms-and-conditions': ['terms.age_title', 'terms.age_body'],
  'cookie-policy': ['cookie.s2_3'],
}
fails = 0; n = 0
for lang in ['en', 'de', 'fr', 'es', 'ru', 'el', 'ar', 'ja', 'zh', 'hi', 'hy']:
    strings = json.load(open(os.path.join(LANGDIR, lang + '.json'), encoding='utf-8'))
    jar = tempfile.mktemp()
    for p in pages:
        n += 1
        out = subprocess.run(['curl', '-s', '-L', '-c', jar, '-b', jar, '-w', '\n%{http_code}', f'{BASE}?page={p}&ui_lang={lang}'], capture_output=True, text=True).stdout
        body, code = out.rsplit('\n', 1)
        text = html.unescape(re.sub(r'<[^>]+>', ' ', body))
        text = re.sub(r'\s+', ' ', text)
        probs = []
        if code != '200': probs.append('http ' + code)
        m = re.search(r'<html[^>]*>', body)
        if not m: probs.append('no <html>')
        else:
            want = 'rtl' if lang == 'ar' else 'ltr'
            if f'dir="{want}"' not in m.group(0): probs.append('dir: ' + m.group(0))
            if f'lang="{lang}"' not in m.group(0): probs.append('lang attr: ' + m.group(0))
        raw = set(re.findall(r'\b(?:delacc|privacy|terms|cookie|footer|account|mobile|nav)\.[a-z0-9_]+\b', text))
        if raw: probs.append('raw keys ' + str(sorted(raw)))
        ph = set(re.findall(r'\{(?:tab|button|dashboard|section)\}', body))
        if ph: probs.append('placeholders ' + str(ph))
        if re.search(r'(Warning|Fatal error|Notice|Deprecated):', body): probs.append('php error')
        for k in must[p]:
            plain = re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', strings[k]))).strip()
            frag = plain[:40]
            if frag not in text: probs.append('missing text for ' + k + ': ' + frag)
        if 'Paddle' in text and p != 'delete-account': probs.append('mentions Paddle')
        if probs:
            fails += 1
            print('FAIL', lang, p, probs)
    os.unlink(jar)
print(f'TOTAL {n}  FAIL {fails}')
sys.exit(1 if fails else 0)
