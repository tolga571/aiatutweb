-- Run after the \copy loads (scripts/lexicon/load.sh): keys, indexes and
-- the public views. Loading first and indexing after is much faster.

ALTER TABLE lex.entry_sources ADD FOREIGN KEY (entry_id) REFERENCES lex.entries(id) ON DELETE CASCADE;
ALTER TABLE lex.entry_sources ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.senses     ADD FOREIGN KEY (entry_id) REFERENCES lex.entries(id) ON DELETE CASCADE;
ALTER TABLE lex.senses     ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.forms      ADD FOREIGN KEY (entry_id) REFERENCES lex.entries(id) ON DELETE SET NULL;
ALTER TABLE lex.forms      ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.prons      ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.conj_forms ADD FOREIGN KEY (entry_id) REFERENCES lex.entries(id) ON DELETE CASCADE;
ALTER TABLE lex.conj_forms ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.links      ADD FOREIGN KEY (entry_a) REFERENCES lex.entries(id) ON DELETE CASCADE;
ALTER TABLE lex.links      ADD FOREIGN KEY (entry_b) REFERENCES lex.entries(id) ON DELETE CASCADE;
ALTER TABLE lex.links      ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.chars      ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.sentences  ADD FOREIGN KEY (entry_id) REFERENCES lex.entries(id) ON DELETE SET NULL;
ALTER TABLE lex.sentences  ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.texts      ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);
ALTER TABLE lex.media      ADD FOREIGN KEY (text_id) REFERENCES lex.texts(id) ON DELETE SET NULL;
ALTER TABLE lex.media      ADD FOREIGN KEY (source_id) REFERENCES lex.sources(id);

CREATE UNIQUE INDEX ON lex.entries (lang, headword, reading, pos);
CREATE INDEX ON lex.entries (lang, norm text_pattern_ops);
CREATE INDEX ON lex.entry_sources (source_id);
CREATE INDEX ON lex.senses (entry_id);
CREATE INDEX ON lex.senses (source_id);
CREATE INDEX ON lex.forms (lang, norm);
CREATE INDEX ON lex.forms (entry_id);
CREATE INDEX ON lex.forms (source_id);
CREATE INDEX ON lex.prons (lang, norm);
CREATE INDEX ON lex.prons (source_id);
CREATE INDEX ON lex.conj_forms (entry_id, sort);
CREATE INDEX ON lex.conj_forms (source_id);
CREATE INDEX ON lex.links (entry_a);
CREATE INDEX ON lex.links (entry_b);
CREATE INDEX ON lex.chars (char);
CREATE INDEX ON lex.sentences (entry_id);
CREATE INDEX ON lex.sentences (lang);
CREATE INDEX ON lex.texts (source_id);
CREATE INDEX ON lex.media (text_id);

-- ── What the site may show ─────────────────────────────────────
-- A language is shown when it's in public.languages and not disabled
-- (draft languages work by direct link, like everywhere else on the site);
-- a row is shown when its source is public. Site code reads only these.

CREATE VIEW lex.v_public_sources AS
    SELECT * FROM lex.sources WHERE visibility = 'public';

CREATE VIEW lex.v_entries AS
    SELECT e.*, l.status AS lang_status
    FROM lex.entries e
    JOIN public.languages l ON l.code = e.lang AND l.status <> 'disabled'
    WHERE EXISTS (
        SELECT 1 FROM lex.entry_sources es
        JOIN lex.sources s ON s.id = es.source_id AND s.visibility = 'public'
        WHERE es.entry_id = e.id
    );

CREATE VIEW lex.v_senses AS
    SELECT se.* FROM lex.senses se
    JOIN lex.sources s ON s.id = se.source_id AND s.visibility = 'public'
    JOIN lex.v_entries e ON e.id = se.entry_id;

CREATE VIEW lex.v_forms AS
    SELECT f.* FROM lex.forms f
    JOIN lex.sources s ON s.id = f.source_id AND s.visibility = 'public'
    JOIN public.languages l ON l.code = f.lang AND l.status <> 'disabled';

CREATE VIEW lex.v_prons AS
    SELECT p.* FROM lex.prons p
    JOIN lex.sources s ON s.id = p.source_id AND s.visibility = 'public'
    JOIN public.languages l ON l.code = p.lang AND l.status <> 'disabled';

CREATE VIEW lex.v_conj_forms AS
    SELECT c.* FROM lex.conj_forms c
    JOIN lex.sources s ON s.id = c.source_id AND s.visibility = 'public'
    JOIN lex.v_entries e ON e.id = c.entry_id;

CREATE VIEW lex.v_links AS
    SELECT k.* FROM lex.links k
    JOIN lex.sources s ON s.id = k.source_id AND s.visibility = 'public'
    JOIN lex.v_entries a ON a.id = k.entry_a
    JOIN lex.v_entries b ON b.id = k.entry_b;

CREATE VIEW lex.v_chars AS
    SELECT c.* FROM lex.chars c
    JOIN lex.sources s ON s.id = c.source_id AND s.visibility = 'public';

CREATE VIEW lex.v_sentences AS
    SELECT x.* FROM lex.sentences x
    JOIN lex.sources s ON s.id = x.source_id AND s.visibility = 'public'
    JOIN public.languages l ON l.code = x.lang AND l.status <> 'disabled';

CREATE VIEW lex.v_texts AS
    SELECT t.* FROM lex.texts t
    JOIN lex.sources s ON s.id = t.source_id AND s.visibility = 'public'
    JOIN public.languages l ON l.code = t.lang AND l.status <> 'disabled';

CREATE VIEW lex.v_media AS
    SELECT m.* FROM lex.media m
    JOIN lex.sources s ON s.id = m.source_id AND s.visibility = 'public'
    JOIN public.languages l ON l.code = m.lang AND l.status <> 'disabled';

-- Per-source counts for the admin page (counting 15M rows live is slow).
UPDATE lex.sources s SET entry_count = c.n FROM (SELECT source_id, count(*) n FROM lex.entry_sources GROUP BY 1) c WHERE c.source_id = s.id;
UPDATE lex.sources s SET row_count = c.n FROM (
    SELECT source_id, sum(n) n FROM (
        SELECT source_id, count(*) n FROM lex.entry_sources GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.senses GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.forms GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.prons GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.conj_forms GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.links GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.chars GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.sentences GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.texts GROUP BY 1
        UNION ALL SELECT source_id, count(*) FROM lex.media GROUP BY 1
    ) u GROUP BY 1
) c WHERE c.source_id = s.id;

CREATE TABLE lex.lang_counts AS SELECT lang, count(*) AS n FROM lex.entries GROUP BY lang;

ANALYZE lex.entries;
ANALYZE lex.entry_sources;
ANALYZE lex.senses;
ANALYZE lex.forms;
ANALYZE lex.prons;
ANALYZE lex.conj_forms;
ANALYZE lex.links;
ANALYZE lex.chars;
ANALYZE lex.sentences;
