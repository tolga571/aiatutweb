<?php
namespace App\Src;

/**
 * Admin view of the lexicon (lex schema, loaded by scripts/lexicon/): every
 * source with its licence and visibility, and word search across all
 * sources — including the admin_only / review ones the site never shows.
 */
class AdminLexicon
{
    public const VISIBILITIES = ['public', 'admin_only', 'review', 'disabled'];
    public const PER_PAGE = 50;

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** False until scripts/lexicon/load.sh has been run on this database. */
    public function available(): bool
    {
        try {
            return (bool)$this->db->fetchOne("SELECT to_regclass('lex.sources') AS t")['t'];
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function sources(): array
    {
        return $this->db->fetchAll('SELECT * FROM lex.sources ORDER BY row_count DESC, id');
    }

    /** [lang => entries], with each language's site status (null = not added to the site). */
    public function languages(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.lang, c.n, l.status FROM lex.lang_counts c LEFT JOIN public.languages l ON l.code = c.lang ORDER BY c.n DESC'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['lang']] = ['entries' => (int)$r['n'], 'status' => $r['status']];
        }
        return $out;
    }

    public function setVisibility(int $sourceId, string $visibility): string
    {
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            throw new \InvalidArgumentException('bad visibility');
        }
        $row = $this->db->fetchOne('SELECT code FROM lex.sources WHERE id = ?', [$sourceId]);
        if (!$row) {
            throw new \InvalidArgumentException('unknown source');
        }
        $this->db->execute('UPDATE lex.sources SET visibility = ?, updated_at = now() WHERE id = ?', [$visibility, $sourceId]);
        return $row['code'];
    }

    /**
     * Entries whose normalised headword starts with $q (or equals it when
     * $exact), optionally only those a given source has. Each row carries the
     * codes of its sources and its first gloss.
     */
    public function search(string $lang, string $q, int $sourceId, bool $exact, int $page): array
    {
        $where = ['e.lang = ?'];
        $args = [$lang];
        if ($q !== '') {
            $n = self::norm($lang, $q);
            if ($exact) {
                $where[] = 'e.norm = ?';
                $args[] = $n;
            } else {
                $where[] = 'e.norm LIKE ?';
                $args[] = addcslashes($n, '%_\\') . '%';
            }
        }
        if ($sourceId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM lex.entry_sources x WHERE x.entry_id = e.id AND x.source_id = ?)';
            $args[] = $sourceId;
        }
        $offset = (max(1, $page) - 1) * self::PER_PAGE;
        // One row more than a page tells us whether there is a next page
        // without counting (counting a prefix match over 400k rows is slow).
        $rows = $this->db->fetchAll(
            'SELECT e.*,
                (SELECT string_agg(s.code, \',\' ORDER BY s.id) FROM lex.entry_sources es JOIN lex.sources s ON s.id = es.source_id WHERE es.entry_id = e.id) AS source_codes,
                (SELECT se.gloss FROM lex.senses se WHERE se.entry_id = e.id ORDER BY se.source_id, se.sense_no LIMIT 1) AS first_gloss
             FROM lex.entries e WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.freq_rank NULLS LAST, length(e.headword), e.headword
             LIMIT ' . (self::PER_PAGE + 1) . ' OFFSET ' . $offset,
            $args
        );
        $more = count($rows) > self::PER_PAGE;
        return ['rows' => array_slice($rows, 0, self::PER_PAGE), 'more' => $more];
    }

    /** Everything about one entry, from every source. */
    public function entry(int $id): ?array
    {
        $e = $this->db->fetchOne('SELECT * FROM lex.entries WHERE id = ?', [$id]);
        if (!$e) {
            return null;
        }
        $src = 'JOIN lex.sources s ON s.id = x.source_id';
        $cols = 's.code AS source_code, s.visibility AS source_visibility';
        return [
            'entry' => $e,
            'sources' => $this->db->fetchAll("SELECT x.ext_id, $cols FROM lex.entry_sources x $src WHERE x.entry_id = ? ORDER BY s.id", [$id]),
            'senses' => $this->db->fetchAll("SELECT x.*, $cols FROM lex.senses x $src WHERE x.entry_id = ? ORDER BY s.id, x.gloss_lang, x.sense_no LIMIT 300", [$id]),
            'forms' => $this->db->fetchAll("SELECT x.form, x.tags, $cols FROM lex.forms x $src WHERE x.entry_id = ? ORDER BY s.id, x.id LIMIT 300", [$id]),
            'prons' => $this->db->fetchAll("SELECT x.word, x.ipa, x.stress, x.variant, $cols FROM lex.prons x $src WHERE x.lang = ? AND x.norm = ? ORDER BY s.id LIMIT 50", [$e['lang'], $e['norm']]),
            'conj' => $this->db->fetchAll("SELECT x.table_title, x.tense, x.person, x.form, x.form_gloss, x.translit, $cols FROM lex.conj_forms x $src WHERE x.entry_id = ? ORDER BY s.id, x.table_title, x.sort LIMIT 600", [$id]),
            'sentences' => $this->db->fetchAll("SELECT x.text, x.translit, x.translation, $cols FROM lex.sentences x $src WHERE x.entry_id = ? ORDER BY s.id, x.id LIMIT 40", [$id]),
            'links' => $this->db->fetchAll(
                "SELECT x.link_type, x.confidence, x.note, o.id AS other_id, o.lang AS other_lang, o.headword AS other_headword, $cols
                 FROM lex.links x $src JOIN lex.entries o ON o.id = CASE WHEN x.entry_a = ? THEN x.entry_b ELSE x.entry_a END
                 WHERE x.entry_a = ? OR x.entry_b = ? ORDER BY s.id, o.lang LIMIT 100",
                [$id, $id, $id]
            ),
        ];
    }

    /**
     * Same lookup key as scripts/lexicon/build.py norm(): lower case without
     * diacritics (Latin/Greek/Cyrillic), without harakat (Arabic), unchanged
     * for Chinese/Japanese.
     */
    public static function norm(string $lang, string $s): string
    {
        $s = trim($s);
        if ($lang === 'ar') {
            $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $s);
            return strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا']);
        }
        if ($lang === 'zh' || $lang === 'ja') {
            return $s;
        }
        $s = mb_strtolower(str_replace(["'", "\u{0301}"], '', $s), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $s = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D));
            return \Normalizer::normalize($s, \Normalizer::FORM_C);
        }
        // No intl extension: strip the common accents by hand.
        return strtr($s, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
            'ë' => 'e', 'ē' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
            'õ' => 'o', 'ö' => 'o', 'ō' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ğ' => 'g',
            'ş' => 's', 'ı' => 'ı', 'ё' => 'е', 'й' => 'и', 'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
        ]);
    }
}
