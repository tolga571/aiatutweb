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
