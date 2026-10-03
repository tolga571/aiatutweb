<?php
// Lexicon sources + search. Set by AdminController::lexicon():
// $available, $sources, $lexLangs, $filters, $pageNum, $results, $csrf.
$e = fn($v) => htmlspecialchars((string)$v);
$n = fn($v) => number_format((float)$v, 0, ',', '.');
$title = t('admin.nav_lexicon');
$pageHeader = t('admin.nav_lexicon');
$pagePretitle = t('admin.lex_pretitle');
$canWrite = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$visBadge = ['public' => 'bg-green-lt', 'admin_only' => 'bg-red-lt', 'review' => 'bg-yellow-lt', 'disabled' => 'bg-secondary-lt'];
$sourceById = [];
$visByCode = [];
foreach ($sources as $s) {
    $sourceById[(int)$s['id']] = $s;
    $visByCode[$s['code']] = $s['visibility'];
}
$langStatus = function (?string $status): string {
    return $status === null ? t('admin.lex_lang_not_added') : t('admin.lang_status_' . $status);
};
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['page' => 'admin-lexicon', 'lang' => $filters['lang'], 'q' => $filters['q'],
    'source' => $filters['source'] ?: null, 'exact' => $filters['exact'] ? 1 : null], $o), fn($v) => $v !== null && $v !== ''));

ob_start();
?>
<?php if (!$available): ?>
<div class="card"><div class="card-body">
    <h3 class="card-title"><?= $e(t('admin.lex_not_loaded_title')) ?></h3>
    <p class="text-secondary mb-0"><?= t('admin.lex_not_loaded_body') ?></p>
</div></div>
<?php else: ?>

<div class="card mb-3">
    <div class="card-body small text-secondary"><?= t('admin.lex_how') ?></div>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-search me-1"></i><?= $e(t('admin.lex_search_title')) ?></h3></div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="admin-lexicon">
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-secondary"><?= $e(t('admin.col_language')) ?></label>
                <select name="lang" class="form-select">
                    <?php foreach ($lexLangs as $code => $info): ?>
                    <option value="<?= $e($code) ?>" <?= $code === $filters['lang'] ? 'selected' : '' ?>><?= $e($code) ?> · <?= $n($info['entries']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-secondary"><?= $e(t('admin.lex_source')) ?></label>
                <select name="source" class="form-select">
                    <option value=""><?= $e(t('admin.lex_all_sources')) ?></option>
                    <?php foreach ($sources as $s): if (!(int)$s['entry_count']) continue; ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $filters['source'] ? 'selected' : '' ?>><?= $e($s['code']) ?> (<?= $e(t('admin.lex_vis_' . $s['visibility'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md">
                <label class="form-label small text-secondary"><?= $e(t('admin.lex_word')) ?></label>
                <input type="search" name="q" value="<?= $e($filters['q']) ?>" class="form-control" placeholder="<?= $e(t('admin.lex_word_ph')) ?>" lang="<?= $e($filters['lang']) ?>">
            </div>
            <div class="col-auto">
                <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="exact" value="1" <?= $filters['exact'] ? 'checked' : '' ?>><span class="form-check-label"><?= $e(t('admin.lex_exact')) ?></span></label>
            </div>
            <div class="col-auto"><button class="btn btn-primary"><i class="ti ti-search me-1"></i><?= $e(t('admin.search')) ?></button></div>
        </form>
        <?php $ls = $lexLangs[$filters['lang']]['status'] ?? null; ?>
        <div class="small mt-2 <?= $ls === 'published' ? 'text-green' : 'text-yellow' ?>">
            <i class="ti ti-language"></i> <?= $e(t('admin.lex_lang_site_status', ['lang' => $filters['lang'], 'status' => $langStatus($ls)])) ?>
        </div>
    </div>
    <?php if ($results !== null): ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover">
            <thead><tr><th><?= $e(t('admin.lex_word')) ?></th><th><?= $e(t('admin.lex_reading')) ?></th><th><?= $e(t('admin.type')) ?></th><th><?= $e(t('admin.lex_gloss')) ?></th><th><?= $e(t('admin.lex_sources')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($results['rows'] as $r): ?>
                <tr style="cursor:pointer" onclick="location.href='?page=admin-lexicon-entry&id=<?= (int)$r['id'] ?>'">
                    <td class="fw-semibold" lang="<?= $e($r['lang']) ?>"><a href="?page=admin-lexicon-entry&amp;id=<?= (int)$r['id'] ?>" class="text-reset"><?= $e($r['headword']) ?></a><?= $r['level'] ? ' <span class="badge bg-blue-lt">' . $e($r['level']) . '</span>' : '' ?></td>
                    <td class="text-secondary"><?= $e($r['reading']) ?></td>
                    <td class="text-secondary small"><?= $e($r['pos']) ?></td>
                    <td class="small text-truncate" style="max-width:340px;"><?= $e($r['first_gloss'] ?? '') ?></td>
                    <td class="small">
                        <?php foreach (explode(',', (string)$r['source_codes']) as $code): if ($code === '') continue; ?>
                            <span class="badge <?= $visBadge[$visByCode[$code] ?? 'review'] ?> me-1"><?= $e($code) ?></span>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$results['rows']): ?><tr><td colspan="5" class="text-center text-secondary py-4"><?= $e(t('admin.no_results')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pageNum > 1 || $results['more']): ?>
    <div class="card-footer d-flex align-items-center">
        <span class="text-secondary small"><?= $e(t('admin.page_label')) ?> <?= $pageNum ?></span>
        <ul class="pagination m-0 ms-auto">
            <li class="page-item<?= $pageNum <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum - 1])) ?>"><i class="ti ti-chevron-left"></i> <?= $e(t('admin.prev_page')) ?></a></li>
            <li class="page-item<?= !$results['more'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= $e($qs(['p' => $pageNum + 1])) ?>"><?= $e(t('admin.next_page')) ?> <i class="ti ti-chevron-right"></i></a></li>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Sources -->
<div class="card" id="sources">
    <div class="card-header"><h3 class="card-title"><i class="ti ti-database me-1"></i><?= $e(t('admin.lex_sources')) ?></h3><span class="card-subtitle ms-2"><?= $e(t('admin.lex_sources_sub', ['n' => count($sources)])) ?></span></div>
    <div class="list-group list-group-flush">
        <?php foreach ($sources as $s): ?>
        <div class="list-group-item">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-6" style="min-width:0;">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong><?= $e($s['name']) ?></strong>
                        <code class="small"><?= $e($s['code']) ?></code>
                        <span class="badge <?= $visBadge[$s['visibility']] ?>"><?= $e(t('admin.lex_vis_' . $s['visibility'])) ?></span>
                    </div>
                    <div class="text-secondary small text-break">
                        <?= $e($s['licence']) ?><?php if ($s['url']): ?> · <a href="<?= $e($s['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $e(parse_url($s['url'], PHP_URL_HOST) ?: $s['url']) ?></a><?php endif; ?>
                        · <?= $e(t('admin.lex_counts', ['e' => $n($s['entry_count']), 'r' => $n($s['row_count'])])) ?>
                    </div>
                    <?php if ($s['notes'] !== ''): ?><div class="text-secondary small text-break"><?= $e($s['notes']) ?></div><?php endif; ?>
                    <div class="text-secondary small text-break"><i class="ti ti-file-import"></i> <?= $e($s['origin']) ?></div>
                </div>
                <div class="col-12 col-lg-6 d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                    <?php if ((int)$s['entry_count']): ?>
                    <a class="btn btn-sm btn-ghost-secondary" href="<?= $e($qs(['source' => (int)$s['id'], 'q' => null, 'p' => null])) ?>"><i class="ti ti-list-search me-1"></i><?= $e(t('admin.lex_browse')) ?></a>
                    <?php endif; ?>
                    <?php if ($canWrite): ?>
                    <form method="POST" action="?page=admin-lexicon-action" class="d-flex gap-2">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
                        <select name="visibility" class="form-select form-select-sm w-auto" aria-label="<?= $e(t('admin.lex_visibility')) ?>">
                            <?php foreach (\App\Src\AdminLexicon::VISIBILITIES as $v): ?>
                            <option value="<?= $v ?>" <?= $s['visibility'] === $v ? 'selected' : '' ?>><?= $e(t('admin.lex_vis_' . $v)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary"><?= $e(t('admin.lang_save')) ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
