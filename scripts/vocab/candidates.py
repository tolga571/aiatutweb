#!/usr/bin/env python3
"""Step 1 of the vocabulary build: for every concept in concepts.tsv, find candidate
words in the downloaded dictionaries (sqlite-data-languages) for es/de/fr/zh/ja/ar.
Output: candidates.json (top candidates per concept/language) for human review.

usage: candidates.py <data_dir> <out.json>
<data_dir> = folder that contains data-european/, data-japanese/, data-arabic/, data-chinese/
(extracted from the sqlite-data-languages zip).
"""
import csv, json, re, sqlite3, sys
from pathlib import Path

DATA = Path(sys.argv[1])
OUT = Path(sys.argv[2])
HERE = Path(__file__).parent


def ro(path):
    return sqlite3.connect(f"file:{path}?mode=ro", uri=True)


def load_concepts():
    rows = []
    for line in (HERE / "concepts.tsv").read_text(encoding="utf-8").splitlines():
        if not line.strip() or line.startswith("#"):
            continue
        en, pos, cat, level, tr = line.split("\t")
        rows.append(dict(en=en, pos=pos, category=cat, level=level, tr=tr))
    return rows


POS_OK = {
    "n": {"noun", "proper noun"},
    "v": {"verb"},
    "adj": {"adj", "adjective"},
    "adv": {"adv", "adverb"},
    "num": {"num", "numeral", "number", "adj", "noun", "det"},
}


# ── European (es / de / fr): WikDict translation tables + dictionary.sqlite for IPA/POS ──
def european(lang, concepts):
    src = ro(DATA / f"data-european/sources/{lang}-en.sqlite3")
    dic = ro(DATA / "data-european/dictionary.sqlite")
    rows = src.execute("select written_rep, trans_list, rel_importance from simple_translation").fetchall()
    by_gloss = {}
    for w, tl, imp in rows:
        for pos_i, g in enumerate([x.strip().lower() for x in tl.split("|")]):
            by_gloss.setdefault(g, []).append((w, pos_i, imp or 0, tl))
    out = {}
    for c in concepts:
        keys = [c["en"]] if c["pos"] != "v" else [c["en"], "to " + c["en"]]
        cands = []
        for k in keys:
            cands += by_gloss.get(k, [])
        scored = []
        for w, pos_i, imp, tl in cands:
            if " " in w or re.search(r"\d", w) or len(w) < 2:
                continue
            if lang != "de" and w[0].isupper():   # proper nouns; German nouns are capitalised legitimately
                continue
            if lang == "de" and c["pos"] != "n" and w[0].isupper():
                continue
            row = dic.execute("select pos, ipa from words where lang=? and word=? limit 1", (lang, w)).fetchone()
            pos, ipa = (row or ("", ""))
            pos_match = 1 if (pos and pos.lower() in POS_OK.get(c["pos"], set())) else 0
            scored.append((-pos_match, pos_i, -imp, w, ipa, pos, tl))
        scored.sort()
        out[c["en"]] = [dict(word=s[3], ipa=s[4], pos=s[5], gloss=s[6][:60]) for s in scored[:4]]
    return out


# ── Chinese: HSK lists first (levelled, clean), CEDICT as fallback ──
def pinyin_num_to_mark(s):
    tone = {"a": "āáǎà", "e": "ēéěè", "i": "īíǐì", "o": "ōóǒò", "u": "ūúǔù", "ü": "ǖǘǚǜ"}

    def conv(syl):
        m = re.match(r"^([a-zü:]+?)([1-5])$", syl.lower().replace("u:", "ü"))
        if not m:
            return syl
        body, t = m.group(1), int(m.group(2))
        if t == 5:
            return body
        for v in ("a", "e", "ou"):
            if v in body:
                i = body.index(v)
                ch = body[i]
                return body[:i] + tone[ch][t - 1] + body[i + 1:] if len(v) == 1 else body[:i] + tone["o"][t - 1] + body[i + 1:]
        for i in range(len(body) - 1, -1, -1):
            if body[i] in tone:
                return body[:i] + tone[body[i]][t - 1] + body[i + 1:]
        return body
    return " ".join(conv(x) for x in s.split())


def chinese(concepts):
    hsk = []
    for lv in range(1, 7):
        with open(DATA / f"data-chinese/hsk/hsk{lv}.csv", encoding="utf-8") as f:
            for w, py, meaning in csv.reader(f):
                hsk.append((lv, w, py, meaning))
    ced = []
    for line in (DATA / "data-chinese/cedict_ts.u8").read_text(encoding="utf-8").splitlines():
        if line.startswith("#"):
            continue
        m = re.match(r"^(\S+) (\S+) \[([^\]]+)\] /(.*)/$", line)
        if m:
            ced.append((m.group(2), pinyin_num_to_mark(m.group(3)), m.group(4).split("/")))
    out = {}
    for c in concepts:
        en = c["en"]
        targets = {en, "to " + en, "a " + en, "the " + en}
        cands = []
        for lv, w, py, meaning in hsk:
            parts = [p.strip().lower() for p in re.split(r"[;,]", meaning)]
            if any(p in targets for p in parts):
                first = 0 if parts[0] in targets else 1
                cands.append((first, lv, w, py, "HSK%d %s" % (lv, meaning[:40])))
        cands.sort()
        res = [dict(word=w, pinyin=py, src=s) for _, _, w, py, s in cands[:3]]
        if len(res) < 2:
            extra = []
            for w, py, defs in ced:
                d0 = [d.lower() for d in defs]
                if d0 and d0[0] in targets and len(w) <= 3:
                    extra.append((len(w), w, py, "CEDICT " + "; ".join(defs[:2])[:40]))
            extra.sort()
            for _, w, py, s in extra[:3]:
                if all(w != r["word"] for r in res):
                    res.append(dict(word=w, pinyin=py, src=s))
        out[en] = res[:4]
    return out


# ── Japanese: words table (word, reading(romaji), gloss, pos); prefer words made of JLPT kanji ──
def japanese(concepts):
    db = ro(DATA / "data-japanese/dictionary.db")
    jl = {}
    for ch, j in db.execute("select character, jlpt from kanji_catalog where jlpt is not null and jlpt!=''"):
        jl[ch] = j
    rows = db.execute("select word, reading, gloss, pos from words").fetchall()
    by_gloss = {}
    for w, rd, gl, pos in rows:
        if not gl:
            continue
        for i, g in enumerate([x.strip().lower() for x in gl.split(";")]):
            by_gloss.setdefault(g, []).append((w, rd, gl, pos, i))
    cjk = re.compile(r"[一-鿿]")
    out = {}
    for c in concepts:
        keys = [c["en"]] if c["pos"] != "v" else ["to " + c["en"], c["en"]]
        cands = []
        for k in keys:
            cands += by_gloss.get(k, [])
        scored = []
        for w, rd, gl, pos, gi in cands:
            kan = cjk.findall(w or "")
            unknown = sum(1 for k in kan if k not in jl)
            hard = sum(1 for k in kan if jl.get(k, "N0") in ("N1", "N2"))
            scored.append((gi, unknown, hard, len(w or ""), w, rd, gl[:50], pos))
        scored.sort()
        out[c["en"]] = [dict(word=s[4], reading=s[5], gloss=s[6], pos=s[7]) for s in scored[:4]]
    return out


# ── Arabic: atalk vocabulary (curated first) ──
def arabic(concepts):
    db = ro(DATA / "data-arabic/atalk_full_vocabulary.sqlite")
    rows = db.execute("select arabic, vocalized, transliteration, gloss, pos, source from vocabulary where gloss is not null").fetchall()
    by_gloss = {}
    for ar, voc, tr, gl, pos, src in rows:
        for i, g in enumerate([x.strip().lower() for x in re.split(r"[;,]", gl)]):
            by_gloss.setdefault(g, []).append((ar, voc, tr, gl, pos, src, i))
    out = {}
    for c in concepts:
        keys = [c["en"]] if c["pos"] != "v" else ["to " + c["en"], c["en"]]
        cands = []
        for k in keys:
            cands += by_gloss.get(k, [])
        scored = []
        for ar, voc, tr, gl, pos, src, i in cands:
            if " " in ar:
                continue
            scored.append((0 if src == "curated" else 1, i, len(ar), ar, voc, tr, gl[:40], pos, src))
        scored.sort()
        out[c["en"]] = [dict(word=s[3], vocalized=s[4], translit=s[5], gloss=s[6], pos=s[7], src=s[8]) for s in scored[:4]]
    return out


if __name__ == "__main__":
    concepts = load_concepts()
    res = {"concepts": concepts}
    for l in ("es", "de", "fr"):
        res[l] = european(l, concepts)
    res["zh"] = chinese(concepts)
    res["ja"] = japanese(concepts)
    res["ar"] = arabic(concepts)
    OUT.write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding="utf-8")
    miss = {l: [c["en"] for c in concepts if not res[l].get(c["en"])] for l in ("es", "de", "fr", "zh", "ja", "ar")}
    print({l: len(v) for l, v in miss.items()}, "concepts without any candidate")
