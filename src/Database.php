<?php
namespace App\Src;

class Database {
    private \PDO $pdo;

    public function __construct(string $dbUrl) {
        if (empty($dbUrl)) {
            throw new \RuntimeException('DATABASE_URL environment variable is required. Set it to a PostgreSQL connection string.');
        }
        if (!extension_loaded('pdo_pgsql')) {
            throw new \RuntimeException('pdo_pgsql extension is not installed. Install it or enable it in php.ini / nixpacks.toml.');
        }

        $parts = @parse_url($dbUrl);
        if (!$parts || !isset($parts['host'])) {
            throw new \RuntimeException('Cannot parse DATABASE_URL. Expected format: postgresql://user:password@host:5432/dbname');
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? '5432';
        $dbname = ltrim($parts['path'] ?? '/postgres', '/');
        $user = $parts['user'] ?? 'postgres';
        $pass = $parts['pass'] ?? '';

        if (empty($host) || empty($dbname)) {
            throw new \RuntimeException('DATABASE_URL missing host or database name.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
            $host, $port, $dbname, $user, $pass
        );

        $this->pdo = new \PDO($dsn);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        error_log('Database: PostgreSQL connected (' . $host . ')');
        $this->initialize();
    }

    private function exec(string $sql): void {
        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                return;
            }
            throw $e;
        }
    }

    private function initialize(): void {
        $this->exec("CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            name TEXT,
            native_lang TEXT DEFAULT 'en',
            target_lang TEXT DEFAULT 'en',
            cefr_level TEXT DEFAULT 'A1',
            learning_goal TEXT DEFAULT 'conversation',
            interest_area TEXT DEFAULT 'general',
            role TEXT DEFAULT 'user',
            plan_status TEXT DEFAULT 'inactive',
            xp INTEGER DEFAULT 0,
            onboarding_completed INTEGER DEFAULT 0,
            has_paid INTEGER DEFAULT 0,
            profile_image TEXT DEFAULT NULL,
            google_id TEXT DEFAULT NULL,
            streak_count INTEGER DEFAULT 0,
            last_activity_date DATE DEFAULT NULL,
            payment_pending_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS conversations (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            topic_id TEXT,
            topic_label TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS messages (
            id SERIAL PRIMARY KEY,
            conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            translation TEXT,
            correction TEXT,
            metadata TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS admins (
            id SERIAL PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            name TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS admin_audit (
            id SERIAL PRIMARY KEY,
            admin_id INTEGER NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
            action TEXT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS learning_notes (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title TEXT,
            content TEXT NOT NULL,
            source TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS posts (
            id SERIAL PRIMARY KEY,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            content TEXT NOT NULL,
            category TEXT DEFAULT 'blog',
            language TEXT DEFAULT 'en',
            published INTEGER DEFAULT 1,
            is_premium INTEGER DEFAULT 0,
            author_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS token_usage (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id),
            used_this_month INTEGER DEFAULT 0,
            bonus_limit INTEGER DEFAULT 0,
            last_reset_month TEXT
        )");
        try {
            $this->exec("ALTER TABLE token_usage ADD COLUMN used_this_month INTEGER DEFAULT 0");
            $this->exec("ALTER TABLE token_usage ADD COLUMN bonus_limit INTEGER DEFAULT 0");
            $this->exec("ALTER TABLE token_usage ADD COLUMN last_reset_month TEXT");
        } catch (\Exception $e) {
            // Columns might already exist
        }
        $this->exec("CREATE TABLE IF NOT EXISTS vocabulary_words (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            word TEXT NOT NULL,
            translation TEXT,
            pronunciation TEXT DEFAULT '',
            example TEXT DEFAULT '',
            example_translation TEXT DEFAULT '',
            category TEXT DEFAULT 'chat',
            level TEXT DEFAULT 'A1',
            language TEXT NOT NULL,
            source TEXT DEFAULT 'chat',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS user_flashcards (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            vocab_id INTEGER NOT NULL REFERENCES vocabulary_words(id) ON DELETE CASCADE,
            ease_factor REAL DEFAULT 2.5,
            interval INTEGER DEFAULT 0,
            repetitions INTEGER DEFAULT 0,
            next_review TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_reviewed TIMESTAMP,
            correct_count INTEGER DEFAULT 0,
            incorrect_count INTEGER DEFAULT 0,
            status TEXT DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, vocab_id)
        )");
        // Flashcards v2: favourites, a free-text note, manual "I know this"
        // (learned_at — also set when SM-2 reaches mastered) and a marker
        // for languages whose starter deck was already added, so cards the
        // user deleted don't come back on the next visit.
        $this->pdo->exec("ALTER TABLE vocabulary_words ADD COLUMN IF NOT EXISTS is_favorite BOOLEAN NOT NULL DEFAULT FALSE");
        $this->pdo->exec("ALTER TABLE vocabulary_words ADD COLUMN IF NOT EXISTS note TEXT NOT NULL DEFAULT ''");
        $this->pdo->exec("ALTER TABLE vocabulary_words ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NULL");
        $this->pdo->exec("ALTER TABLE user_flashcards ADD COLUMN IF NOT EXISTS learned_at TIMESTAMP DEFAULT NULL");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_vocabulary_words_user_lang ON vocabulary_words (user_id, language)");
        // Abuse limits (AbuseGuard): which IP each free trial came from, and
        // small app-wide flags such as "budget alert already sent this month".
        $this->exec("CREATE TABLE IF NOT EXISTS trial_grants (
            user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            ip TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_trial_grants_ip ON trial_grants (ip, created_at)");
        $this->exec("CREATE TABLE IF NOT EXISTS app_state (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS flashcard_decks (
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            language TEXT NOT NULL,
            seeded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, language)
        )");
        // Card lists, like playlists (see CardLists): per language, one
        // default "Saved" list (name '' — the label is translated) plus the
        // user's own. A card can sit in several lists; review progress stays
        // on the card (user_flashcards), so it counts in every list.
        $this->exec("CREATE TABLE IF NOT EXISTS card_lists (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            language TEXT NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_card_lists_user ON card_lists (user_id, language)");
        $this->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_card_lists_default ON card_lists (user_id, language) WHERE is_default");
        $this->exec("CREATE TABLE IF NOT EXISTS card_list_items (
            list_id INTEGER NOT NULL REFERENCES card_lists(id) ON DELETE CASCADE,
            vocab_id INTEGER NOT NULL REFERENCES vocabulary_words(id) ON DELETE CASCADE,
            added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (list_id, vocab_id)
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_card_list_items_vocab ON card_list_items (vocab_id)");
        // "My mistakes" notebook: one row per chat correction, copied out of
        // messages.metadata by Mistakes::sync(). (message_id, position) keeps
        // a re-sync from duplicating rows; deleting the conversation (or the
        // user) cascades here through messages.
        $this->exec("CREATE TABLE IF NOT EXISTS user_mistakes (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            message_id INTEGER NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
            position INTEGER NOT NULL DEFAULT 0,
            language TEXT NOT NULL,
            original TEXT NOT NULL,
            corrected TEXT NOT NULL,
            pronunciation TEXT,
            rule TEXT,
            sentence TEXT,
            practice_count INTEGER NOT NULL DEFAULT 0,
            correct_count INTEGER NOT NULL DEFAULT 0,
            last_practiced_at TIMESTAMP DEFAULT NULL,
            learned_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(message_id, position)
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_user_mistakes_user ON user_mistakes (user_id, language, created_at DESC)");
        // One row per Gemini request (see AiUsage). cost_usd is computed at
        // insert time from AiUsage::PRICES, so a later price change doesn't
        // rewrite history. SET NULL keeps totals intact when a user deletes
        // their account.
        $this->exec("CREATE TABLE IF NOT EXISTS ai_usage (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            feature TEXT NOT NULL DEFAULT 'chat',
            model TEXT,
            ok BOOLEAN NOT NULL DEFAULT TRUE,
            prompt_tokens INTEGER NOT NULL DEFAULT 0,
            output_tokens INTEGER NOT NULL DEFAULT 0,
            thought_tokens INTEGER NOT NULL DEFAULT 0,
            cost_usd NUMERIC(12,6) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_ai_usage_created ON ai_usage (created_at)");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_ai_usage_user ON ai_usage (user_id, created_at)");
        // Admin dashboard time series filter on these.
        $this->exec("CREATE INDEX IF NOT EXISTS idx_messages_created ON messages (created_at)");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_users_created ON users (created_at)");
        $this->exec("CREATE TABLE IF NOT EXISTS alphabet_progress (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            lang TEXT NOT NULL,
            letter_key TEXT NOT NULL,
            learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, lang, letter_key)
        )");
        $this->exec("CREATE TABLE IF NOT EXISTS sessions (
            id TEXT PRIMARY KEY,
            data TEXT NOT NULL DEFAULT '',
            expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions (expires_at)");
        $this->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id SERIAL PRIMARY KEY,
            ip TEXT NOT NULL,
            type TEXT NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts (ip, type, attempted_at)");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts (ip, type, attempted_at)");
        // Dedup for FastSpring webhook deliveries: retries resend the same
        // event id, so this is what actually decides "have I processed this
        // exact event before" — kept separate from the created-timestamp
        // ordering guard in FastSpringBilling, which answers a different
        // question (is this event older than one I've already applied).
        $this->exec("CREATE TABLE IF NOT EXISTS fastspring_processed_events (
            event_id TEXT PRIMARY KEY,
            processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        // Same dedup role as fastspring_processed_events, for Dodo's
        // webhook-id header (Standard Webhooks spec).
        $this->exec("CREATE TABLE IF NOT EXISTS dodo_processed_events (
            event_id TEXT PRIMARY KEY,
            processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        // Password reset tokens. Only the hash is stored — the plaintext
        // token lives solely in the emailed link — and each row is single
        // use (used_at set on consumption) with a short expiry.
        $this->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets (token_hash)");
        // Same shape as password_resets, for the register-time email
        // verification link.
        $this->exec("CREATE TABLE IF NOT EXISTS email_verifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_email_verifications_token ON email_verifications (token_hash)");
        // Human-readable billing/account timeline for the admin activity
        // monitor — every subscription start/upgrade/downgrade/cancel,
        // resume, and refund request, across all three providers, in one
        // place. user_id is nullable (ON DELETE SET NULL) so a deleted
        // user's history stays in the feed instead of vanishing with them.
        $this->exec("CREATE TABLE IF NOT EXISTS activity_events (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            event_type TEXT NOT NULL,
            provider TEXT,
            plan TEXT,
            billing_interval TEXT,
            detail TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_activity_events_created ON activity_events (created_at DESC)");
        $this->migrate();
    }

    private function migrate(): void {
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN payment_pending_at TIMESTAMP DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN paddle_subscription_id TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN paddle_customer_id TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN cancel_requested_at TIMESTAMP DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // 'api' = a real Paddle cancellation is scheduled and can be
            // undone via the API; 'manual' = only a support request was
            // recorded, nothing to undo automatically.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN cancel_method TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // Next renewal date reported by Paddle, shown to the user so
            // they know when they'll next be billed (or when a scheduled
            // change/cancellation actually takes effect).
            $this->pdo->exec("ALTER TABLE users ADD COLUMN next_billed_at TIMESTAMP DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // Set when a downgrade has been scheduled with Paddle for the
            // next billing period (standard practice: downgrades apply at
            // renewal rather than issuing an immediate prorated credit).
            $this->pdo->exec("ALTER TABLE users ADD COLUMN pending_plan_change TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN refund_requested_at TIMESTAMP DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // Which plan the user actually started checkout for, recorded
            // client-side right after Paddle confirms the purchase. Used so
            // the payment_pending_at timeout fallback (when the webhook is
            // slow to arrive) can grant the plan that was really bought
            // instead of guessing.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN pending_purchase_plan TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // 'month' or 'year' — which billing cycle the user's current
            // paid price is on. Needed alongside plan_status so the pricing
            // page can tell "on Pro monthly" apart from "on Pro yearly" and
            // offer the right switch action instead of showing both as the
            // same "current plan".
            $this->pdo->exec("ALTER TABLE users ADD COLUMN billing_interval TEXT DEFAULT 'month'");
        } catch (\Exception $e) {
        }
        try {
            // Mirrors pending_purchase_plan: the interval of the price the
            // user actually checked out for, so the payment_pending_at
            // timeout fallback can grant the right interval too, not just
            // the right tier.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN pending_purchase_interval TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // FastSpring subscription/account IDs, kept separate from the
            // Paddle ones so both providers can coexist during migration.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN fastspring_subscription_id TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN fastspring_account_id TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // The `created` (ms) timestamp of the last webhook event actually
            // applied to this user, so an out-of-order redelivery of an
            // older event can be detected and skipped instead of clobbering
            // state a newer event already wrote.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN fastspring_last_event_at BIGINT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN dodo_subscription_id TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN dodo_customer_id TEXT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // Dodo's webhook-timestamp header (Unix seconds), used the same
            // way as fastspring_last_event_at above to reject a redelivered
            // event older than the newest one already applied.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN dodo_last_event_at BIGINT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            // Shadow-mode equivalent for Paddle (the signature header's `ts`,
            // seconds since epoch): public/webhook.php only logs when an
            // incoming event would have been considered stale, it never
            // skips anything, so this is purely observational for now.
            $this->pdo->exec("ALTER TABLE users ADD COLUMN paddle_last_event_at BIGINT DEFAULT NULL");
        } catch (\Exception $e) {
        }
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP DEFAULT NULL");
        } catch (\Exception $e) {
        }
        // Admin audit trail: who did what to which user. admin_email and
        // target_user_id are snapshots (no FK) so the trail survives when
        // the admin or the user is deleted.
        $this->pdo->exec("ALTER TABLE admin_audit ADD COLUMN IF NOT EXISTS admin_email TEXT");
        $this->pdo->exec("ALTER TABLE admin_audit ADD COLUMN IF NOT EXISTS target_user_id INTEGER");
        $this->pdo->exec("ALTER TABLE admin_audit ADD COLUMN IF NOT EXISTS detail TEXT");
        $this->pdo->exec("ALTER TABLE admin_audit ADD COLUMN IF NOT EXISTS ip TEXT");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_target ON admin_audit (target_user_id, performed_at DESC)");
        // The original FK cascaded, deleting an admin's whole history with
        // them; switch it to SET NULL once.
        $cascade = $this->pdo->query("SELECT 1 FROM pg_constraint WHERE conname = 'admin_audit_admin_id_fkey' AND confdeltype = 'c'")->fetchColumn();
        if ($cascade) {
            $this->pdo->exec("ALTER TABLE admin_audit ALTER COLUMN admin_id DROP NOT NULL");
            $this->pdo->exec("ALTER TABLE admin_audit DROP CONSTRAINT admin_audit_admin_id_fkey");
            $this->pdo->exec("ALTER TABLE admin_audit ADD CONSTRAINT admin_audit_admin_id_fkey FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL");
        }
        // Set by an admin to block sign-in (see index.php / Auth::login).
        $this->pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP DEFAULT NULL");
        // Admin two-factor auth (see Totp). totp_backup_codes is a JSON
        // array of password_hash()es of the single-use backup codes.
        $this->pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS totp_secret TEXT DEFAULT NULL");
        $this->pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS totp_enabled_at TIMESTAMP DEFAULT NULL");
        $this->pdo->exec("ALTER TABLE admins ADD COLUMN IF NOT EXISTS totp_backup_codes TEXT DEFAULT NULL");
        // When an admin closed the refund / manual-cancellation request in
        // the work queue. A request newer than this reopens it.
        $this->pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS refund_handled_at TIMESTAMP DEFAULT NULL");
        $this->pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cancel_handled_at TIMESTAMP DEFAULT NULL");
        // One row per day with real (Dodo) MRR, written by AdminRevenue::snapshot()
        // when an admin opens the panel — MRR history can't be rebuilt after the fact.
        $this->exec("CREATE TABLE IF NOT EXISTS revenue_snapshots (
            day DATE PRIMARY KEY,
            mrr NUMERIC(12,2) NOT NULL DEFAULT 0,
            real_subscribers INTEGER NOT NULL DEFAULT 0,
            test_subscribers INTEGER NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        // Site languages (see Language): status published = listed, draft =
        // hidden from lists but usable by direct link, disabled = rejected.
        $this->exec("CREATE TABLE IF NOT EXISTS languages (
            code TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            native_name TEXT NOT NULL,
            flag TEXT NOT NULL DEFAULT '',
            dir TEXT NOT NULL DEFAULT 'ltr',
            speech_locale TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('published', 'draft', 'disabled')),
            ui_enabled BOOLEAN NOT NULL DEFAULT TRUE,
            learn_enabled BOOLEAN NOT NULL DEFAULT TRUE,
            sort_order INTEGER NOT NULL DEFAULT 100,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        // Seeded once; after that the admin "Languages" page owns the rows.
        $this->pdo->exec("INSERT INTO languages (code, name, native_name, flag, dir, speech_locale, status, sort_order) VALUES
            ('en', 'English',  'English',  'us', 'ltr', 'en-US', 'published', 10),
            ('de', 'German',   'Deutsch',  'de', 'ltr', 'de-DE', 'published', 20),
            ('fr', 'French',   'Français', 'fr', 'ltr', 'fr-FR', 'published', 30),
            ('es', 'Spanish',  'Español',  'es', 'ltr', 'es-ES', 'published', 40),
            ('zh', 'Chinese',  '中文',      'cn', 'ltr', 'zh-CN', 'published', 50),
            ('ja', 'Japanese', '日本語',    'jp', 'ltr', 'ja-JP', 'published', 60),
            ('ar', 'Arabic',   'العربية',  'sa', 'rtl', 'ar-SA', 'published', 70),
            ('ru', 'Russian',  'Русский',  'ru', 'ltr', 'ru-RU', 'published', 90),
            ('el', 'Greek',    'Ελληνικά', 'gr', 'ltr', 'el-GR', 'published', 100),
            ('hi', 'Hindi',    'हिन्दी',     'in', 'ltr', 'hi-IN', 'published', 110),
            ('hy', 'Armenian', 'Հայերեն',  'am', 'ltr', 'hy-AM', 'published', 120)
            ON CONFLICT (code) DO NOTHING");
        // One-time registry changes for databases seeded before them
        // (2026-10-04): the site is for international learners — Turkish is
        // switched off entirely (interface and learning), and Russian, Greek,
        // Hindi and Armenian go live. Runs once, so later admin edits stick.
        // All or nothing, and never fatal: on a database coming straight from
        // an older release, columns added further down (users.ui_lang) may
        // not exist yet — a failure here once took every page down.
        if (!$this->pdo->query("SELECT 1 FROM app_state WHERE key = 'languages_rev_2'")->fetchColumn()) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ui_lang TEXT DEFAULT NULL");
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec("UPDATE languages SET status = 'disabled', ui_enabled = FALSE, learn_enabled = FALSE, updated_at = CURRENT_TIMESTAMP WHERE code = 'tr'");
                $this->pdo->exec("UPDATE languages SET status = 'published', ui_enabled = TRUE, learn_enabled = TRUE, updated_at = CURRENT_TIMESTAMP WHERE code IN ('ru', 'el', 'hi')");
                $this->pdo->exec("INSERT INTO languages (code, name, native_name, flag, dir, speech_locale, status, sort_order) VALUES
                    ('hy', 'Armenian', 'Հայերեն', 'am', 'ltr', 'hy-AM', 'published', 120) ON CONFLICT (code) DO NOTHING");
                $this->pdo->exec("UPDATE users SET ui_lang = NULL WHERE ui_lang = 'tr'");
                $this->pdo->exec("INSERT INTO app_state (key, value) VALUES ('languages_rev_2', '1') ON CONFLICT (key) DO NOTHING");
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('languages_rev_2 failed (will retry next request): ' . $e->getMessage());
            }
        }
        // UI strings on top of lang/<code>.json: AI translations and admin
        // edits. Kept in the DB because Railway's filesystem is reset on deploy.
        $this->exec("CREATE TABLE IF NOT EXISTS ui_translations (
            lang TEXT NOT NULL,
            key TEXT NOT NULL,
            value TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT 'manual',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (lang, key)
        )");
        // Mobile app sign-ins (see Api\Tokens): one row per device, only the
        // SHA-256 of the bearer token is stored. Sliding 90-day expiry like
        // the web session.
        $this->exec("CREATE TABLE IF NOT EXISTS api_tokens (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL UNIQUE,
            device TEXT NOT NULL DEFAULT '',
            platform TEXT NOT NULL DEFAULT '',
            app_version TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL
        )");
        $this->exec("CREATE INDEX IF NOT EXISTS idx_api_tokens_user ON api_tokens (user_id)");
        // A user flagging an AI tutor reply as offensive / wrong. Google Play
        // requires this for apps with AI-generated content.
        $this->exec("CREATE TABLE IF NOT EXISTS ai_reports (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            message_id INTEGER REFERENCES messages(id) ON DELETE SET NULL,
            reason TEXT NOT NULL,
            note TEXT NOT NULL DEFAULT '',
            message_snapshot TEXT NOT NULL DEFAULT '',
            source TEXT NOT NULL DEFAULT 'app',
            status TEXT NOT NULL DEFAULT 'open',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        // Interface language the user picked (NULL = follow native_lang).
        $this->pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ui_lang TEXT DEFAULT NULL");
        try {
            // 'admin' (full access) or 'viewer' (read-only — can see every
            // admin page but can't save settings or manage other admins).
            // Defaults existing rows to 'admin' so today's admins keep the
            // access they already had.
            $this->pdo->exec("ALTER TABLE admins ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
        } catch (\Exception $e) {
        }
        // Turkish leaves the data too (2026-10-05), not just the lists:
        // nobody learns Turkish any more, Turkish natives learning another
        // language get English explanations, and Turkish natives learning
        // English (English can't be both) pick their native language again
        // through onboarding. Turkish-language study data can't be opened
        // any more, so it goes. Last step on purpose — every table above
        // exists by now — and, like languages_rev_2, all or nothing and
        // never fatal.
        if (!$this->pdo->query("SELECT 1 FROM app_state WHERE key = 'languages_rev_3'")->fetchColumn()) {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec("UPDATE users SET target_lang = CASE WHEN native_lang = 'en' THEN 'es' ELSE 'en' END WHERE target_lang = 'tr'");
                $this->pdo->exec("UPDATE users SET native_lang = 'en' WHERE native_lang = 'tr' AND target_lang <> 'en'");
                $this->pdo->exec("UPDATE users SET native_lang = NULL, onboarding_completed = 0 WHERE native_lang = 'tr'");
                $this->pdo->exec("UPDATE users SET ui_lang = NULL WHERE ui_lang = 'tr'");
                $this->pdo->exec("DELETE FROM vocabulary_words WHERE language = 'tr'");
                $this->pdo->exec("DELETE FROM flashcard_decks WHERE language = 'tr'");
                $this->pdo->exec("DELETE FROM user_mistakes WHERE language = 'tr'");
                $this->pdo->exec("DELETE FROM alphabet_progress WHERE lang = 'tr'");
                $this->pdo->exec("DELETE FROM ui_translations WHERE lang = 'tr'");
                $this->pdo->exec("DELETE FROM languages WHERE code = 'tr'");
                $this->pdo->exec("INSERT INTO app_state (key, value) VALUES ('languages_rev_3', '1') ON CONFLICT (key) DO NOTHING");
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('languages_rev_3 failed (will retry next request): ' . $e->getMessage());
            }
        }
        // HSK cards users added before 2026-10-06 carry the old English gloss,
        // often a dictionary's first sense (对不起 "unworthy"), whatever the
        // user's language. Cards still showing exactly that gloss get today's
        // meaning in the user's language; cards the user edited are left alone. One set-based
        // UPDATE (via a temp table) rather than a query per word, since this
        // runs on a request. All or nothing, never fatal.
        if (!$this->pdo->query("SELECT 1 FROM app_state WHERE key = 'vocab_hsk_rev_1'")->fetchColumn()) {
            $this->pdo->beginTransaction();
            try {
                $oldEn = json_decode((string)@file_get_contents(__DIR__ . '/../data/vocab/hsk_old_en.json'), true) ?: [];
                $rows = [];
                foreach (json_decode((string)@file_get_contents(__DIR__ . '/../data/vocab/zh.json'), true) ?: [] as $c) {
                    if (($c['category'] ?? '') === 'HSK') {
                        // hsk_old_en.json lists only the glosses that changed;
                        // for the rest the old gloss is today's English one.
                        $was = $oldEn[$c['word']] ?? $c['translations']['en'];
                        foreach ($c['translations'] as $lang => $meaning) {
                            $rows[] = [$c['word'], $was, $lang, $meaning];
                        }
                    }
                }
                if ($rows) {
                    $this->pdo->exec('CREATE TEMP TABLE hsk_fix (word TEXT, old_en TEXT, lang TEXT, meaning TEXT) ON COMMIT DROP');
                    foreach (array_chunk($rows, 500) as $chunk) {
                        $this->pdo->prepare('INSERT INTO hsk_fix VALUES ' . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)')))
                            ->execute(array_merge(...$chunk));
                    }
                    $this->pdo->exec(
                        "UPDATE vocabulary_words v SET translation = f.meaning, updated_at = CURRENT_TIMESTAMP
                         FROM users u, hsk_fix f
                         WHERE u.id = v.user_id AND v.language = 'zh' AND v.source = 'pack'
                           AND f.word = v.word AND v.translation = f.old_en AND f.meaning <> f.old_en
                           AND f.lang = CASE WHEN u.native_lang IN ('en','de','fr','es','ar','ja','ru','el','hi','hy') THEN u.native_lang ELSE 'en' END"
                    );
                }
                $this->pdo->exec("INSERT INTO app_state (key, value) VALUES ('vocab_hsk_rev_1', '1') ON CONFLICT (key) DO NOTHING");
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('vocab_hsk_rev_1 failed (will retry next request): ' . $e->getMessage());
            }
        }

        // Email verification became required for password sign-in on
        // 2026-10-07. Accounts created before that never had to confirm
        // their address and must not be locked out, so they count as
        // confirmed. Runs once; never fatal.
        if (!$this->pdo->query("SELECT 1 FROM app_state WHERE key = 'email_verify_rev_1'")->fetchColumn()) {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE email_verified_at IS NULL');
                $this->pdo->exec("INSERT INTO app_state (key, value) VALUES ('email_verify_rev_1', '1') ON CONFLICT (key) DO NOTHING");
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('email_verify_rev_1 failed (will retry next request): ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): \PDO {
        return $this->pdo;
    }

    public function lastInsertId(?string $table = null): int {
        if ($table) {
            return (int)$this->pdo->lastInsertId($table . '_id_seq');
        }
        return (int)$this->pdo->lastInsertId();
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->rowCount();
    }

    public function now(): string {
        return 'CURRENT_TIMESTAMP';
    }

    public function dateNow(): string {
        return 'CURRENT_DATE';
    }

    public function insertIgnore(string $table, array $columns, array $values): void {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $cols = implode(', ', $columns);
        $sql = "INSERT INTO $table ($cols) VALUES ($placeholders) ON CONFLICT DO NOTHING";
        $this->execute($sql, $values);
    }
}
