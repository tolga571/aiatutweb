# Vocabulary packs (data/vocab/*.json)

Extra flashcards on top of the original 50-word starter deck (`data/flashcards_data.php`).
Users add them per CEFR level from the **Word packs** panel on the Flashcards page
(`?page=flashcard-import-pack`, see `Flashcard::importPack`).

## Where the data comes from
The `sqlite-data-languages` download (dictionaries: European WikDict/Wiktionary, JMdict-based Japanese,
Arabic, CC-CEDICT + HSK lists). The raw set is ~2.5 GB and is **not** in the repo — only the small
curated result (~0.5 MB) is.

* `concepts.tsv` – 263 single-word concepts (English lemma, POS, category, CEFR level, Turkish).
  The Turkish column is hand-written (no Turkish dictionary in the set).
* `picks.tsv` – the word chosen for each concept in es/de/fr/zh/ja/ar (`-` = no card).
  `word|reading` forces a Japanese reading (e.g. `妻|つま`).
* `build_vocab.py` – **verifies every pick against the dictionaries** (a pick that is not found is dropped
  and listed in `build_report.json`, never guessed), pulls pronunciation from them (es/de/fr IPA,
  Japanese romaji, Arabic transliteration only for hand-curated rows, Chinese pinyin), adds English IPA
  from CMUdict (`eng-to-ipa`), adds Chinese HSK 1–4 (→ A1..B2) as extra packs, de-duplicates against
  the starter deck and writes `data/vocab/<lang>.json` + `manifest.json`.
* `candidates.py` – helper that lists dictionary candidates per concept (used while choosing picks).

## Rebuild
    unzip sqlite-data-langauges-*.zip -d /some/dir          # data-european/, data-japanese/, data-arabic/, data-chinese/
    php -r 'echo json_encode(require "data/flashcards_data.php", JSON_UNESCAPED_UNICODE);' > /tmp/base.json
    pip install eng-to-ipa pykakasi
    python3 scripts/vocab/build_vocab.py /some/dir data/vocab /tmp/base.json

## Known gaps (see build_report.json)
Some very common words are not in the source dictionaries under the form used (e.g. Japanese いつも/ここ/とても,
Arabic verbs like جاء/أراد); those cards are simply absent. Spanish/German IPA is missing for most words.
HSK cards only carry English glosses (users with another native language see the English gloss).
