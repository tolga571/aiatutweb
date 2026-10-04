<?php
/**
 * Returns an <img> tag for the given language code using flagcdn.com SVGs.
 * Usage: <?= flagImg('en') ?>  or  <?= flagImg('de', 'w-6 h-4') ?>
 */
function flagImg(string $lang, string $class = 'w-5 h-3.5'): string {
    $map = [
        'en' => 'us', 'de' => 'de', 'fr' => 'fr',
        'es' => 'es', 'zh' => 'cn', 'ja' => 'jp', 'ar' => 'sa', 'tr' => 'tr',
    ];
    $country = $map[strtolower($lang)] ?? null;
    if (!$country) return '<span class="text-sm">🌐</span>';
    $alt = strtoupper($lang) . ' flag';
    return '<img src="https://flagcdn.com/' . $country . '.svg"'
         . ' alt="' . htmlspecialchars($alt) . '"'
         . ' class="' . htmlspecialchars($class) . ' inline-block rounded-[2px] object-cover align-middle"'
         . ' loading="lazy" decoding="async"'
         . ' onerror="this.onerror=null;this.style.display=\'none\'"'
         . ' />';
}
