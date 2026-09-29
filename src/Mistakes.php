<?php
namespace App\Src;

/**
 * The "My mistakes" notebook. Every AI chat reply already stores the user's
 * grammar corrections in messages.metadata (corrections: [{original,
 * corrected, pronunciation, rule}]); this copies them into user_mistakes so
 * each one can carry its own practice progress.
 */
class Mistakes {
    /** Correct answers in a row needed before a mistake counts as learned. */
    public const LEARNED_AFTER = 2;

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Pulls corrections from AI messages newer than the last one already
     * copied. Cheap to call on every page load and after every chat reply.
     * Old messages don't record which language they were in, so backfilled
     * rows get the user's current target language.
     */
    public function sync(int $userId, string $language): void {
        $last = $this->db->fetchOne('SELECT COALESCE(MAX(message_id), 0) AS m FROM user_mistakes WHERE user_id = ?', [$userId]);
        $rows = $this->db->fetchAll(
            "SELECT m.id, m.metadata, m.created_at,
                    (SELECT u.content FROM messages u
                      WHERE u.conversation_id = m.conversation_id AND u.role = 'user' AND u.id < m.id
                      ORDER BY u.id DESC LIMIT 1) AS sentence
             FROM messages m JOIN conversations c ON c.id = m.conversation_id
             WHERE c.user_id = ? AND m.role = 'ai' AND m.id > ? AND m.metadata LIKE '%\"original\"%'
             ORDER BY m.id",
            [$userId, (int)($last['m'] ?? 0)]
        );
        foreach ($rows as $row) {
            $meta = json_decode($row['metadata'] ?? '', true);
            $corrections = is_array($meta['corrections'] ?? null) ? $meta['corrections'] : [];
            foreach (array_values($corrections) as $pos => $c) {
                $original = self::clip($c['original'] ?? '');
                $corrected = self::clip($c['corrected'] ?? '');
                if ($original === '' || $corrected === '' || $original === $corrected) {
                    continue;
                }
                $this->db->execute(
                    'INSERT INTO user_mistakes (user_id, message_id, position, language, original, corrected, pronunciation, rule, sentence, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (message_id, position) DO NOTHING',
                    [
                        $userId, (int)$row['id'], $pos, $language, $original, $corrected,
                        self::clip($c['pronunciation'] ?? ''), self::clip($c['rule'] ?? '', 1000),
                        self::clip($row['sentence'] ?? '', 2000), $row['created_at'],
                    ]
                );
            }
        }
    }

    public function getAll(int $userId, string $language): array {
        return $this->db->fetchAll(
            'SELECT id, original, corrected, pronunciation, rule, sentence, practice_count, correct_count,
                    learned_at IS NOT NULL AS learned, created_at
             FROM user_mistakes WHERE user_id = ? AND language = ?
             ORDER BY created_at DESC, id LIMIT 500',
            [$userId, $language]
        );
    }

    /**
     * Records one practice result. 'known' / 'again' come from the practice
     * session; 'learned' / 'unlearn' are the manual toggles on the list.
     * Returns the updated row, or null if it isn't this user's mistake.
     */
    public function review(int $userId, int $mistakeId, string $action): ?array {
        $sql = match ($action) {
            'known' => 'UPDATE user_mistakes SET practice_count = practice_count + 1, correct_count = correct_count + 1,
                        last_practiced_at = CURRENT_TIMESTAMP,
                        learned_at = CASE WHEN correct_count + 1 >= ' . self::LEARNED_AFTER . ' THEN COALESCE(learned_at, CURRENT_TIMESTAMP) ELSE learned_at END
                        WHERE id = ? AND user_id = ?',
            'again' => 'UPDATE user_mistakes SET practice_count = practice_count + 1, correct_count = 0,
                        last_practiced_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?',
            'learned' => 'UPDATE user_mistakes SET learned_at = COALESCE(learned_at, CURRENT_TIMESTAMP) WHERE id = ? AND user_id = ?',
            'unlearn' => 'UPDATE user_mistakes SET learned_at = NULL, correct_count = 0 WHERE id = ? AND user_id = ?',
            default => null,
        };
        if ($sql === null || $this->db->execute($sql, [$mistakeId, $userId]) === 0) {
            return null;
        }
        return $this->db->fetchOne(
            'SELECT id, practice_count, correct_count, learned_at IS NOT NULL AS learned FROM user_mistakes WHERE id = ?',
            [$mistakeId]
        );
    }

    private static function clip(mixed $value, int $max = 500): string {
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }
}
