<?php

use App\Src\Language;

/**
 * Global translation helper function.
 * Translates a key using the currently loaded language.
 */
function __(string $key, string $default = ''): string
{
    return Language::get($key, $default !== '' ? $default : $key);
}
