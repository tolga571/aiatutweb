#!/usr/bin/env python3
"""
Writes hsk_meanings.tsv into the HSK cards of data/vocab/zh.json: a corrected
English meaning plus de, fr, es, ar, ja, ru, el, hi, hy. The original HSK
glosses were a dictionary's first sense (对不起 "unworthy", 喂 "to feed",
十分 "to divide into ten parts"), not the sense the word has at its level.

  python3 scripts/vocab/add_hsk_meanings.py --check   # verify only
  python3 scripts/vocab/add_hsk_meanings.py           # verify, then write

Run after build_vocab.py (which rebuilds zh.json with English-only HSK glosses).
"""
import json
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ZH = os.path.join(os.path.dirname(os.path.dirname(HERE)), 'data', 'vocab', 'zh.json')
COLS = ['en', 'de', 'fr', 'es', 'ar', 'ja', 'ru', 'el', 'hi', 'hy']

rows = {}
for n, line in enumerate(open(os.path.join(HERE, 'hsk_meanings.tsv'), encoding='utf-8'), 1):
    if line.startswith('#') or not line.strip():
        continue
    f = line.rstrip('\n').split('\t')
    if len(f) != 2 + len(COLS) or any(not x.strip() for x in f):
        sys.exit(f'line {n}: expected {2 + len(COLS)} non-empty columns, got {len(f)}')
    rows[int(f[0])] = (f[1], dict(zip(COLS, f[2:])))

cards = json.load(open(ZH, encoding='utf-8'))
hsk = [c for c in cards if c.get('category') == 'HSK']
problems = []
if len(rows) != len(hsk):
    problems.append(f'{len(rows)} rows for {len(hsk)} HSK cards')
for i, c in enumerate(hsk):
    if i not in rows:
        problems.append(f'no row for #{i} {c["word"]}')
    elif rows[i][0] != c['word']:
        problems.append(f'#{i}: row says {rows[i][0]}, card is {c["word"]}')
if problems:
    sys.exit('PROBLEMS:\n' + '\n'.join(problems[:20]))
changed_en = sum(1 for i, c in enumerate(hsk) if c['translations'].get('en') != rows[i][1]['en'])
print(f'{len(hsk)} HSK cards match; English meaning corrected on {changed_en}')
if '--check' in sys.argv:
    sys.exit(0)

for i, c in enumerate(hsk):
    c['translations'] = {'en': rows[i][1]['en'], **{l: rows[i][1][l] for l in COLS[1:]}}
with open(ZH, 'w', encoding='utf-8') as f:
    f.write(json.dumps(cards, ensure_ascii=False, separators=(',', ':')))
print('wrote', ZH)
