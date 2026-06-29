<?php
namespace App\Src;

class Database {
    private \PDO $pdo;

    public function __construct(string $dbPath) {
        $dsn = "sqlite:" . $dbPath;
        $this->pdo = new \PDO($dsn);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->initialize();
    }

    private function initialize(): void {
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
                // Add profile_image column if not present (SQLite doesn't support IF NOT EXISTS)
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN profile_image TEXT DEFAULT NULL;");
        } catch (\PDOException $e) {
            // Column probably already exists, ignore the error
        }

        try {
            $this->pdo->exec("ALTER TABLE messages ADD COLUMN metadata TEXT DEFAULT NULL;");
        } catch (\PDOException $e) {
            // Column probably already exists, ignore the error
        }

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

        // Migration for existing vocabulary_words table
        $columnsToAdd = [
            'pronunciation' => "TEXT DEFAULT ''",
            'example' => "TEXT DEFAULT ''",
            'example_translation' => "TEXT DEFAULT ''",
            'category' => "TEXT DEFAULT 'chat'",
            'level' => "TEXT DEFAULT 'A1'",
            'source' => "TEXT DEFAULT 'chat'"
        ];

        foreach ($columnsToAdd as $col => $def) {
            try {
                $this->pdo->exec("ALTER TABLE vocabulary_words ADD COLUMN $col $def");
            } catch (\PDOException $e) {
                // Column likely exists, ignore
            }
        }

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

    public function getPdo(): \PDO {
        return $this->pdo;
    }

    public function lastInsertId(): int {
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
}
?>
