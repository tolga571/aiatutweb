<?php
namespace App\Src;

class Database {
    private \PDO $pdo;
    private bool $isPostgres;

    public function __construct(string $dbPath, string $dbUrl = '') {
        if (!empty($dbUrl) && extension_loaded('pdo_pgsql')) {
            $parts = @parse_url($dbUrl);
            $host = ($parts && isset($parts['host']) && !str_contains($parts['host'], '{{')) ? $parts['host'] : (getenv('PGHOST') ?: '');
            $port = ($parts && isset($parts['host']) && !str_contains($parts['host'], '{{')) ? ($parts['port'] ?? '5432') : (getenv('PGPORT') ?: '5432');
            $dbname = ($parts && isset($parts['host']) && !str_contains($parts['host'], '{{')) ? ltrim($parts['path'] ?? '/postgres', '/') : (getenv('PGDATABASE') ?: '');
            $user = ($parts && isset($parts['host']) && !str_contains($parts['host'], '{{')) ? ($parts['user'] ?? 'postgres') : (getenv('PGUSER') ?: 'postgres');
            $pass = ($parts && isset($parts['host']) && !str_contains($parts['host'], '{{')) ? ($parts['pass'] ?? '') : (getenv('PGPASSWORD') ?: '');
            if (!empty($host) && !empty($dbname)) {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
                    $host, $port, $dbname, $user, $pass
                );
                try {
                    $this->isPostgres = true;
                    $this->pdo = new \PDO($dsn);
                    error_log('Database: PostgreSQL connected (' . $host . ')');
                } catch (\PDOException $e) {
                    error_log('Database: PostgreSQL failed, using SQLite: ' . $e->getMessage());
                }
            }
        }
        if (!isset($this->pdo)) {
            $this->isPostgres = false;
            error_log('Database: Using SQLite');
            $dsn = "sqlite:" . $dbPath;
            $this->pdo = new \PDO($dsn);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        }
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->initialize();
    }

    private function exec(string $sql): void {
        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            if ($this->isPostgres && str_contains($e->getMessage(), 'already exists')) {
                return;
            }
            throw $e;
        }
    }

    private function initialize(): void {
        if ($this->isPostgres) {
            $this->initializePg();
        } else {
            $this->initializeSqlite();
        }
        $this->migrate();
    }

    private function migrate(): void {
        try {
            $colType = $this->isPostgres ? 'TIMESTAMP' : 'DATETIME';
            $this->pdo->exec("ALTER TABLE users ADD COLUMN payment_pending_at {$colType} DEFAULT NULL");
        } catch (\Exception $e) {
            // Column already exists, ignore
        }
    }

    private function initializeSqlite(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
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
            payment_pending_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            topic_id TEXT,
            topic_label TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            translation TEXT,
            correction TEXT,
            metadata TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE CASCADE
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS learning_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT,
            content TEXT NOT NULL,
            source TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            content TEXT NOT NULL,
            category TEXT DEFAULT 'blog',
            language TEXT DEFAULT 'en',
            published INTEGER DEFAULT 1,
            is_premium INTEGER DEFAULT 0,
            author_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS token_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            used_today INTEGER DEFAULT 0,
            last_reset DATE,
            FOREIGN KEY(user_id) REFERENCES users(id)
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS vocabulary_words (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            word TEXT NOT NULL,
            translation TEXT,
            pronunciation TEXT DEFAULT '',
            example TEXT DEFAULT '',
            example_translation TEXT DEFAULT '',
            category TEXT DEFAULT 'chat',
            level TEXT DEFAULT 'A1',
            language TEXT NOT NULL,
            source TEXT DEFAULT 'chat',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user_flashcards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            vocab_id INTEGER NOT NULL,
            ease_factor REAL DEFAULT 2.5,
            interval INTEGER DEFAULT 0,
            repetitions INTEGER DEFAULT 0,
            next_review DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_reviewed DATETIME,
            correct_count INTEGER DEFAULT 0,
            incorrect_count INTEGER DEFAULT 0,
            status TEXT DEFAULT 'new',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(vocab_id) REFERENCES vocabulary_words(id) ON DELETE CASCADE,
            UNIQUE(user_id, vocab_id)
        );");
    }

    private function initializePg(): void {
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
            used_today INTEGER DEFAULT 0,
            last_reset DATE
        )");
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
    }

    public function getPdo(): \PDO {
        return $this->pdo;
    }

    public function lastInsertId(?string $table = null): int {
        if ($this->isPostgres) {
            if ($table) {
                return (int)$this->pdo->lastInsertId($table . '_id_seq');
            }
            return (int)$this->pdo->lastInsertId();
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
        return $this->isPostgres ? 'CURRENT_TIMESTAMP' : 'datetime("now")';
    }

    public function dateNow(): string {
        return $this->isPostgres ? 'CURRENT_DATE' : 'DATE("now")';
    }

    public function insertIgnore(string $table, array $columns, array $values): void {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $cols = implode(', ', $columns);
        if ($this->isPostgres) {
            $sql = "INSERT INTO $table ($cols) VALUES ($placeholders) ON CONFLICT DO NOTHING";
        } else {
            $sql = "INSERT OR IGNORE INTO $table ($cols) VALUES ($placeholders)";
        }
        $this->execute($sql, $values);
    }
}