#!/usr/bin/env python3
"""
Adds Russian, Greek, Hindi and Armenian to the vocabulary packs from
picks_extra.tsv (run after build_vocab.py):

  * every existing pack card gets ru/el/hi/hy meanings (matched by its English concept);
  * data/vocab/{ru,el,hi,hy}.json are built for learners of those languages.

  python3 scripts/vocab/add_langs.py --check   # verify only, write nothing
  python3 scripts/vocab/add_langs.py           # verify, then write

Checks against the local dictionary set (~/.cache/jl-analysis/ds, see
scripts/vocab/README.md); a missing dictionary just skips that check.
Armenian has no dictionary here — its words are listed for a human look.
"""
import json
import os
import sqlite3
import sys
import unicodedata

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
OUT = os.path.join(ROOT, 'data', 'vocab')
DS = os.path.expanduser('~/.cache/jl-analysis/ds')
NEW = ['ru', 'el', 'hi', 'hy']
OLD = ['en', 'es', 'de', 'fr', 'zh', 'ja', 'ar']


def tsv(name):
    rows = []
    for line in open(os.path.join(HERE, name), encoding='utf-8'):
        if line.startswith('#') or not line.strip():
            continue
        rows.append(line.rstrip('\n').split('\t'))
    return rows


def ro(path):
    return sqlite3.connect(f'file:{path}?mode=ro', uri=True) if os.path.exists(path) else None


def plain(s):
    """Lowercase, no stress marks, ё→е — how the dictionaries spell headwords."""
    s = unicodedata.normalize('NFD', s.lower())
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')
    return unicodedata.normalize('NFC', s).replace('ё', 'е')


concepts = {r[0]: {'pos': r[1], 'category': r[2], 'level': r[3]} for r in tsv('concepts.tsv')}
old_picks = {r[0]: dict(zip(OLD[1:], r[1:])) for r in tsv('picks.tsv')}
extra = {}
for r in tsv('picks_extra.tsv'):
    if len(r) != 9:
        sys.exit(f'bad row ({len(r)} columns): {r}')
    extra[r[0]] = {'ru': (r[1], r[2]), 'el': (r[3], r[4]), 'hi': (r[5], r[6]), 'hy': (r[7], r[8])}

problems = []
missing = sorted(set(concepts) - set(extra))
unknown = sorted(set(extra) - set(concepts))
if missing or unknown:
    problems.append(f'concepts missing: {missing} / not a concept: {unknown}')
for en, langs in extra.items():
    for l, (w, p) in langs.items():
        if not w.strip() or not p.strip():
            problems.append(f'{en}: empty {l}')

# Russian: headword (or each word of a phrase) in the rtalk dictionary.
report = {'ru': [], 'el': [], 'hi': [], 'hy': []}
db = ro(os.path.join(DS, 'data-russian', 'rtalk.db'))
if db:
    have = {plain(w) for (w,) in db.execute('SELECT word FROM offline_dictionary')}
    have |= {plain(w) for (w,) in db.execute('SELECT lemma FROM offline_dictionary WHERE lemma IS NOT NULL')}
    for en, langs in extra.items():
        words = [x for x in langs['ru'][0].split() if x not in ('в',)]
        if not all(plain(x) in have for x in words):
            report['ru'].append(f'{en} → {langs["ru"][0]}')
# Greek: headword or lemma in the European dictionary.
db = ro(os.path.join(DS, 'data-european', 'dictionary.sqlite'))
if db:
    have = {plain(w) for (w,) in db.execute("SELECT word FROM words WHERE lang = 'el'")}
    have |= {plain(w) for (w,) in db.execute("SELECT lemma FROM words WHERE lang = 'el'")}
    for en, langs in extra.items():
        if not all(plain(x) in have for x in langs['el'][0].split()):
            report['el'].append(f'{en} → {langs["el"][0]}')
# Hindi: does the English→Hindi bond table agree with the pick?
db = ro(os.path.join(DS, 'translations-data', 'database.sqlite'))
agree = 0
if db:
    for en, langs in extra.items():
        # The table pivots through Arabic: English ↔ Arabic ↔ Hindi.
        hits = {w for (w,) in db.execute(
            """WITH bond AS (
                   SELECT source_word_id AS a, target_word_id AS b FROM translation_bonds
                   UNION ALL SELECT target_word_id, source_word_id FROM translation_bonds),
               ar AS (SELECT x.id FROM words e JOIN bond ON bond.a = e.id JOIN words x ON x.id = bond.b
                      WHERE e.language_code = 'en' AND e.normalized_word = ? AND x.language_code = 'ar')
               SELECT DISTINCT h.word FROM ar JOIN bond ON bond.a = ar.id JOIN words h ON h.id = bond.b
               WHERE h.language_code = 'hi'""", (en,))}
        if langs['hi'][0] in hits:
            agree += 1
        elif hits:
            report['hi'].append(f'{en} → {langs["hi"][0]} (table: {", ".join(sorted(hits)[:3])})')
        else:
            report['hi'].append(f'{en} → {langs["hi"][0]} (not in table)')
report['hy'] = [f'{en} → {langs["hy"][0]} ({langs["hy"][1]})' for en, langs in extra.items()]

print(f'concepts: {len(concepts)}, rows: {len(extra)}')
for l in ['ru', 'el']:
    print(f'{l}: {len(extra) - len(report[l])}/{len(extra)} found in the dictionary; not found: {report[l]}')
print(f'hi: {agree}/{len(extra)} agree with the bond table; others: {len(report["hi"])}')
for x in report['hi']:
    print('   ', x)
if problems:
    sys.exit('PROBLEMS:\n' + '\n'.join(problems))
if '--check' in sys.argv:
    sys.exit(0)

# ── write ────────────────────────────────────────────────────────────
def load(lang):
    return json.load(open(os.path.join(OUT, f'{lang}.json'), encoding='utf-8'))


def save(lang, cards):
    with open(os.path.join(OUT, f'{lang}.json'), 'w', encoding='utf-8') as f:
        f.write(json.dumps(cards, ensure_ascii=False, separators=(',', ':')))


added = 0
for lang in OLD:
    cards = load(lang)
    for c in cards:
        en = (c.get('translations') or {}).get('en')
        if c.get('category') != 'HSK' and en in extra:
            for l in NEW:
                c['translations'][l] = extra[en][l][0]
                added += 1
    save(lang, cards)

starter = json.loads(os.popen(f"php -r 'echo json_encode(require \"{ROOT}/data/flashcards_data.php\");'").read())
manifest = json.load(open(os.path.join(OUT, 'manifest.json'), encoding='utf-8'))
for target in NEW:
    seen = {c['word'].lower() for c in starter.get(target, [])}
    cards = []
    for en, meta in concepts.items():
        word, pron = extra[en][target]
        if word.lower() in seen:
            continue  # already in the starter deck, or a second concept with the same word
        seen.add(word.lower())
        trans = {'en': en}
        for l in OLD[1:]:
            w = old_picks.get(en, {}).get(l, '-')
            if w and w != '-':
                trans[l] = w.split('|')[0]
        for l in NEW:
            trans[l] = extra[en][l][0]
        cards.append({'word': word, 'pronunciation': pron, 'category': meta['category'], 'level': meta['level'], 'translations': trans})
    save(target, cards)
    lv = {}
    for c in cards:
        lv[c['level']] = lv.get(c['level'], 0) + 1
    manifest[target] = lv
with open(os.path.join(OUT, 'manifest.json'), 'w', encoding='utf-8') as f:
    f.write(json.dumps(manifest, ensure_ascii=False, indent=1))
print(f'wrote {added} meanings into existing packs; new packs: ' + ', '.join(f'{l}={sum(manifest[l].values())}' for l in NEW))
