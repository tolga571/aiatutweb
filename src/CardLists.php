<?php
namespace App\Src;

/**
 * Card lists, like playlists: per language one default "Saved" list plus the
 * user's own. A card can be in several lists; taking it out of a list never
 * deletes the card. Every method checks that the list (and the cards) belong
 * to the user, so ids from the client are never trusted.
 */
class CardLists {
    public const MAX_LISTS = 50;
    public const MAX_ITEMS = 1000;
    public const MAX_NAME = 60;

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /** The user's default list for a language, created on first use. */
    public function defaultId(int $userId, string $lang): int {
        $row = $this->db->fetchOne('SELECT id FROM card_lists WHERE user_id = ? AND language = ? AND is_default', [$userId, $lang]);
        if ($row) {
            return (int)$row['id'];
        }
        $this->db->execute(
            "INSERT INTO card_lists (user_id, language, name, is_default) VALUES (?, ?, '', TRUE)
             ON CONFLICT (user_id, language) WHERE is_default DO NOTHING",
            [$userId, $lang]
        );
        return (int)$this->db->fetchOne('SELECT id FROM card_lists WHERE user_id = ? AND language = ? AND is_default', [$userId, $lang])['id'];
    }

    /**
     * Lists for one language, default first, with card and learned counts.
     * @return array<int, array{id:int, name:string, is_default:bool, cards:int, learned:int}>
     */
    public function all(int $userId, string $lang): array {
        $this->defaultId($userId, $lang);
        $rows = $this->db->fetchAll(
            'SELECT l.id, l.name, l.is_default, COUNT(i.vocab_id) AS cards, COUNT(uf.learned_at) AS learned
             FROM card_lists l
             LEFT JOIN card_list_items i ON i.list_id = l.id
             LEFT JOIN user_flashcards uf ON uf.vocab_id = i.vocab_id AND uf.user_id = l.user_id
             WHERE l.user_id = ? AND l.language = ?
             GROUP BY l.id
             ORDER BY l.is_default DESC, l.created_at ASC, l.id ASC',
            [$userId, $lang]
        );
        return array_map([$this, 'shape'], $rows);
    }

    /** One of the user's lists, or null. */
    public function get(int $userId, int $listId): ?array {
        $row = $this->db->fetchOne(
            'SELECT l.id, l.name, l.is_default, l.language,
                    (SELECT COUNT(*) FROM card_list_items i WHERE i.list_id = l.id) AS cards,
                    0 AS learned
             FROM card_lists l WHERE l.id = ? AND l.user_id = ?',
            [$listId, $userId]
        );
        return $row ? $this->shape($row) + ['language' => $row['language']] : null;
    }

    /** @return array{list?: array, error?: string} */
    public function create(int $userId, string $lang, string $name): array {
        $name = $this->cleanName($name);
        if ($name === '') {
            return ['error' => 'list_name_required'];
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            return ['error' => 'too_long'];
        }
        $n = (int)($this->db->fetchOne('SELECT COUNT(*) AS c FROM card_lists WHERE user_id = ? AND NOT is_default', [$userId])['c'] ?? 0);
        if ($n >= self::MAX_LISTS) {
            return ['error' => 'list_limit'];
        }
        $this->defaultId($userId, $lang);
        $this->db->execute('INSERT INTO card_lists (user_id, language, name) VALUES (?, ?, ?)', [$userId, $lang, $name]);
        return ['list' => $this->get($userId, $this->db->lastInsertId('card_lists'))];
    }

    /** The default list keeps its (translated) name. */
    public function rename(int $userId, int $listId, string $name): array {
        $list = $this->get($userId, $listId);
        if (!$list) {
            return ['error' => 'list_not_found'];
        }
        if ($list['is_default']) {
            return ['error' => 'list_default'];
        }
        $name = $this->cleanName($name);
        if ($name === '') {
            return ['error' => 'list_name_required'];
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            return ['error' => 'too_long'];
        }
        $this->db->execute('UPDATE card_lists SET name = ?, updated_at = ' . $this->db->now() . ' WHERE id = ? AND user_id = ?', [$name, $listId, $userId]);
        return ['list' => $this->get($userId, $listId)];
    }

    /** Deletes the list only — its cards stay in the deck and in other lists. */
    public function delete(int $userId, int $listId): array {
        $list = $this->get($userId, $listId);
        if (!$list) {
            return ['error' => 'list_not_found'];
        }
        if ($list['is_default']) {
            return ['error' => 'list_default'];
        }
        $this->db->execute('DELETE FROM card_lists WHERE id = ? AND user_id = ?', [$listId, $userId]);
        return ['deleted' => true];
    }

    /**
     * Puts cards into a list; with $fromId they leave that list (a move).
     * Only the user's cards in the list's language are taken.
     * @param int[] $vocabIds
     * @return array{added?: int, list?: array, error?: string}
     */
    public function add(int $userId, int $listId, array $vocabIds, int $fromId = 0): array {
        $list = $this->get($userId, $listId);
        if (!$list) {
            return ['error' => 'list_not_found'];
        }
        $from = $fromId ? $this->get($userId, $fromId) : null;
        if ($fromId && !$from) {
            return ['error' => 'list_not_found'];
        }
        $ids = $this->ownedCards($userId, $list['language'], $vocabIds);
        if (!$ids) {
            return ['error' => 'not_found'];
        }
        $have = (int)$list['cards'];
        $new = $this->db->fetchAll(
            'SELECT v.id FROM vocabulary_words v WHERE v.id IN (' . $this->marks($ids) . ')
               AND NOT EXISTS (SELECT 1 FROM card_list_items i WHERE i.list_id = ? AND i.vocab_id = v.id)',
            array_merge($ids, [$listId])
        );
        if ($have + count($new) > self::MAX_ITEMS) {
            return ['error' => 'list_full'];
        }
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $this->db->insertIgnore('card_list_items', ['list_id', 'vocab_id'], [$listId, $id]);
            }
            if ($from && $fromId !== $listId) {
                $this->db->execute(
                    'DELETE FROM card_list_items WHERE list_id = ? AND vocab_id IN (' . $this->marks($ids) . ')',
                    array_merge([$fromId], $ids)
                );
            }
            $this->touch($listId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CardLists::add: ' . $e->getMessage());
            return ['error' => 'generic'];
        }
        return ['added' => count($new), 'list' => $this->get($userId, $listId)];
    }

    /** Takes cards out of a list (the cards themselves stay). */
    public function remove(int $userId, int $listId, array $vocabIds): array {
        $list = $this->get($userId, $listId);
        if (!$list) {
            return ['error' => 'list_not_found'];
        }
        $ids = $this->ints($vocabIds);
        if ($ids) {
            $this->db->execute(
                'DELETE FROM card_list_items WHERE list_id = ? AND vocab_id IN (' . $this->marks($ids) . ')',
                array_merge([$listId], $ids)
            );
            $this->touch($listId);
        }
        return ['list' => $this->get($userId, $listId)];
    }

    /** Ids of the lists a card is in (for the "Save to…" checkboxes). */
    public function listsOf(int $userId, int $vocabId): array {
        return array_map('intval', array_column($this->db->fetchAll(
            'SELECT i.list_id FROM card_list_items i JOIN card_lists l ON l.id = i.list_id
             WHERE i.vocab_id = ? AND l.user_id = ? ORDER BY i.list_id',
            [$vocabId, $userId]
        ), 'list_id'));
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function shape(array $r): array {
        return [
            'id' => (int)$r['id'],
            'name' => (string)$r['name'],
            'is_default' => $r['is_default'] === true || $r['is_default'] === 't' || $r['is_default'] === 1 || $r['is_default'] === '1',
            'cards' => (int)$r['cards'],
            'learned' => (int)$r['learned'],
        ];
    }

    private function cleanName(string $name): string {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    /** @return int[] at most MAX_ITEMS distinct positive ints */
    private function ints(array $ids): array {
        $out = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int)$id > 0) {
                $out[(int)$id] = true;
            }
            if (count($out) >= self::MAX_ITEMS) {
                break;
            }
        }
        return array_keys($out);
    }

    private function ownedCards(int $userId, string $lang, array $vocabIds): array {
        $ids = $this->ints($vocabIds);
        if (!$ids) {
            return [];
        }
        return array_map('intval', array_column($this->db->fetchAll(
            'SELECT id FROM vocabulary_words WHERE user_id = ? AND language = ? AND id IN (' . $this->marks($ids) . ')',
            array_merge([$userId, $lang], $ids)
        ), 'id'));
    }

    private function marks(array $ids): string {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    private function touch(int $listId): void {
        $this->db->execute('UPDATE card_lists SET updated_at = ' . $this->db->now() . ' WHERE id = ?', [$listId]);
    }
}
