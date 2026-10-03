<?php
namespace App\Src;

/**
 * Site languages and UI strings.
 *
 * Which languages exist lives in the `languages` table, not in code:
 *   published — listed in every language picker and usable;
 *   draft     — not listed anywhere, but usable when reached directly
 *               (?ui_lang=ru, ?page=update_lang&lang=ru) — for languages
 *               we are still preparing (documentation, data, strings);
 *   disabled  — rejected.
 * ui_enabled / learn_enabled say whether a language can be the interface
 * language and/or a language to learn.
 *
 * UI strings: lang/<code>.json in the repo is the base; rows in
 * `ui_translations` (AI-translated or edited in the admin) override it,
 * since files written at runtime would not survive a Railway deploy.
 * A key missing in a language falls back to English, then to the key.
 */
class Language
{
    public const DEFAULT = 'en';
    public const COOKIE = 'jl_lang';

    /** Used when the registry can't be read (no DB yet, table missing). */
    private const FALLBACK_REGISTRY = [
        ['code' => 'en', 'name' => 'English',  'native_name' => 'English',  'flag' => 'us', 'dir' => 'ltr', 'speech_locale' => 'en-US', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'de', 'name' => 'German',   'native_name' => 'Deutsch',  'flag' => 'de', 'dir' => 'ltr', 'speech_locale' => 'de-DE', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'fr', 'name' => 'French',   'native_name' => 'Français', 'flag' => 'fr', 'dir' => 'ltr', 'speech_locale' => 'fr-FR', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'es', 'name' => 'Spanish',  'native_name' => 'Español',  'flag' => 'es', 'dir' => 'ltr', 'speech_locale' => 'es-ES', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'zh', 'name' => 'Chinese',  'native_name' => '中文',      'flag' => 'cn', 'dir' => 'ltr', 'speech_locale' => 'zh-CN', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語',    'flag' => 'jp', 'dir' => 'ltr', 'speech_locale' => 'ja-JP', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'ar', 'name' => 'Arabic',   'native_name' => 'العربية',  'flag' => 'sa', 'dir' => 'rtl', 'speech_locale' => 'ar-SA', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
        ['code' => 'tr', 'name' => 'Turkish',  'native_name' => 'Türkçe',   'flag' => 'tr', 'dir' => 'ltr', 'speech_locale' => 'tr-TR', 'status' => 'published', 'ui_enabled' => true, 'learn_enabled' => true],
    ];

    private static ?Database $db = null;
    private static ?array $registry = null;
    private static array $translations = [];
    private static array $english = [];
    private static bool $loaded = false;
    private static string $currentLang = self::DEFAULT;

    public static function boot(Database $db): void
    {
        self::$db = $db;
        self::$registry = null;
    }

    // ── Registry ────────────────────────────────────────────────

    /** All languages, keyed by code, in display order. */
    public static function registry(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }
        $rows = [];
        if (self::$db) {
            try {
                $rows = self::$db->fetchAll('SELECT code, name, native_name, flag, dir, speech_locale, status, ui_enabled, learn_enabled FROM languages ORDER BY sort_order, code');
            } catch (\Throwable $e) {
                error_log('Language::registry: ' . $e->getMessage());
            }
        }
        if (!$rows) {
            $rows = self::FALLBACK_REGISTRY;
        }
        self::$registry = [];
        foreach ($rows as $r) {
            $r['ui_enabled'] = filter_var($r['ui_enabled'], FILTER_VALIDATE_BOOLEAN);
            $r['learn_enabled'] = filter_var($r['learn_enabled'], FILTER_VALIDATE_BOOLEAN);
            self::$registry[$r['code']] = $r;
        }
        return self::$registry;
    }

    public static function info(string $code): ?array
    {
        return self::registry()[strtolower($code)] ?? null;
    }

    /**
     * Whether $code can be used for $use ('ui' or 'learn'). Drafts count:
     * they are only hidden from lists, not blocked.
     */
    public static function isUsable(string $code, string $use = 'learn'): bool
    {
        $l = self::info($code);
        if (!$l || $l['status'] === 'disabled') {
            return false;
        }
        return $use === 'ui' ? $l['ui_enabled'] : $l['learn_enabled'];
    }

    /** Codes to show in a picker: published only, plus $keep (the user's current choice, even if draft). */
    public static function listed(string $use = 'learn', ?string $keep = null): array
    {
        $out = [];
        foreach (self::registry() as $code => $l) {
            $usable = $use === 'ui' ? $l['ui_enabled'] : $l['learn_enabled'];
            if (!$usable || $l['status'] === 'disabled') {
                continue;
            }
            if ($l['status'] === 'published' || $code === $keep) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /** Every code accepted as a language to learn / native language (drafts included). */
    public static function supportedLangs(): array
    {
        return array_values(array_filter(array_keys(self::registry()), fn($c) => self::isUsable($c, 'learn')));
    }

    /** English name ("German"), used in AI prompts and admin. */
    public static function langName(string $code): string
    {
        return self::info($code)['name'] ?? strtoupper($code);
    }

    /** Name in the language itself ("Deutsch"), used in the interface-language picker. */
    public static function nativeName(string $code): string
    {
        return self::info($code)['native_name'] ?? strtoupper($code);
    }

    /** Language name in the current UI language, falling back to its English name. */
    public static function displayName(string $code): string
    {
        return self::get('languages.' . strtolower($code), self::langName($code));
    }

    public static function flagCountry(string $code): string
    {
        return self::info($code)['flag'] ?? '';
    }

    public static function dir(?string $code = null): string
    {
        return self::info($code ?? self::$currentLang)['dir'] ?? 'ltr';
    }

    public static function speechLocale(string $code): string
    {
        return self::info($code)['speech_locale'] ?? $code;
    }

    // ── Strings ─────────────────────────────────────────────────

    public static function load(string $lang): void
    {
        $lang = self::isUsable($lang, 'ui') ? strtolower($lang) : self::DEFAULT;
        self::$currentLang = $lang;
        self::$english = self::strings(self::DEFAULT);
        self::$translations = $lang === self::DEFAULT ? self::$english : self::strings($lang);
        self::$loaded = true;
    }

    /** The repo file for $lang overlaid with its DB rows. */
    public static function strings(string $lang): array
    {
        $out = self::fileStrings($lang);
        foreach (self::dbStrings($lang) as $key => $value) {
            $out[$key] = $value;
        }
        return $out;
    }

    public static function fileStrings(string $lang): array
    {
        if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]+)?$/', $lang)) {
            return [];
        }
        $file = __DIR__ . '/../lang/' . $lang . '.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public static function dbStrings(string $lang): array
    {
        if (!self::$db) {
            return [];
        }
        try {
            $out = [];
            foreach (self::$db->fetchAll('SELECT key, value FROM ui_translations WHERE lang = ?', [$lang]) as $r) {
                $out[$r['key']] = $r['value'];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (!self::$loaded) {
            self::load(self::DEFAULT);
        }
        return self::$translations[$key] ?? self::$english[$key] ?? ($default !== '' ? $default : $key);
    }

    /** Strings whose key starts with one of $prefixes, for handing to JavaScript. */
    public static function forJs(array $prefixes): array
    {
        if (!self::$loaded) {
            self::load(self::DEFAULT);
        }
        $out = [];
        foreach (self::$english + self::$translations as $key => $_) {
            foreach ($prefixes as $p) {
                if (str_starts_with($key, $p)) {
                    $out[$key] = self::get($key);
                    break;
                }
            }
        }
        return $out;
    }

    public static function currentLang(): string
    {
        return self::$currentLang;
    }
}
