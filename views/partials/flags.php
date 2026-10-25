<?php
/**
 * Returns an <img> tag for the given language code. The flag SVGs are
 * self-hosted in public/img/flags/ (copied from flagcdn.com): loaded from the
 * CDN they sometimes failed and the onerror handler hid them.
 * Usage: <?= flagImg('en') ?>  or  <?= flagImg('de', 'w-6 h-4') ?>
 */
function flagImg(string $lang, string $class = 'w-5 h-3.5'): string {
    $country = \App\Src\Language::flagCountry($lang) ?: null;
    if (!$country) return '<span class="text-sm">🌐</span>';
    $alt = strtoupper($lang) . ' flag';
    return '<img src="/img/flags/' . $country . '.svg"'
         . ' alt="' . htmlspecialchars($alt) . '"'
         . ' class="' . htmlspecialchars($class) . ' inline-block rounded-[2px] object-cover align-middle"'
         . ' decoding="async"'
         . ' onerror="this.onerror=null;this.style.display=\'none\'"'
         . ' />';
}
