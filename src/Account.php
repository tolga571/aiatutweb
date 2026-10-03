<?php
namespace App\Src;

/**
 * Account-level operations shared by the website and the mobile API, so
 * deleting or exporting an account behaves the same everywhere (Google Play
 * requires in-app account deletion too).
 */
class Account
{
    /** Everything we store about the user (GDPR export). */
    public static function export(Database $db, int $userId): array
    {
        $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]) ?? [];
        unset($user['password'], $user['totp_secret']);
        return [
            'exported_at' => date('c'),
            'profile' => $user,
            'conversations' => $db->fetchAll('SELECT id, topic_id, topic_label, created_at, updated_at FROM conversations WHERE user_id = ? ORDER BY created_at', [$userId]),
            'messages' => $db->fetchAll(
                'SELECT m.id, m.conversation_id, m.role, m.content, m.translation, m.correction, m.created_at
                 FROM messages m JOIN conversations c ON c.id = m.conversation_id
                 WHERE c.user_id = ? ORDER BY m.created_at',
                [$userId]
            ),
            'vocabulary_words' => $db->fetchAll('SELECT * FROM vocabulary_words WHERE user_id = ?', [$userId]),
            'flashcards' => $db->fetchAll('SELECT * FROM user_flashcards WHERE user_id = ?', [$userId]),
            'alphabet_progress' => $db->fetchAll('SELECT * FROM alphabet_progress WHERE user_id = ?', [$userId]),
            'mistakes' => $db->fetchAll(
                'SELECT language, original, corrected, rule, sentence, practice_count, correct_count, learned_at, created_at FROM user_mistakes WHERE user_id = ? ORDER BY created_at',
                [$userId]
            ),
            'learning_notes' => $db->fetchAll('SELECT * FROM learning_notes WHERE user_id = ?', [$userId]),
            'billing_activity' => $db->fetchAll(
                'SELECT event_type, provider, plan, billing_interval, detail, created_at FROM activity_events WHERE user_id = ? ORDER BY created_at',
                [$userId]
            ),
        ];
    }

    /**
     * Deletes the account and everything that cascades from it. Billing is
     * cancelled first, best-effort: the right to erasure doesn't wait on a
     * provider API call.
     */
    public static function delete(Database $db, array $user, ?DodoClient $dodo, ?FastSpringClient $fastspring, ?PaddleClient $paddle, string $via = 'web'): void
    {
        $userId = (int)$user['id'];
        try {
            if (!empty($user['dodo_subscription_id']) && $dodo && $dodo->isConfigured()) {
                $dodo->cancelSubscription($user['dodo_subscription_id']);
            } elseif (!empty($user['fastspring_subscription_id']) && $fastspring && $fastspring->isConfigured()) {
                $fastspring->cancelSubscription($user['fastspring_subscription_id']);
            } elseif (!empty($user['paddle_subscription_id']) && $paddle && $paddle->isConfigured()) {
                $paddle->cancelSubscription($user['paddle_subscription_id'], 'immediately');
            }
        } catch (\Throwable $e) {
            error_log("Account::delete: cancelling billing for user {$userId} failed: " . $e->getMessage());
        }
        ActivityLog::record($db, $userId, 'account_deleted', null, $user['plan_status'] ?? null, null,
            "user {$userId} ({$user['email']}) deleted their own account" . ($via !== 'web' ? " ({$via})" : ''));
        // token_usage has no ON DELETE CASCADE from users — every other
        // user-owned table does, so this is the only manual cleanup needed
        // before the DELETE below can succeed.
        $db->execute('DELETE FROM token_usage WHERE user_id = ?', [$userId]);
        $db->execute('DELETE FROM users WHERE id = ?', [$userId]);
    }
}
