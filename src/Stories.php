<?php
namespace App\Src;

/**
 * Stories: the public list and reader, saving a word to the cards, and the
 * admin side (draft from English, build every language, publish).
 * Tables: story_categories, stories, story_texts (see Database::migrate()).
 */
class Stories
{
    public const LENGTHS = ['short', 'medium', 'long'];

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /** Category slugs in display order, each with its name in $lang and its published story count. */
    public function categories(string $lang): array {
        $rows = $this->db->fetchAll(
            "SELECT c.slug, c.names, COUNT(s.id) AS n
             FROM story_categories c
             LEFT JOIN stories s ON s.category = c.slug AND s.status = 'published'
             GROUP BY c.slug, c.names, c.sort_order
             ORDER BY c.sort_order, c.slug"
        );
        return array_map(fn($r) => [
            'slug' => $r['slug'],
            'name' => $this->categoryName($r['slug'], json_decode($r['names'] ?? '{}', true) ?: [], $lang),
            'count' => (int)$r['n'],
        ], $rows);
    }

    /** Built-in categories have UI strings; ones added in the admin carry their own names. */
    public function categoryName(string $slug, array $names, string $lang): string {
        $key = 'stories.cat_' . str_replace('-', '_', $slug);
        $ui = __($key);
        if ($ui !== $key) {
            return $ui;
        }
        return (string)($names[$lang] ?? $names['en'] ?? $slug);
    }

    public function categoryNames(string $lang): array {
        $out = [];
        foreach ($this->categories($lang) as $c) {
            $out[$c['slug']] = $c['name'];
        }
        return $out;
    }

    /**
     * Published stories with their title and summary in $lang (English when
     * a story has no text in it yet), optionally filtered.
     */
    public function published(string $lang, string $category = '', string $length = ''): array {
        $where = ["s.status = 'published'"];
        $params = [$lang];
        if ($category !== '') {
            $where[] = 's.category = ?';
            $params[] = $category;
        }
        if ($length !== '' && in_array($length, self::LENGTHS, true)) {
            $where[] = 's.length = ?';
            $params[] = $length;
        }
        return $this->db->fetchAll(
            "SELECT s.id, s.slug, s.category, s.length,
                    COALESCE(t.lang, e.lang) AS lang,
                    COALESCE(t.title, e.title) AS title,
                    COALESCE(t.summary, e.summary) AS summary
             FROM stories s
             LEFT JOIN story_texts t ON t.story_id = s.id AND t.lang = ?
             LEFT JOIN story_texts e ON e.story_id = s.id AND e.lang = 'en'
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.sort_order, s.published_at DESC, s.id DESC",
            $params
        );
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): ?array {
        $row = $this->db->fetchOne(
            'SELECT * FROM stories WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : ''),
            [$slug]
        );
        return $row ?: null;
    }

    public function find(int $id): ?array {
        return $this->db->fetchOne('SELECT * FROM stories WHERE id = ?', [$id]) ?: null;
    }

    /** Published stories for the sitemap: slug, last change, and the listed languages each has text in. */
    public function sitemap(array $langs): array {
        $rows = $this->db->fetchAll(
            "SELECT s.slug, s.updated_at AS changed, string_agg(t.lang, ',' ORDER BY t.lang) AS langs
             FROM stories s JOIN story_texts t ON t.story_id = s.id
             WHERE s.status = 'published'
             GROUP BY s.id, s.slug, s.updated_at, s.sort_order
             ORDER BY s.sort_order, s.id"
        );
        return array_map(fn($r) => [
            'slug' => $r['slug'],
            'changed' => $r['changed'],
            'langs' => array_values(array_intersect(explode(',', (string)$r['langs']), $langs)),
        ], $rows);
    }

    /** Languages a story has text in. */
    public function languagesOf(int $storyId): array {
        return array_column($this->db->fetchAll('SELECT lang FROM story_texts WHERE story_id = ? ORDER BY lang', [$storyId]), 'lang');
    }

    /** One language of a story: title, summary, sentences [{text, tokens: [{t, r, m}]}]. */
    public function text(int $storyId, string $lang): ?array {
        $row = $this->db->fetchOne('SELECT title, summary, content FROM story_texts WHERE story_id = ? AND lang = ?', [$storyId, $lang]);
        if (!$row) {
            return null;
        }
        $content = json_decode($row['content'], true) ?: [];
        return ['lang' => $lang, 'title' => $row['title'], 'summary' => $row['summary'], 'sentences' => $content['sentences'] ?? []];
    }

    /** Titles of a story in every language it has (for hreflang links and the language bar). */
    public function titles(int $storyId): array {
        $out = [];
        foreach ($this->db->fetchAll('SELECT lang, title FROM story_texts WHERE story_id = ?', [$storyId]) as $r) {
            $out[$r['lang']] = $r['title'];
        }
        return $out;
    }

    /** Plain text of a story: the sentences joined as they are written in that language. */
    public static function plainText(array $text): string {
        $sep = in_array($text['lang'] ?? '', ['zh', 'ja'], true) ? '' : ' ';
        return implode($sep, array_column($text['sentences'], 'text'));
    }

    /**
     * Saves one word of a story to the user's cards in the story's
     * language: the word, its reading, its meaning in the user's native
     * language and the sentence it came from (with that sentence in the
     * native language when the story has it). Goes into the "Saved" list.
     * The word is looked up on the server from (story, language, sentence,
     * word), never taken from the client.
     * @return array{card_id?: int, already?: bool, error?: string}
     */
    public function saveWord(int $userId, string $nativeLang, string $slug, string $lang, int $sentence, int $token): array {
        $story = $this->findBySlug($slug);
        if (!$story || !Language::isUsable($lang, 'learn')) {
            return ['error' => 'not_found'];
        }
        $text = $this->text((int)$story['id'], $lang);
        $tok = $text['sentences'][$sentence]['tokens'][$token] ?? null;
        if (!$tok || StoryGenerator::isPunct((string)$tok['t'])) {
            return ['error' => 'not_found'];
        }
        $word = self::cleanWord((string)$tok['t']);
        $meaning = (string)($tok['m'][$nativeLang] ?? $tok['m']['en'] ?? '');
        $native = $nativeLang !== $lang ? $this->text((int)$story['id'], $nativeLang) : null;
        $fc = new Flashcard($this->db);
        $r = $fc->createCard($userId, $lang, [
            'word' => mb_substr($word, 0, Flashcard::FIELD_LIMITS['word']),
            'translation' => mb_substr($meaning, 0, Flashcard::FIELD_LIMITS['translation']),
            'pronunciation' => mb_substr((string)$tok['r'], 0, Flashcard::FIELD_LIMITS['pronunciation']),
            'example' => mb_substr((string)$text['sentences'][$sentence]['text'], 0, Flashcard::FIELD_LIMITS['example']),
            'example_translation' => mb_substr((string)($native['sentences'][$sentence]['text'] ?? ''), 0, Flashcard::FIELD_LIMITS['example_translation']),
            'category' => 'Stories',
        ]);
        $cards = new CardLists($this->db);
        if (!empty($r['card'])) {
            $cards->add($userId, $cards->defaultId($userId, $lang), [(int)$r['card']['id']]);
            return ['card_id' => (int)$r['card']['id']];
        }
        if (($r['error'] ?? '') === 'duplicate' && !empty($r['existing_id'])) {
            $cards->add($userId, $cards->defaultId($userId, $lang), [(int)$r['existing_id']]);
            return ['card_id' => (int)$r['existing_id'], 'already' => true];
        }
        return ['error' => $r['error'] ?? 'generic'];
    }

    /** The word without the punctuation around it ("rats." → "rats", «Hola» → Hola). */
    public static function cleanWord(string $t): string {
        $w = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $t);
        return $w === '' ? $t : $w;
    }

    // ── Admin ─────────────────────────────────────────────────────

    /** Every story for the admin list, with its English title and how many languages it has. */
    public function all(): array {
        return $this->db->fetchAll(
            "SELECT s.*, e.title AS title_en, (SELECT COUNT(*) FROM story_texts t WHERE t.story_id = s.id) AS langs
             FROM stories s LEFT JOIN story_texts e ON e.story_id = s.id AND e.lang = 'en'
             ORDER BY s.status DESC, s.sort_order, s.id DESC"
        );
    }

    /** URL-safe slug from an English title, unique among stories. */
    public function makeSlug(string $title, int $exceptId = 0): string {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title)), '-');
        $base = substr($base !== '' ? $base : 'story', 0, 60);
        $slug = $base;
        for ($i = 2; $this->db->fetchOne('SELECT 1 FROM stories WHERE slug = ? AND id != ?', [$slug, $exceptId]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    /**
     * English source → draft story (new, or replacing a draft's source).
     * Sentences: one per line or split at . ! ? — the admin can paste a paragraph.
     * @return array{id?: int, error?: string}
     */
    public function saveDraft(array $in, int $id = 0): array {
        $title = trim((string)($in['title'] ?? ''));
        $summary = trim((string)($in['summary'] ?? ''));
        $category = (string)($in['category'] ?? '');
        $sentences = self::splitSentences((string)($in['body'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120 || mb_strlen($summary) > 300) {
            return ['error' => 'title'];
        }
        if (count($sentences) < 3 || count($sentences) > 40) {
            return ['error' => 'body'];
        }
        if (!$this->db->fetchOne('SELECT 1 FROM story_categories WHERE slug = ?', [$category])) {
            return ['error' => 'category'];
        }
        $source = json_encode(['title' => $title, 'summary' => $summary !== '' ? $summary : $title, 'sentences' => $sentences], JSON_UNESCAPED_UNICODE);
        $length = StoryGenerator::lengthFor($sentences);
        if ($id) {
            $story = $this->find($id);
            if (!$story) {
                return ['error' => 'not_found'];
            }
            $this->db->execute(
                "UPDATE stories SET category = ?, length = ?, source = ?, source_json = ?, status = 'draft', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$category, $length, trim((string)($in['source'] ?? '')), $source, $id]
            );
            $this->db->execute('DELETE FROM story_texts WHERE story_id = ?', [$id]);
            return ['id' => $id];
        }
        $row = $this->db->fetchOne(
            "INSERT INTO stories (slug, category, length, status, source, source_json) VALUES (?, ?, ?, 'draft', ?, ?) RETURNING id",
            [$this->makeSlug($title), $category, $length, trim((string)($in['source'] ?? '')), $source]
        );
        return ['id' => (int)$row['id']];
    }

    public static function splitSentences(string $body): array {
        $out = [];
        foreach (preg_split('/\R+/u', trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // A pasted paragraph: split after . ! ? (and a closing quote) before a capital or a quote.
            foreach (preg_split('/(?<=[.!?…])["”’]?\K\s+(?=["“‘]?\p{Lu})/u', $line) as $s) {
                if (trim($s) !== '') {
                    $out[] = trim($s);
                }
            }
        }
        return $out;
    }

    /** Sentences per admin build step: each step must answer well within Cloudflare's 100 s. */
    public const STEP_SENTENCES = 4;

    /** Number of build steps for one language of a story. */
    public function stepCount(int $id): int {
        $story = $this->find($id);
        $src = $story ? json_decode((string)$story['source_json'], true) : null;
        return $src ? (int)ceil(count($src['sentences']) / self::STEP_SENTENCES) : 0;
    }

    /**
     * One admin build step: sentences [step*4, step*4+4) of one language.
     * Parts wait in stories.build_json; the last part of a language moves
     * the whole language into story_texts.
     * @return array{ok?: bool, done?: bool, title?: string, error?: string, detail?: string}
     */
    public function buildStep(int $id, string $lang, int $step, StoryGenerator $gen): array {
        $story = $this->find($id);
        $src = $story ? json_decode((string)$story['source_json'], true) : null;
        $steps = $this->stepCount($id);
        if (!$src || !Language::info($lang) || $step < 0 || $step >= $steps) {
            return ['error' => 'not_found'];
        }
        $build = json_decode((string)($story['build_json'] ?? ''), true) ?: [];
        $have = $step === 0 ? ['title' => '', 'summary' => '', 'sentences' => []] : ($build[$lang] ?? null);
        if (!$have || count($have['sentences']) !== $step * self::STEP_SENTENCES) {
            return ['error' => 'out_of_order'];
        }
        try {
            $part = $gen->compose($src, $lang, $step * self::STEP_SENTENCES, self::STEP_SENTENCES, array_column($have['sentences'], 'text'));
        } catch (\Throwable $e) {
            error_log("story {$id} {$lang} step {$step}: " . $e->getMessage());
            return ['error' => 'generation', 'detail' => mb_substr($e->getMessage(), 0, 300)];
        }
        if ($step === 0) {
            $have['title'] = $part['title'];
            $have['summary'] = $part['summary'];
        }
        $have['sentences'] = array_merge($have['sentences'], $part['sentences']);
        if ($step < $steps - 1) {
            $build[$lang] = $have;
            $this->db->execute('UPDATE stories SET build_json = ? WHERE id = ?', [json_encode($build, JSON_UNESCAPED_UNICODE), $id]);
            return ['ok' => true, 'done' => false];
        }
        unset($build[$lang]);
        $this->db->execute(
            'INSERT INTO story_texts (story_id, lang, title, summary, content) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (story_id, lang) DO UPDATE SET title = EXCLUDED.title, summary = EXCLUDED.summary, content = EXCLUDED.content, updated_at = CURRENT_TIMESTAMP',
            [$id, $lang, $have['title'], $have['summary'], json_encode(['sentences' => $have['sentences']], JSON_UNESCAPED_UNICODE)]
        );
        $this->db->execute('UPDATE stories SET build_json = ? WHERE id = ?', [$build ? json_encode($build, JSON_UNESCAPED_UNICODE) : null, $id]);
        return ['ok' => true, 'done' => true, 'title' => $have['title']];
    }

    /** Publishing needs every listed language; unpublishing always works. */
    public function setStatus(int $id, string $status): array {
        if (!in_array($status, ['draft', 'published'], true) || !$this->find($id)) {
            return ['error' => 'not_found'];
        }
        if ($status === 'published') {
            $have = $this->languagesOf($id);
            $missing = array_diff(array_column(Language::registry(), 'code'), $have);
            if ($missing) {
                return ['error' => 'missing_languages', 'missing' => array_values($missing)];
            }
        }
        $this->db->execute(
            "UPDATE stories SET status = ?, updated_at = CURRENT_TIMESTAMP,
                    published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, CURRENT_TIMESTAMP) ELSE published_at END
             WHERE id = ?",
            [$status, $status, $id]
        );
        return ['ok' => true];
    }

    public function delete(int $id): bool {
        return $this->db->execute('DELETE FROM stories WHERE id = ?', [$id]) > 0;
    }

    /** A new category with its name in every language (the admin gives English; the rest can be filled later). */
    public function addCategory(string $slug, array $names): array {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), '-');
        if ($slug === '' || strlen($slug) > 40 || trim((string)($names['en'] ?? '')) === '') {
            return ['error' => 'invalid'];
        }
        if ($this->db->fetchOne('SELECT 1 FROM story_categories WHERE slug = ?', [$slug])) {
            return ['error' => 'exists'];
        }
        $this->db->execute('INSERT INTO story_categories (slug, names, sort_order) VALUES (?, ?, 100)', [$slug, json_encode($names, JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'slug' => $slug];
    }
}
