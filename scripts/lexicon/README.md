# Lexicon (`lex` schema)

Dictionary data from the three downloads, kept **in full** and tagged by
source. Who sees what is decided in the database, not by deleting data:

* `lex.sources.visibility` — `public` (site), `admin_only` (copyrighted or
  scraped: kept, only the admin sees it), `review` (origin/quality unclear,
  the admin decides), `disabled`.
* the site's `languages` table — a language that isn't added (or is
  `disabled`) is hidden; `draft` works by direct link only, `published` is
  listed everywhere.

Site code reads only the `lex.v_*` views, which apply both rules; the admin
reads the tables. `lex.entry_sources` is the pivot between a word and the
sources that have it, so hiding one source hides its glosses, not the word.

## Sources

| code | what | visibility |
|---|---|---|
| cc_cedict, hsk_lists, makemeahanzi | Chinese dictionary, HSK levels, character decomposition | public |
| jmdict | Japanese dictionary (rank 99999 rows of jtalk's dictionary.db) | public |
| wikdict, kaikki, ipa_dict | de/es/fr/la/el dictionaries and IPA | public |
| openrussian | Russian words, declensions, conjugations, en+de glosses | public |
| arabeyes, atalk_curated | Arabic word lists | public |
| cooljugator | 33k verb tables in ~45 languages, 281k example sentences | public (owner's decision, 2026-10-03) |
| kanjikana, massivedict, hanswehr, easymandarin | scraped / copyrighted | admin_only |
| everything else | generated or unclear origin | review |

The full list with licences is `SOURCES` in `build.py` (also in the
`lex.sources` table and on the admin Lexicon page).

## Rebuild

The raw downloads (~2.6 GB) are not in the repo. Unzip them so that

    <dir>/ds/      = sqlite-data-langauges-*.zip
    <dir>/talks/   = 6TALKS-*.zip
    <dir>/pc/      = parse-conjugator-*.zip (and the zip inside it)

then

    python3 scripts/lexicon/build.py <dir> <out>          # ~4 min, writes <out>/*.tsv + report.json
    DATABASE_URL=postgresql://... sh scripts/lexicon/load.sh <out>   # ~3 min locally

`load.sh` drops and recreates the `lex` schema only. The result is about
3.4 GB (2.1M entries, 2.4M conjugation forms, 4.2M pronunciations).

To put it on another database without rebuilding:

    pg_dump -Fc -n lex "$LOCAL_URL" > lex.dump
    pg_restore --no-owner -d "$TARGET_URL" lex.dump

The views reference `public.languages`, so the app must have run once on
the target (it creates that table).

Language codes from cooljugator are normalised (`gr`→`el`, `ee`→`et`,
`hw`→`haw`; its `run`/`rua`/`fin`/`fia` noun/adjective tables become
`ru`/`fi` with the right POS).
