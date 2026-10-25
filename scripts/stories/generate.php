<?php
/**
 * Builds the starter stories: data/stories/sources.json (English) →
 * data/stories/<slug>.json (all 11 languages, word by word).
 *
 *   php scripts/stories/generate.php                 # every story without a file yet
 *   php scripts/stories/generate.php pied-piper-of-hamelin --force
 *
 * Uses the real Gemini API (GEMINI_API_KEY); costs roughly $0.3-0.6 per
 * story. Database::migrate() loads the files into `stories` / `story_texts`.
 */
require __DIR__ . '/../../autoload.php';

use App\Src\GeminiClient;
use App\Src\Language;
use App\Src\StoryGenerator;

$args = array_slice($argv, 1);
// --tidy: rewrite the finished files (compact JSON, meanings cleaned) and stop.
if (in_array('--tidy', $args, true)) {
    foreach (glob(__DIR__ . '/../../data/stories/*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (empty($d['texts'])) {
            continue;
        }
        foreach ($d['texts'] as $l => $t) {
            $d['texts'][$l] = StoryGenerator::cleanText($t);
        }
        file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE) . "\n");
        echo 'tidied ' . basename($f) . "\n";
    }
    exit;
}
$force = in_array('--force', $args, true);
$only = array_values(array_filter($args, fn($a) => $a !== '--force'));

$key = getenv('GEMINI_API_KEY') ?: '';
if ($key === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not set\n");
    exit(1);
}
$dir = __DIR__ . '/../../data/stories';
$sources = json_decode(file_get_contents($dir . '/sources.json'), true)['stories'];
$langs = array_column(Language::registry(), 'code');
$total = 0.0;

foreach ($sources as $src) {
    $slug = $src['slug'];
    if ($only && !in_array($slug, $only, true)) {
        continue;
    }
    $file = "{$dir}/{$slug}.json";
    if (!$force && file_exists($file)) {
        echo "skip {$slug} (exists)\n";
        continue;
    }
    // Each finished language is kept in a cache file, so a failure (or a
    // model running out of daily quota) only costs that language again.
    $cacheDir = "{$dir}/.cache";
    @mkdir($cacheDir, 0775, true);
    $gen = new StoryGenerator(new GeminiClient($key, getenv('GEMINI_API_KEY_BACKUP') ?: ''), null, $langs);
    $t0 = microtime(true);
    $texts = [];
    foreach ($langs as $lang) {
        $cache = "{$cacheDir}/{$slug}.{$lang}.json";
        if (!$force && file_exists($cache)) {
            $texts[$lang] = StoryGenerator::cleanText(json_decode(file_get_contents($cache), true));
            continue;
        }
        echo "  {$slug} {$lang}\n";
        try {
            $texts[$lang] = $gen->compose($src, $lang);
            file_put_contents($cache, json_encode($texts[$lang], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            fwrite(STDERR, "  FAILED {$slug} {$lang}: " . substr($e->getMessage(), 0, 600) . "\n");
        }
    }
    $total += $gen->cost();
    if (count($texts) !== count($langs)) {
        fwrite(STDERR, "incomplete {$slug}: " . count($texts) . '/' . count($langs) . " languages\n");
        continue;
    }
    $story = [
        'slug' => $slug,
        'category' => $src['category'],
        'length' => StoryGenerator::lengthFor($src['sentences']),
        'source' => $src['source'],
        'generated_at' => date('c'),
        'texts' => $texts,
    ];
    file_put_contents($file, json_encode($story, JSON_UNESCAPED_UNICODE) . "\n");
    printf("done %s in %ds, $%.3f\n", $slug, microtime(true) - $t0, $gen->cost());
}
printf("total $%.3f\n", $total);
