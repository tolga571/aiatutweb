#!/usr/bin/env python3
"""Builds data/vocab/<lang>.json (the extra flashcard packs) from
  - concepts.tsv  : the concept list (English lemma, POS, category, CEFR level, Turkish)
  - picks.tsv     : hand-chosen word per concept per language
and VERIFIES / ENRICHES every pick against the downloaded dictionaries
(sqlite-data-languages): a pick that is not found in its dictionary is dropped and
reported, never guessed. Pronunciation comes from the dictionaries (es/de/fr IPA,
ja romaji, ar transliteration, zh pinyin); English IPA comes from CMUdict
(eng-to-ipa); Turkish is written by hand (no Turkish dictionary in the set).
The Chinese HSK 1-4 lists are added as extra packs (HSK1..4 -> A1..B2).

usage: build_vocab.py <data_dir> <out_dir> <existing flashcards_data.php as JSON>
"""
import csv, json, re, sqlite3, sys
from pathlib import Path

DATA, OUT, BASE = Path(sys.argv[1]), Path(sys.argv[2]), json.load(open(sys.argv[3], encoding="utf-8"))
HERE = Path(__file__).parent
sys.path.insert(0, str(HERE))
from candidates import pinyin_num_to_mark  # noqa: E402  (reuse)

LANGS = ["tr", "en", "de", "fr", "es", "zh", "ja", "ar"]
PICK_COLS = ["en", "es", "de", "fr", "zh", "ja", "ar"]


def ro(p):
    return sqlite3.connect(f"file:{p}?mode=ro", uri=True)


concepts = {}
for line in (HERE / "concepts.tsv").read_text(encoding="utf-8").splitlines():
    if line.strip() and not line.startswith("#"):
        en, pos, cat, level, tr = line.split("\t")
        concepts[en] = dict(en=en, pos=pos, category=cat, level=level, tr=tr)
picks = {}
for line in (HERE / "picks.tsv").read_text(encoding="utf-8").splitlines():
    if line.strip() and not line.startswith("#"):
        p = line.split("\t")
        picks[p[0]] = dict(zip(PICK_COLS, p))

# ── dictionaries ────────────────────────────────────────────────────────
dic = ro(DATA / "data-european/dictionary.sqlite")
src = {l: ro(DATA / f"data-european/sources/{l}-en.sqlite3") for l in ("es", "de", "fr")}
ja = ro(DATA / "data-japanese/dictionary.db")
ar = ro(DATA / "data-arabic/atalk_full_vocabulary.sqlite")

hsk = {}
for lv in range(1, 7):
    with open(DATA / f"data-chinese/hsk/hsk{lv}.csv", encoding="utf-8") as f:
        for w, py, meaning in csv.reader(f):
            hsk.setdefault(w, (lv, py, meaning))
cedict = {}
for line in (DATA / "data-chinese/cedict_ts.u8").read_text(encoding="utf-8").splitlines():
    if line.startswith("#"):
        continue
    m = re.match(r"^(\S+) (\S+) \[([^\]]+)\] /(.*)/$", line)
    if m:
        cedict.setdefault(m.group(2), pinyin_num_to_mark(m.group(3)))

try:
    import eng_to_ipa
except ImportError:
    eng_to_ipa = None

report = {"dropped": [], "no_pron": []}


def euro(lang, w):
    for cand in {w, w.lower(), w.capitalize()}:
        r = dic.execute("select ipa from words where lang=? and word=? limit 1", (lang, cand)).fetchone()
        if r is None:
            r = src[lang].execute("select 1 from simple_translation where written_rep=? limit 1", (cand,)).fetchone()
            ipa = ""
        else:
            ipa = r[0] or ""
        if r is not None:
            if not ipa:
                r2 = dic.execute("select ipa from words where lang=? and lemma=? and ipa!='' limit 1", (lang, cand)).fetchone()
                ipa = r2[0] if r2 else ""
            return w, ipa.split(", /")[0]     # several transcriptions listed: keep the first
    return None, ""


def zh(w):
    if w in hsk:
        return w, hsk[w][1]
    if w in cedict:
        return w, cedict[w]
    return None, ""


try:
    import pykakasi
    _kks = pykakasi.kakasi()
except ImportError:
    _kks = None


def romaji(kana):
    if not _kks:
        return kana
    return "".join(x["hepburn"] for x in _kks.convert(kana))


def jpn(w, concept=None):
    """w may carry an explicit reading override: 妻|つま"""
    reading_override = None
    if "|" in w:
        w, reading_override = w.split("|", 1)
    rows = ja.execute("select reading, gloss, pos from words where word=?", (w,)).fetchall()
    if not rows:
        r = ja.execute("select reading from vocabularies where word=? limit 1", (w,)).fetchone()
        rows = [(r[0], "", "")] if r else []
    if not rows:
        k = ja.execute("select 1 from kanji_catalog where character=? limit 1", (w,)).fetchone()
        return (w, "") if k else (None, "")
    if reading_override:
        return w, "/" + romaji(reading_override) + "/"
    tok = re.compile(r"(?<![a-z])" + re.escape((concept or "").lower()) + r"(?![a-z])") if concept else None

    def score(row):
        rd, gl, pos = row
        g = (gl or "").lower()
        sc = 0
        if tok:
            m = tok.search(g)
            sc += 100 if m else 0
            if m:
                sc -= min(m.start(), 60) / 3.0      # earlier in the gloss = main sense
        if "(arch)" in g or "(dated)" in g or "(obs)" in g:
            sc -= 40
        if "ok" in (pos or "").split(",") or "n-suf" in (pos or "") or "suf" == (pos or ""):
            sc -= 40
        sc += len(rd)
        return -sc
    rows.sort(key=score)
    return w, "/" + romaji(rows[0][0]) + "/"


def arb(w):
    """Arabeyes transliterations are vowel-less noise; only the hand-curated rows (source='curated') give a pronunciation."""
    for cand in (w, "ال" + w):
        r = ar.execute("select transliteration, source from vocabulary where arabic=? or vocalized=? order by (source='curated') desc limit 1", (cand, cand)).fetchone()
        if r:
            tr, srcname = r
            return w, ("/" + tr + "/") if (tr and srcname == "curated") else ""
    return None, ""


def english_ipa(w):
    if not eng_to_ipa:
        return ""
    s = eng_to_ipa.convert(w)
    return "" if "*" in s else "/" + s + "/"


# ── resolve every concept in every language ────────────────────────────
resolved = {c: {} for c in concepts}       # concept -> lang -> (word, pron)
for c, meta in concepts.items():
    p = picks[c]
    resolved[c]["tr"] = (meta["tr"], "")
    resolved[c]["en"] = (c, english_ipa(c))
    for l in ("es", "de", "fr"):
        if p[l] == "-":
            continue
        w, ipa = euro(l, p[l])
        if w is None:
            report["dropped"].append((c, l, p[l], "not in dictionary"))
        else:
            resolved[c][l] = (w, ipa)
    for l, fn in (("zh", zh), ("ja", lambda x, _c=c: jpn(x, _c)), ("ar", arb)):
        if p[l] == "-":
            continue
        w, pron = fn(p[l])
        if w is None:
            report["dropped"].append((c, l, p[l], "not in dictionary"))
        else:
            resolved[c][l] = (w, pron)

# ── assemble per-language packs ────────────────────────────────────────
base_words = {l: {c["word"].lower() for c in BASE.get(l, [])} for l in LANGS}
OUT.mkdir(parents=True, exist_ok=True)
manifest = {}
for target in LANGS:
    seen = set(base_words[target])
    cards = []
    for c, meta in concepts.items():
        if target not in resolved[c]:
            continue
        word, pron = resolved[c][target]
        key = word.lower()
        if key in seen:
            report["dropped"].append((c, target, word, "duplicate of an existing card"))
            continue
        seen.add(key)
        trans = {n: resolved[c][n][0] for n in LANGS if n in resolved[c]}
        cards.append(dict(word=word, pronunciation=pron, category=meta["category"], level=meta["level"], translations=trans))
    if target == "zh":                       # HSK 1-4 packs (not already in the core set)
        lvmap = {1: "A1", 2: "A2", 3: "B1", 4: "B2"}
        for w, (lv, py, meaning) in hsk.items():
            if lv > 4 or w.lower() in seen:
                continue
            seen.add(w.lower())
            cards.append(dict(word=w, pronunciation=py, category="HSK", level=lvmap[lv], translations={"en": meaning.strip()}))
    (OUT / f"{target}.json").write_text(json.dumps(cards, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    lv = {}
    for card in cards:
        lv[card["level"]] = lv.get(card["level"], 0) + 1
    manifest[target] = lv

(OUT / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=1), encoding="utf-8")
(HERE / "build_report.json").write_text(json.dumps(report, ensure_ascii=False, indent=1), encoding="utf-8")
print(json.dumps(manifest, ensure_ascii=False))
print("dropped:", len(report["dropped"]))
