-- Lexicon: dictionary data imported from the sqlite-data-languages /
-- 6TALKS / parse-conjugator downloads (see scripts/lexicon/README.md).
--
-- Every row carries the source it came from (lex.sources). Whether users
-- may see a source is lex.sources.visibility:
--   public      shown on the site
--   admin_only  copyrighted / scraped: kept, but only the admin sees it
--   review      origin or quality unclear: admin decides
--   disabled    hidden everywhere except the admin source list
-- and whether a language is shown is the site's `languages` table
-- (published/draft = usable, disabled or not added = hidden). Site code
-- reads only the lex.v_* views; the admin reads the tables.
--
-- A word is one lex.entries row; lex.entry_sources is the pivot saying
-- which sources have it, so the same word from WikDict and MassiveDict is
-- one entry with two pivot rows, and hiding MassiveDict hides only its
-- glosses (lex.senses rows), not the word.
--
-- Rebuilt from scratch by scripts/lexicon/load.sh; nothing in the app
-- writes here except the admin (visibility, edits) and the AI gloss cache.

DROP SCHEMA IF EXISTS lex CASCADE;
CREATE SCHEMA lex;

CREATE TABLE lex.sources (
    id          SMALLINT PRIMARY KEY,
    code        TEXT NOT NULL UNIQUE,
    name        TEXT NOT NULL,
    url         TEXT NOT NULL DEFAULT '',
    licence     TEXT NOT NULL DEFAULT '',
    licence_url TEXT NOT NULL DEFAULT '',
    attribution TEXT NOT NULL DEFAULT '',
    visibility  TEXT NOT NULL CHECK (visibility IN ('public', 'admin_only', 'review', 'disabled')),
    origin      TEXT NOT NULL DEFAULT '',   -- which download / file it was read from
    notes       TEXT NOT NULL DEFAULT '',
    entry_count BIGINT NOT NULL DEFAULT 0,  -- filled by post.sql
    row_count   BIGINT NOT NULL DEFAULT 0,  -- all rows of every table carrying this source
    imported_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE lex.entries (
    id        BIGINT PRIMARY KEY,
    lang      TEXT NOT NULL,
    headword  TEXT NOT NULL,
    norm      TEXT NOT NULL,                -- lower-case, no diacritics / harakat: lookup key
    reading   TEXT NOT NULL DEFAULT '',     -- kana, pinyin, transliteration, stressed form
    pos       TEXT NOT NULL DEFAULT '',
    freq_rank INTEGER,
    level     TEXT,                         -- A1..C2, HSK1..6, N5..N1
    extra     JSONB
);

CREATE TABLE lex.entry_sources (
    entry_id  BIGINT NOT NULL,
    source_id SMALLINT NOT NULL,
    ext_id    TEXT,
    PRIMARY KEY (entry_id, source_id)
);

CREATE TABLE lex.senses (
    id         BIGINT PRIMARY KEY,
    entry_id   BIGINT NOT NULL,
    source_id  SMALLINT NOT NULL,
    gloss_lang TEXT NOT NULL DEFAULT 'en',
    sense_no   SMALLINT NOT NULL DEFAULT 1,
    gloss      TEXT NOT NULL,
    definition TEXT
);

-- Inflected / variant forms pointing at their entry (lemma).
CREATE TABLE lex.forms (
    id        BIGINT PRIMARY KEY,
    lang      TEXT NOT NULL,
    form      TEXT NOT NULL,
    norm      TEXT NOT NULL,
    entry_id  BIGINT,                       -- NULL when the lemma isn't in lex.entries
    lemma     TEXT,
    tags      TEXT NOT NULL DEFAULT '',
    source_id SMALLINT NOT NULL
);

-- Pronunciation: IPA and/or stressed-vowel index (Russian).
CREATE TABLE lex.prons (
    id        BIGINT PRIMARY KEY,
    lang      TEXT NOT NULL,
    word      TEXT NOT NULL,
    norm      TEXT NOT NULL,
    ipa       TEXT,
    stress    SMALLINT,
    variant   TEXT NOT NULL DEFAULT '',
    source_id SMALLINT NOT NULL
);

-- Conjugation / declension tables.
CREATE TABLE lex.conj_forms (
    id          BIGINT PRIMARY KEY,
    entry_id    BIGINT NOT NULL,
    source_id   SMALLINT NOT NULL,
    table_title TEXT NOT NULL DEFAULT '',
    tense       TEXT NOT NULL DEFAULT '',
    person      TEXT NOT NULL DEFAULT '',
    form        TEXT NOT NULL,
    form_gloss  TEXT,
    translit    TEXT,
    sort        INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE lex.links (
    id         BIGINT PRIMARY KEY,
    entry_a    BIGINT NOT NULL,
    entry_b    BIGINT NOT NULL,
    link_type  TEXT NOT NULL,               -- translation | related | concept
    confidence REAL,
    note       TEXT,
    source_id  SMALLINT NOT NULL
);

CREATE TABLE lex.chars (
    id            BIGINT PRIMARY KEY,
    char          TEXT NOT NULL,
    script        TEXT NOT NULL DEFAULT '',
    readings      JSONB,
    meaning       TEXT,
    radical       TEXT,
    strokes       SMALLINT,
    decomposition TEXT,
    levels        JSONB,
    extra         JSONB,
    source_id     SMALLINT NOT NULL
);

CREATE TABLE lex.sentences (
    id             BIGINT PRIMARY KEY,
    lang           TEXT NOT NULL,
    text           TEXT NOT NULL,
    translit       TEXT,
    translation    TEXT,
    translation_lang TEXT NOT NULL DEFAULT 'en',
    entry_id       BIGINT,
    extra          JSONB,
    source_id      SMALLINT NOT NULL
);

-- Longer texts: articles, demo texts saved in the clone apps.
CREATE TABLE lex.texts (
    id        BIGINT PRIMARY KEY,
    lang      TEXT NOT NULL,
    title     TEXT NOT NULL DEFAULT '',
    body      TEXT NOT NULL DEFAULT '',
    level     TEXT,
    extra     JSONB,
    source_id SMALLINT NOT NULL
);

-- Audio etc. Only references (URL / path); the files themselves aren't imported.
CREATE TABLE lex.media (
    id        BIGINT PRIMARY KEY,
    lang      TEXT NOT NULL,
    kind      TEXT NOT NULL DEFAULT 'audio',
    ref       TEXT NOT NULL,
    text_id   BIGINT,
    source_id SMALLINT NOT NULL
);
