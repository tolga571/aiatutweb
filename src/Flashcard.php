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
            'SELECT vw.*, uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.last_reviewed, uf.correct_count, uf.incorrect_count, uf.status as review_status,
                    uf.id as flashcard_id
             FROM vocabulary_words vw
             JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
             WHERE vw.user_id = ? AND vw.language = ? AND uf.next_review <= ' . $this->db->now() . ' AND (uf.status != "new" OR uf.status IS NULL)
             ORDER BY uf.next_review ASC
             LIMIT ?',
            [$userId, $lang, $limit]
        );

        // Fill remaining slots with new cards, max 20 new cards per session
        $newLimit = min(20, $limit - count($due));
        if ($newLimit > 0) {
            $new = $this->db->fetchAll(
                'SELECT vw.*, uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                        uf.last_reviewed, uf.correct_count, uf.incorrect_count, uf.status as review_status,
                        uf.id as flashcard_id
                 FROM vocabulary_words vw
                 JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
                 WHERE vw.user_id = ? AND vw.language = ? AND uf.status = "new"
                 ORDER BY vw.id ASC
                 LIMIT ?',
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
            'SELECT vw.*, 
                    COALESCE(uf.status, "new") as review_status,
                    uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.correct_count, uf.incorrect_count, uf.id as flashcard_id
             FROM vocabulary_words vw
             LEFT JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
             WHERE vw.user_id = ? AND vw.language = ? AND vw.source = "chat"
             ORDER BY vw.created_at DESC
             LIMIT ?',
            [$userId, $lang, $limit]
        );
    }

    /**
     * Get all cards with optional category/search filters.
     */
    public function getAllCards(int $userId, string $lang, ?string $category = null, ?string $search = null, int $limit = 60): array {
        $sql = 'SELECT vw.*, 
                    COALESCE(uf.status, "new") as review_status,
                    uf.ease_factor, uf.interval, uf.repetitions, uf.next_review,
                    uf.correct_count, uf.incorrect_count, uf.id as flashcard_id
                FROM vocabulary_words vw
                LEFT JOIN user_flashcards uf ON uf.vocab_id = vw.id AND uf.user_id = vw.user_id
                WHERE vw.user_id = ? AND vw.language = ?';
        $params = [$userId, $lang];

        if ($category && $category !== 'all') {
            $sql .= ' AND vw.category = ?';
            $params[] = $category;
        }
        if ($search) {
            $sql .= ' AND (vw.word LIKE ? OR vw.translation LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= ' ORDER BY vw.category ASC, vw.id ASC LIMIT ?';
        $params[] = $limit;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Review a card and update SM-2 parameters.
     * @param int $quality 0=Again, 1=Hard, 2=Good, 3=Easy
     * @return array Updated card data with XP earned
     */
    public function reviewCard(int $userId, int $vocabId, int $quality): array {
        $quality = max(0, min(3, $quality));

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
                 status = ?
             WHERE user_id = ? AND vocab_id = ?',
            [
                $sm2['ease_factor'], $sm2['interval'], $sm2['repetitions'],
                $nextReview, $correctDelta, $incorrectDelta, $status,
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

    /**
     * Import static cards from flashcards_data.php for a user.
     */
    public function importStaticCards(int $userId, string $targetLang, string $nativeLang): array {
        $vocabData = require __DIR__ . '/../data/flashcards_data.php';

        if (!isset($vocabData[$targetLang])) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Language not available'];
        }

        $cards = $vocabData[$targetLang];
        $supportedNativeLangs = ['en', 'de', 'fr', 'es', 'zh', 'ja', 'ar', 'tr'];
        $useLang = in_array($nativeLang, $supportedNativeLangs, true) ? $nativeLang : 'en';

        $imported = 0;
        $skipped = 0;

        foreach ($cards as $card) {
            // Check if already imported
            $existing = $this->db->fetchOne(
                'SELECT id FROM vocabulary_words WHERE user_id = ? AND word = ? AND language = ?',
                [$userId, $card['word'], $targetLang]
            );

            if ($existing) {
                $skipped++;
                continue;
            }

            // Resolve translation
            $translation = $card['translations'][$useLang] ?? $card['translations']['en'] ?? '';
            $exampleTranslation = $card['example_translations'][$useLang] ?? $card['example_translations']['en'] ?? '';

            $this->db->execute(
                'INSERT INTO vocabulary_words (user_id, word, translation, pronunciation, example, example_translation, category, level, language, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $card['word'],
                    $translation,
                    $card['pronunciation'] ?? '',
                    $card['example'] ?? '',
                    $exampleTranslation,
                    $card['category'] ?? 'General',
                    $card['level'] ?? 'A1',
                    $targetLang,
                    'static'
                ]
            );
            $vocabId = $this->db->lastInsertId('vocabulary_words');

            // Auto-create flashcard record
            $this->db->insertIgnore('user_flashcards', ['user_id', 'vocab_id'], [$userId, $vocabId]);

            $imported++;
        }

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
             WHERE uf.user_id = ? AND vw.language = ? AND uf.next_review <= ' . $this->db->now(),
            [$userId, $lang]
        );

        $mastered = $this->db->fetchOne(
            'SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.status = "mastered"',
            [$userId, $lang]
        );

        $learning = $this->db->fetchOne(
            'SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.status = "learning"',
            [$userId, $lang]
        );

        $newCards = $this->db->fetchOne(
            'SELECT COUNT(*) as c FROM user_flashcards uf
             JOIN vocabulary_words vw ON vw.id = uf.vocab_id
             WHERE uf.user_id = ? AND vw.language = ? AND uf.status = "new"',
            [$userId, $lang]
        );

        $chatWords = $this->db->fetchOne(
            'SELECT COUNT(*) as c FROM vocabulary_words WHERE user_id = ? AND language = ? AND source = "chat"',
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

        return [
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
