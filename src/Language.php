<?php
namespace App\Src;

class Language
{
    private static ?array $translations = null;
    private static string $currentLang = 'en';
    private static array $supportedLangs = ['en', 'de', 'fr', 'es', 'zh', 'ja', 'ar', 'tr'];

    public static function load(string $lang): void
    {
        $lang = in_array($lang, self::$supportedLangs, true) ? $lang : 'en';
        self::$currentLang = $lang;

        $file = __DIR__ . '/../lang/' . $lang . '.php';
        if (file_exists($file)) {
            self::$translations = require $file;
        } else {
            self::$translations = [];
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (self::$translations === null) {
            self::load('en');
        }
        return self::$translations[$key] ?? $default;
    }

    public static function currentLang(): string
    {
        return self::$currentLang;
    }

    public static function supportedLangs(): array
    {
        return self::$supportedLangs;
    }

    public static function langName(string $code): string
    {
        $names = [
            'en' => 'English', 'de' => 'German', 'fr' => 'French',
            'es' => 'Spanish', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ar' => 'Arabic', 'tr' => 'Turkish',
        ];
        return $names[$code] ?? strtoupper($code);
    }

    public static function flagCountry(string $code): string
    {
        $map = ['en' => 'us', 'de' => 'de', 'fr' => 'fr', 'es' => 'es', 'zh' => 'cn', 'ja' => 'jp', 'ar' => 'sa', 'tr' => 'tr'];
        return $map[$code] ?? 'us';
    }
}
