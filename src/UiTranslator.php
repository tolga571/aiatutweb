<?php
namespace App\Src;

/**
 * Admin side of the UI strings (see Language): per-language coverage, AI
 * translation of missing keys, manual edits and JSON export.
 *
 * English (lang/en.json) is the master list of keys. Translations written
 * here go to `ui_translations`; "Export JSON" gives the merged file to
 * commit as lang/<code>.json, after which the DB rows can be cleared.
 */
class UiTranslator
{
    /** Keys per Gemini request: big enough to be cheap, small enough to finish well inside the 30 s HTTP timeout. */
    public const BATCH = 60;

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function masterKeys(): array
    {
        return Language::fileStrings(Language::DEFAULT);
    }

    /** [code => [total, translated, missing, db, ai]] for every language in the registry. */
    public function coverage(): array
    {
        $master = $this->masterKeys();
        $total = count($master);
        $dbCounts = [];
        foreach ($this->db->fetchAll("SELECT lang, COUNT(*) AS n, COUNT(*) FILTER (WHERE source = 'ai') AS ai FROM ui_translations GROUP BY lang") as $r) {
            $dbCounts[$r['lang']] = $r;
        }
        $out = [];
        foreach (Language::registry() as $code => $_) {
            $have = $code === Language::DEFAULT ? $master : Language::strings($code);
            $translated = count(array_intersect_key($have, $master));
            $out[$code] = [
                'total' => $total,
                'translated' => $translated,
                'missing' => $total - $translated,
                'db' => (int)($dbCounts[$code]['n'] ?? 0),
                'ai' => (int)($dbCounts[$code]['ai'] ?? 0),
            ];
        }
        return $out;
    }

    /** Master keys that $lang has no string for yet. */
    public function missingKeys(string $lang): array
    {
        return array_diff_key($this->masterKeys(), Language::strings($lang));
    }

    /**
     * Translates up to one batch of missing keys with Gemini and stores them
     * (source 'ai'). Returns ['translated' => n, 'rejected' => [keys], 'remaining' => n].
     * A string whose placeholders / HTML tags don't survive is rejected and
     * stays missing (English is shown), never saved broken.
     */
    public function translateBatch(string $lang, GeminiClient $gemini, ?int $adminUserId = null): array
    {
        $info = Language::info($lang);
        if (!$info || $lang === Language::DEFAULT) {
            throw new \InvalidArgumentException('unknown language');
        }
        $missing = $this->missingKeys($lang);
        $batch = array_slice($missing, 0, self::BATCH, true);
        if (!$batch) {
            return ['translated' => 0, 'rejected' => [], 'remaining' => 0];
        }

        $name = $info['name'];
        $system = "You translate the user-interface strings of Jumplearner, a website where people learn languages by chatting with an AI tutor named Kai.\n"
            . "Translate every value of the JSON object from English into {$name} (language code {$lang}).\n"
            . "Rules:\n"
            . "- Return ONLY a JSON object with exactly the same keys.\n"
            . "- Keep unchanged: placeholders like %s, %d, %1\$s, {count}, {name}; HTML tags and their attributes; **markdown**; URLs; the names Jumplearner and Kai; CEFR levels (A1, B2...).\n"
            . "- Short, natural interface wording a native speaker would expect on a modern app; keep button labels short.\n"
            . "- Language names (keys starting with languages.) are the name of that language written in {$name}.";
        $gemini->useModels(['gemini-3.1-flash-lite', 'gemini-2.5-flash']);
        try {
            $raw = $gemini->chatWithHistory(json_encode($batch, JSON_UNESCAPED_UNICODE), [], $system);
        } catch (\Throwable $e) {
            AiUsage::record($this->db, $adminUserId, null, false, 'i18n');
            throw $e;
        }
        AiUsage::record($this->db, $adminUserId, $gemini->getLastUsage(), true, 'i18n');

        $result = json_decode($this->stripFences($raw), true);
        if (!is_array($result)) {
            throw new \RuntimeException('AI returned invalid JSON');
        }

        $saved = 0;
        $rejected = [];
        foreach ($batch as $key => $english) {
            $value = $result[$key] ?? null;
            if (!is_string($value) || trim($value) === '' || !$this->sameMarkup($english, $value)) {
                $rejected[] = $key;
                continue;
            }
            $this->upsert($lang, $key, $value, 'ai', false);
            $saved++;
        }
        $remaining = count($missing) - $saved;
        return ['translated' => $saved, 'rejected' => $rejected, 'remaining' => $remaining];
    }

    /** Manual edit from the admin; always wins over an AI string. */
    public function setString(string $lang, string $key, string $value): void
    {
        if (!array_key_exists($key, $this->masterKeys()) || !Language::info($lang)) {
            throw new \InvalidArgumentException('unknown key or language');
        }
        $this->upsert($lang, $key, $value, 'manual', true);
    }

    /** Drops the DB override so the repo file (or English) shows again. */
    public function resetString(string $lang, string $key): void
    {
        $this->db->execute('DELETE FROM ui_translations WHERE lang = ? AND key = ?', [$lang, $key]);
    }

    /** Deletes this language's AI rows (manual edits stay). */
    public function clearAi(string $lang): int
    {
        return $this->db->execute("DELETE FROM ui_translations WHERE lang = ? AND source = 'ai'", [$lang]);
    }

    /** File + DB strings restricted to master keys, in master order — the content for lang/<code>.json. */
    public function export(string $lang): array
    {
        $all = Language::strings($lang);
        $out = [];
        foreach ($this->masterKeys() as $key => $_) {
            if (isset($all[$key])) {
                $out[$key] = $all[$key];
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Rows for the string editor: [key, english, value, source] where source
     * is 'file', 'ai', 'manual' or '' (missing). $filter: all|missing|ai|manual.
     */
    public function rows(string $lang, string $filter = 'all', string $search = ''): array
    {
        $file = Language::fileStrings($lang);
        $db = [];
        foreach ($this->db->fetchAll('SELECT key, value, source FROM ui_translations WHERE lang = ?', [$lang]) as $r) {
            $db[$r['key']] = $r;
        }
        $rows = [];
        foreach ($this->masterKeys() as $key => $english) {
            if (isset($db[$key])) {
                [$value, $source] = [$db[$key]['value'], $db[$key]['source']];
            } elseif (isset($file[$key])) {
                [$value, $source] = [$file[$key], 'file'];
            } else {
                [$value, $source] = ['', ''];
            }
            if ($filter === 'missing' && $source !== '') continue;
            if (($filter === 'ai' || $filter === 'manual') && $source !== $filter) continue;
            if ($search !== '' && mb_stripos($key . "\n" . $english . "\n" . $value, $search) === false) continue;
            $rows[] = ['key' => $key, 'english' => $english, 'value' => $value, 'source' => $source];
        }
        return $rows;
    }

    // ── Languages ──────────────────────────────────────────────

    public const STATUSES = ['published', 'draft', 'disabled'];

    public function updateLanguage(string $code, string $status, bool $ui, bool $learn): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('bad status');
        }
        if ($code === Language::DEFAULT && $status !== 'published') {
            throw new \InvalidArgumentException('English is the fallback language and must stay published');
        }
        $this->db->execute(
            'UPDATE languages SET status = ?, ui_enabled = ?, learn_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?',
            [$status, $ui ? 'true' : 'false', $learn ? 'true' : 'false', $code]
        );
    }

    /** New languages always start as draft. */
    public function addLanguage(string $code, string $name, string $nativeName, string $flag, string $dir, string $speech): void
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z]{2,3}$/', $code)) {
            throw new \InvalidArgumentException('code must be 2-3 lowercase letters (ISO 639)');
        }
        if (trim($name) === '' || trim($nativeName) === '') {
            throw new \InvalidArgumentException('name required');
        }
        $flag = preg_match('/^[a-z]{2}(-[a-z]+)?$/', strtolower($flag)) ? strtolower($flag) : '';
        $dir = $dir === 'rtl' ? 'rtl' : 'ltr';
        $this->db->execute(
            "INSERT INTO languages (code, name, native_name, flag, dir, speech_locale, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, 'draft', (SELECT COALESCE(MAX(sort_order), 0) + 10 FROM languages))
             ON CONFLICT (code) DO NOTHING",
            [$code, trim($name), trim($nativeName), $flag, $dir, trim($speech) ?: $code]
        );
    }

    // ── Helpers ────────────────────────────────────────────────

    private function upsert(string $lang, string $key, string $value, string $source, bool $overwriteManual): void
    {
        $guard = $overwriteManual ? '' : " WHERE ui_translations.source <> 'manual'";
        $this->db->execute(
            "INSERT INTO ui_translations (lang, key, value, source) VALUES (?, ?, ?, ?)
             ON CONFLICT (lang, key) DO UPDATE SET value = EXCLUDED.value, source = EXCLUDED.source, updated_at = CURRENT_TIMESTAMP{$guard}",
            [$lang, $key, $value, $source]
        );
    }

    /** Same printf/brace placeholders and the same HTML tags (in any order). */
    public function sameMarkup(string $a, string $b): bool
    {
        $sig = function (string $s): array {
            preg_match_all('/%(?:\d+\$)?[sdf]|\{[a-z_]+\}|<\/?[a-z][a-z0-9]*/i', $s, $m);
            $m = array_map('strtolower', $m[0]);
            sort($m);
            return $m;
        };
        return $sig($a) === $sig($b);
    }

    private function stripFences(string $raw): string
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $raw);
        }
        return $raw;
    }
}
