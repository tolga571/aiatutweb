<?php
namespace App\Src;

class Flashcard {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * SM-2 Algorithm: Calculate next review parameters based on quality rating.
     * @param int $quality 0=Again, 1=Hard, 2=Good, 3=Easy
     */
    private function calculateSM2(float $easeFactor, int $interval, int $repetitions, int $quality): array {
        if ($quality < 2) {
            // Failed: reset repetitions, short interval
            $repetitions = 0;
            $interval = ($quality === 0) ? 1 : 2; // Review again soon or hard
            $easeFactor = max(1.3, $easeFactor - 0.2);
        } else {
            // Passed
            $repetitions++;
            if ($repetitions === 1) {
                $interval = 1;
            } elseif ($repetitions === 2) {
                $interval = 6;
            } else {
                $interval = (int)round($interval * $easeFactor);
            }
            // Adjust ease factor
            $easeFactor = max(1.3, $easeFactor + (0.1 - (3 - $quality) * (0.08 + (3 - $quality) * 0.02)));
        }

        return [
            'ease_factor' => round($easeFactor, 2),
            'interval' => $interval,
            'repetitions' => $repetitions,
        ];
    }

    /**
     * Determine card status based on SM-2 parameters.
     */
    private function determineStatus(int $repetitions, int $interval): string {
        if ($repetitions === 0) return 'new';
        if ($interval < 7) return 'learning';
        if ($interval >= 21) return 'mastered';
        return 'review';
    }

    /**
     * Get cards that are due for review (next_review <= now).
     */
    public function getDueCards(int $userId, string $lang, int $limit = 20): array {
        // Prioritize actual reviews (not new)
        $due = $this->db->fetchAll(
            "SELECT vw.*, uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.last_reviewed, uf.correct_count, uf.incorrect_count, uf.status as review_status,
                    uf.id as flashcard_id, uf.learned_at
             FROM vocabulary_words vw
             JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
             WHERE vw.user_id = ? AND vw.language = ? AND uf.learned_at IS NULL AND uf.next_review <= " . $this->db->now() . " AND (uf.status != 'new' OR uf.status IS NULL)
             ORDER BY uf.next_review ASC
             LIMIT ?",
            [$userId, $lang, $limit]
        );

        // Fill remaining slots with new cards, max 20 new cards per session
        $newLimit = min(20, $limit - count($due));
        if ($newLimit > 0) {
            $new = $this->db->fetchAll(
                "SELECT vw.*, uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                        uf.last_reviewed, uf.correct_count, uf.incorrect_count, uf.status as review_status,
                        uf.id as flashcard_id, uf.learned_at
                 FROM vocabulary_words vw
                 JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
                 WHERE vw.user_id = ? AND vw.language = ? AND uf.status = 'new' AND uf.learned_at IS NULL
                 ORDER BY vw.id ASC
                 LIMIT ?",
                [$userId, $lang, $newLimit]
            );
            $due = array_merge($due, $new);
        }

        return $due;
    }

    /**
     * Get words learned from chat conversations.
     */
    public function getChatWords(int $userId, string $lang, int $limit = 50): array {
        return $this->db->fetchAll(
            "SELECT vw.*, 
                    COALESCE(uf.status, 'new') as review_status,
                    uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.correct_count, uf.incorrect_count, uf.id as flashcard_id, uf.learned_at
             FROM vocabulary_words vw
             LEFT JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
             WHERE vw.user_id = ? AND vw.language = ? AND vw.source = 'chat'
             ORDER BY vw.created_at DESC
             LIMIT ?",
            [$userId, $lang, $limit]
        );
    }

    /** Word-list views: everything, starred, known, the user's own cards, saved from chat. */
    public const VIEWS = ['all', 'favorites', 'learned', 'mine', 'chat'];

    /** Comma-separated ids of the lists a card (vw) is in, e.g. "3,7" — '' for none. */
    private const LIST_IDS_SQL = "COALESCE((SELECT string_agg(cli2.list_id::text, ',' ORDER BY cli2.list_id) FROM card_list_items cli2 WHERE cli2.vocab_id = vw.id), '') AS list_ids";

    /**
     * Get cards with optional view/category/level/search filters.
     */
    public function getAllCards(int $userId, string $lang, ?string $category = null, ?string $search = null, int $limit = 60, ?string $level = null, string $view = 'all', int $offset = 0, int $listId = 0): array {
        // Cards actually due for spaced-repetition review are sorted to the
        // front so the deck reflects what the user should practice today,
        // not just alphabetical/category order.
        $sql = "SELECT vw.*,
                    COALESCE(uf.status, 'new') as review_status,
                    uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.correct_count, uf.incorrect_count, uf.id as flashcard_id, uf.learned_at,
                    CASE WHEN uf.next_review IS NOT NULL AND uf.next_review <= " . $this->db->now() . "
                         AND COALESCE(uf.status, 'new') != 'new' AND uf.learned_at IS NULL THEN 0 ELSE 1 END as due_priority,
                    " . self::LIST_IDS_SQL . "
                FROM vocabulary_words vw
                LEFT JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id";
        $params = [];
        // One list (see CardLists): its cards in the order they were added.
        if ($listId > 0) {
            $sql .= ' JOIN card_list_items cli ON cli.vocab_id = vw.id AND cli.list_id = ?';
            $params[] = $listId;
        }
        $sql .= ' WHERE vw.user_id = ? AND vw.language = ?';
        $params[] = $userId;
        $params[] = $lang;
        $order = $listId > 0 ? 'cli.added_at ASC, vw.id ASC' : 'due_priority ASC, vw.category ASC, vw.id ASC';

        switch ($view) {
            case 'favorites':
                $sql .= ' AND vw.is_favorite = TRUE';
                $order = 'vw.updated_at DESC NULLS LAST, vw.id DESC';
                break;
            case 'learned':
                $sql .= ' AND uf.learned_at IS NOT NULL';
                $order = 'uf.learned_at DESC, vw.id DESC';
                break;
            case 'mine':
                $sql .= " AND vw.source = 'user'";
                $order = 'vw.id DESC';
                break;
            case 'chat':
                $sql .= " AND vw.source = 'chat'";
                $order = 'vw.created_at DESC';
                break;
        }
        if ($category && $category !== 'all') {
            $sql .= ' AND vw.category = ?';
            $params[] = $category;
        }
        if ($level && $level !== 'all') {
            $sql .= ' AND vw.level = ?';
            $params[] = $level;
        }
        if ($search) {
            $sql .= ' AND (LOWER(vw.word) LIKE ? OR LOWER(vw.translation) LIKE ?)';
            $like = '%' . mb_strtolower($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = max(0, $offset);

        return $this->db->fetchAll($sql, $params);
    }

    /** One card of the user's, with its review state; null when it isn't theirs. */
    public function getCard(int $userId, int $vocabId): ?array {
        $row = $this->db->fetchOne(
            "SELECT vw.*, COALESCE(uf.status, 'new') as review_status,
                    uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.correct_count, uf.incorrect_count, uf.id as flashcard_id, uf.learned_at,
                    " . self::LIST_IDS_SQL . "
             FROM vocabulary_words vw
             LEFT JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
             WHERE vw.id = ? AND vw.user_id = ?",
            [$vocabId, $userId]
        );
        return $row ?: null;
    }

    /** Max length per editable field; anything longer is rejected, not cut. */
    public const FIELD_LIMITS = [
        'word' => 120, 'translation' => 200, 'pronunciation' => 120,
        'example' => 400, 'example_translation' => 400, 'category' => 40, 'note' => 1000,
    ];

    /**
     * Cleans user input for create/update. Only known fields are kept.
     * @return array{0: array<string,string>, 1: ?string} [fields, error code]
     */
    private function cleanFields(array $input): array {
        $out = [];
        foreach (self::FIELD_LIMITS as $key => $max) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $v = is_scalar($input[$key]) ? trim(preg_replace('/\s+/u', ' ', (string)$input[$key]) ?? '') : '';
            if ($key === 'note') {
                $v = trim((string)$input[$key]);
            }
            if (mb_strlen($v) > $max) {
                return [[], 'too_long'];
            }
            $out[$key] = $v;
        }
        if (array_key_exists('word', $out) && $out['word'] === '') {
            return [[], 'word_required'];
        }
        return [$out, null];
    }

    private function wordTaken(int $userId, string $lang, string $word, int $exceptId = 0): ?int {
        $row = $this->db->fetchOne(
            'SELECT id FROM vocabulary_words WHERE user_id = ? AND language = ? AND LOWER(word) = ? AND id != ?',
            [$userId, $lang, mb_strtolower($word), $exceptId]
        );
        return $row ? (int)$row['id'] : null;
    }

    /**
     * A card the user wrote themselves (source 'user').
     * @return array{card?: array, error?: string, existing_id?: int}
     */
    public function createCard(int $userId, string $lang, array $input): array {
        [$f, $err] = $this->cleanFields($input);
        if ($err) {
            return ['error' => $err];
        }
        if (($f['word'] ?? '') === '') {
            return ['error' => 'word_required'];
        }
        if ($id = $this->wordTaken($userId, $lang, $f['word'])) {
            return ['error' => 'duplicate', 'existing_id' => $id];
        }
        $level = in_array($input['level'] ?? '', self::PACK_LEVELS, true) ? $input['level'] : 'A1';
        $this->db->execute(
            'INSERT INTO vocabulary_words (user_id, word, translation, pronunciation, example, example_translation, category, level, language, source, note, is_favorite, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . $this->db->now() . ')',
            [
                $userId, $f['word'], $f['translation'] ?? '', $f['pronunciation'] ?? '',
                $f['example'] ?? '', $f['example_translation'] ?? '',
                ($f['category'] ?? '') !== '' ? $f['category'] : 'Custom',
                $level, $lang, 'user', $f['note'] ?? '',
                !empty($input['is_favorite']) ? 'true' : 'false',
            ]
        );
        $id = $this->db->lastInsertId('vocabulary_words');
        $this->db->insertIgnore('user_flashcards', ['user_id', 'vocab_id'], [$userId, $id]);
        return ['card' => $this->getCard($userId, $id)];
    }

    /** Edits any of the user's cards (starter, pack, chat or their own). */
    public function updateCard(int $userId, int $vocabId, array $input): array {
        $card = $this->getCard($userId, $vocabId);
        if (!$card) {
            return ['error' => 'not_found'];
        }
        [$f, $err] = $this->cleanFields($input);
        if ($err) {
            return ['error' => $err];
        }
        if (isset($f['word']) && ($id = $this->wordTaken($userId, $card['language'], $f['word'], $vocabId))) {
            return ['error' => 'duplicate', 'existing_id' => $id];
        }
        if (isset($f['category']) && $f['category'] === '') {
            $f['category'] = 'Custom';
        }
        if (in_array($input['level'] ?? '', self::PACK_LEVELS, true)) {
            $f['level'] = $input['level'];
        }
        if ($f) {
            $set = implode(', ', array_map(fn($k) => $k . ' = ?', array_keys($f)));
            $this->db->execute(
                'UPDATE vocabulary_words SET ' . $set . ', updated_at = ' . $this->db->now() . ' WHERE id = ? AND user_id = ?',
                array_merge(array_values($f), [$vocabId, $userId])
            );
        }
        return ['card' => $this->getCard($userId, $vocabId)];
    }

    public function deleteCard(int $userId, int $vocabId): bool {
        // user_flashcards rows go with it (ON DELETE CASCADE).
        return $this->db->execute('DELETE FROM vocabulary_words WHERE id = ? AND user_id = ?', [$vocabId, $userId]) > 0;
    }

    public function setFavorite(int $userId, int $vocabId, bool $on): ?array {
        $this->db->execute(
            'UPDATE vocabulary_words SET is_favorite = ?, updated_at = ' . $this->db->now() . ' WHERE id = ? AND user_id = ?',
            [$on ? 'true' : 'false', $vocabId, $userId]
        );
        return $this->getCard($userId, $vocabId);
    }

    /**
     * "I know this": takes the card out of the review queue and lists it under
     * learned. Undoing it puts the card straight back into review.
     */
    public function setLearned(int $userId, int $vocabId, bool $on): ?array {
        if (!$this->getCard($userId, $vocabId)) {
            return null;
        }
        $this->db->insertIgnore('user_flashcards', ['user_id', 'vocab_id'], [$userId, $vocabId]);
        if ($on) {
            $this->db->execute(
                'UPDATE user_flashcards SET learned_at = COALESCE(learned_at, ' . $this->db->now() . ') WHERE user_id = ? AND vocab_id = ?',
                [$userId, $vocabId]
            );
        } else {
            $this->db->execute(
                "UPDATE user_flashcards SET learned_at = NULL, next_review = " . $this->db->now() . ",
                        status = CASE WHEN status = 'mastered' THEN 'review' ELSE status END
                 WHERE user_id = ? AND vocab_id = ?",
                [$userId, $vocabId]
            );
        }
        return $this->getCard($userId, $vocabId);
    }

    /**
     * Adds the starter deck the first time a user opens a language (the old
     * rule re-added it whenever the deck had < 50 cards, which brought
     * deleted cards back). Returns how many cards were added.
     */
    public function ensureStarterDeck(int $userId, string $lang, string $nativeLang): int {
        if ($this->db->fetchOne('SELECT 1 AS x FROM flashcard_decks WHERE user_id = ? AND language = ?', [$userId, $lang])) {
            return 0;
        }
        $r = $this->importStaticCards($userId, $lang, $nativeLang);
        $this->db->insertIgnore('flashcard_decks', ['user_id', 'language'], [$userId, $lang]);
        return (int)($r['imported'] ?? 0);
    }

    /**
     * Languages the user can study cards in, with their card count per
     * language. $learnable is Language::listed('learn') (codes).
     */
    public function getLanguages(int $userId, array $learnable): array {
        $counts = [];
        foreach ($this->db->fetchAll('SELECT language, COUNT(*) AS c FROM vocabulary_words WHERE user_id = ? GROUP BY language', [$userId]) as $row) {
            $counts[$row['language']] = (int)$row['c'];
        }
        $starter = array_keys(require __DIR__ . '/../data/flashcards_data.php');
        $out = [];
        foreach ($learnable as $code) {
            $out[] = ['code' => $code, 'cards' => $counts[$code] ?? 0, 'starter_deck' => in_array($code, $starter, true)];
        }
        return $out;
    }

    /**
     * Review a card and update SM-2 parameters.
     * @param int $quality 0=Again, 1=Hard, 2=Good, 3=Easy
     * @return array Updated card data with XP earned
     */
    public function reviewCard(int $userId, int $vocabId, int $quality): array {
        $quality = max(0, min(3, $quality));
        if (!$this->getCard($userId, $vocabId)) {
            return ['success' => false, 'error' => 'not_found'];
        }

        // Get or create flashcard record
        $fc = $this->db->fetchOne(
            'SELECT * FROM user_flashcards WHERE user_id = ? AND vocab_id = ?',
            [$userId, $vocabId]
        );

        if (!$fc) {
            // Create new flashcard record
            $this->db->execute(
                'INSERT INTO user_flashcards (user_id, vocab_id) VALUES (?, ?)',
                [$userId, $vocabId]
            );
            $fc = [
                'ease_factor' => 2.5,
                'interval' => 0,
                'repetitions' => 0,
                'correct_count' => 0,
                'incorrect_count' => 0,
            ];
        }

        $sm2 = $this->calculateSM2(
            (float)$fc['ease_factor'],
            (int)$fc['interval'],
            (int)$fc['repetitions'],
            $quality
        );

        $status = $this->determineStatus($sm2['repetitions'], $sm2['interval']);
        $correctDelta = ($quality >= 2) ? 1 : 0;
        $incorrectDelta = ($quality < 2) ? 1 : 0;

        // Calculate next review datetime
        $nextReview = date('Y-m-d H:i:s', strtotime("+{$sm2['interval']} days"));

        $this->db->execute(
            'UPDATE user_flashcards 
             SET ease_factor = ?, interval = ?, repetitions = ?, 
                 next_review = ?, last_reviewed = ' . $this->db->now() . ',
                 correct_count = correct_count + ?, incorrect_count = incorrect_count + ?,
                 status = ?,
                 learned_at = CASE WHEN ?::boolean THEN COALESCE(learned_at, ' . $this->db->now() . ') ELSE learned_at END
             WHERE user_id = ? AND vocab_id = ?',
            [
                $sm2['ease_factor'], $sm2['interval'], $sm2['repetitions'],
                $nextReview, $correctDelta, $incorrectDelta, $status, $status === 'mastered' ? 'true' : 'false',
                $userId, $vocabId
            ]
        );

        // XP: 5 for correct, 2 for attempt
        $xp = ($quality >= 2) ? 5 : 2;
        $this->db->execute('UPDATE users SET xp = xp + ? WHERE id = ?', [$xp, $userId]);

        return [
            'success' => true,
            'xp' => $xp,
            'status' => $status,
            'next_review' => $nextReview,
            'interval' => $sm2['interval'],
            'ease_factor' => $sm2['ease_factor'],
        ];
    }

    /**
     * Add a word from chat to the vocabulary and create flashcard record.
     */
    public function addFromChat(int $userId, string $word, string $translation, string $pronunciation, string $lang, string $example = ''): ?int {
        // Check if word already exists for this user+lang
        $existing = $this->db->fetchOne(
            'SELECT id FROM vocabulary_words WHERE user_id = ? AND word = ? AND language = ?',
            [$userId, $word, $lang]
        );

        if ($existing) {
            return (int)$existing['id'];
        }

        $this->db->execute(
            'INSERT INTO vocabulary_words (user_id, word, translation, pronunciation, example, category, language, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $word, $translation, $pronunciation, $example, 'chat', $lang, 'chat']
        );
        $vocabId = $this->db->lastInsertId('vocabulary_words');

        // Auto-create flashcard record
        $this->db->insertIgnore('user_flashcards', ['user_id', 'vocab_id'], [$userId, $vocabId]);

        return $vocabId;
    }

    /** CEFR levels a vocabulary pack can be added for, in display order. */
    public const PACK_LEVELS = ['A1', 'A2', 'B1', 'B2'];
    private const NATIVE_LANGS = ['en', 'de', 'fr', 'es', 'zh', 'ja', 'ar', 'ru', 'el', 'hi', 'hy'];

    /**
     * Loads the extra vocabulary pack for a language (data/vocab/<lang>.json,
     * built from the sqlite-data-languages dictionaries by scripts/vocab/).
     * The language is whitelisted, so the path can never be user-controlled.
     */
    private function loadPack(string $lang): array {
        if (!in_array($lang, self::NATIVE_LANGS, true)) {
            return [];
        }
        $file = __DIR__ . '/../data/vocab/' . $lang . '.json';
        if (!is_file($file)) {
            return [];
        }
        $cards = json_decode((string)file_get_contents($file), true);
        return is_array($cards) ? $cards : [];
    }

    /**
     * Bulk-inserts cards the user does not have yet (case-insensitive by word)
     * plus their spaced-repetition rows. One SELECT + a few multi-row INSERTs
     * instead of two queries per card. Returns [imported, skipped].
     */
    private function insertCards(int $userId, string $targetLang, string $nativeLang, array $cards, string $source): array {
        $useLang = in_array($nativeLang, self::NATIVE_LANGS, true) ? $nativeLang : 'en';

        $have = [];
        foreach ($this->db->fetchAll('SELECT word FROM vocabulary_words WHERE user_id = ? AND language = ?', [$userId, $targetLang]) as $row) {
            $have[mb_strtolower($row['word'])] = true;
        }

        $rows = [];
        $skipped = 0;
        foreach ($cards as $card) {
            $word = (string)($card['word'] ?? '');
            $key = mb_strtolower($word);
            if ($word === '' || isset($have[$key])) {
                $skipped++;
                continue;
            }
            $have[$key] = true;
            $tr = $card['translations'] ?? [];
            $etr = $card['example_translations'] ?? [];
            $rows[] = [
                $userId, $word,
                $tr[$useLang] ?? $tr['en'] ?? '',
                $card['pronunciation'] ?? '',
                $card['example'] ?? '',
                $etr[$useLang] ?? $etr['en'] ?? '',
                $card['category'] ?? 'General',
                $card['level'] ?? 'A1',
                $targetLang,
                $source,
            ];
        }
        if (!$rows) {
            return [0, $skipped];
        }

        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($rows, 100) as $chunk) {
                $ph = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
                $this->db->execute(
                    'INSERT INTO vocabulary_words (user_id, word, translation, pronunciation, example, example_translation, category, level, language, source) VALUES ' . $ph,
                    array_merge(...$chunk)
                );
            }
            // Spaced-repetition row for every card that has none yet.
            $this->db->execute(
                'INSERT INTO user_flashcards (user_id, vocab_id)
                 SELECT vw.user_id, vw.id FROM vocabulary_words vw
                 WHERE vw.user_id = ? AND vw.language = ?
                 ON CONFLICT DO NOTHING',
                [$userId, $targetLang]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Flashcard::insertCards failed: ' . $e->getMessage());
            return [0, $skipped];
        }
        return [count($rows), $skipped];
    }

    /**
     * The original starter deck (data/flashcards_data.php). Behaviour is
     * unchanged: returns ['imported' => n, 'skipped' => n].
     */
    public function importStaticCards(int $userId, string $targetLang, string $nativeLang): array {
        $vocabData = require __DIR__ . '/../data/flashcards_data.php';

        if (!isset($vocabData[$targetLang])) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Language not available'];
        }
        [$imported, $skipped] = $this->insertCards($userId, $targetLang, $nativeLang, $vocabData[$targetLang], 'static');
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Levels of the extra vocabulary packs for a language with, per level,
     * the pack size and how many of those words the user already added.
     * @return array<string, array{total:int, added:int}>
     */
    public function getPacks(int $userId, string $lang): array {
        $totals = [];
        foreach ($this->loadPack($lang) as $card) {
            $lv = $card['level'] ?? 'A1';
            $totals[$lv] = ($totals[$lv] ?? 0) + 1;
        }
        $added = [];
        foreach ($this->db->fetchAll(
            "SELECT level, COUNT(*) AS c FROM vocabulary_words WHERE user_id = ? AND language = ? AND source = 'pack' GROUP BY level",
            [$userId, $lang]
        ) as $row) {
            $added[$row['level']] = (int)$row['c'];
        }
        $packs = [];
        foreach (self::PACK_LEVELS as $lv) {
            if (!empty($totals[$lv])) {
                $packs[$lv] = ['total' => $totals[$lv], 'added' => min($added[$lv] ?? 0, $totals[$lv])];
            }
        }
        return $packs;
    }

    /** Adds one CEFR level of the extra pack to the user's deck. */
    public function importPack(int $userId, string $targetLang, string $nativeLang, string $level): array {
        if (!in_array($level, self::PACK_LEVELS, true)) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Unknown level'];
        }
        $cards = array_values(array_filter($this->loadPack($targetLang), fn($c) => ($c['level'] ?? '') === $level));
        if (!$cards) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Level not available'];
        }
        [$imported, $skipped] = $this->insertCards($userId, $targetLang, $nativeLang, $cards, 'pack');
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Get learning statistics for the user.
     */
    public function getStats(int $userId, string $lang): array {
        $total = $this->db->fetchOne(
            'SELECT COUNT(*) as c FROM vocabulary_words WHERE user_id = ? AND language = ?',
            [$userId, $lang]
        );

        $due = $this->db->fetchOne(
            'SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.learned_at IS NULL AND uf.next_review <= ' . $this->db->now(),
            [$userId, $lang]
        );

        $mastered = $this->db->fetchOne(
            "SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.status = 'mastered'",
            [$userId, $lang]
        );

        $learning = $this->db->fetchOne(
            "SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.status = 'learning'",
            [$userId, $lang]
        );

        $newCards = $this->db->fetchOne(
            "SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.status = 'new'",
            [$userId, $lang]
        );

        $chatWords = $this->db->fetchOne(
            "SELECT COUNT(*) as c FROM vocabulary_words WHERE user_id = ? AND language = ? AND source = 'chat'",
            [$userId, $lang]
        );

        $totalCorrect = $this->db->fetchOne(
            'SELECT COALESCE(SUM(uf.correct_count), 0) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ?',
            [$userId, $lang]
        );

        $totalIncorrect = $this->db->fetchOne(
            'SELECT COALESCE(SUM(uf.incorrect_count), 0) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ?',
            [$userId, $lang]
        );

        $extra = $this->db->fetchOne(
            "SELECT COUNT(*) FILTER (WHERE vw.is_favorite) AS favorites,
                    COUNT(*) FILTER (WHERE uf.learned_at IS NOT NULL) AS learned,
                    COUNT(*) FILTER (WHERE vw.source = 'user') AS mine
             FROM vocabulary_words vw
             LEFT JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
             WHERE vw.user_id = ? AND vw.language = ?",
            [$userId, $lang]
        );

        return [
            'favorites' => (int)($extra['favorites'] ?? 0),
            'learned' => (int)($extra['learned'] ?? 0),
            'mine' => (int)($extra['mine'] ?? 0),
            'total' => (int)($total['c'] ?? 0),
            'due' => (int)($due['c'] ?? 0),
            'mastered' => (int)($mastered['c'] ?? 0),
            'learning' => (int)($learning['c'] ?? 0),
            'new' => (int)($newCards['c'] ?? 0),
            'chat_words' => (int)($chatWords['c'] ?? 0),
            'correct' => (int)($totalCorrect['c'] ?? 0),
            'incorrect' => (int)($totalIncorrect['c'] ?? 0),
        ];
    }

    /**
     * Get unique categories for the user's vocabulary.
     */
    public function getCategories(int $userId, string $lang): array {
        return $this->db->fetchAll(
            'SELECT DISTINCT category FROM vocabulary_words WHERE user_id = ? AND language = ? ORDER BY category',
            [$userId, $lang]
        );
    }
}
?>
