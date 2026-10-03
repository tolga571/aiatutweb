<?php
// String editor for one language. Set by AdminController::languageStrings():
// $code, $lang, $filter, $search, $rows (this page), $total, $pageNo, $perPage, $csrf.
$title = t('admin.lang_strings_title', ['name' => $lang['name']]);
$pageHeader = $title;
$pagePretitle = $lang['native_name'] . ' · ' . $code;
$e = fn($v) => htmlspecialchars((string)$v);
$canWrite = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$isEnglish = $code === \App\Src\Language::DEFAULT;
$pages = max(1, (int)ceil($total / $perPage));
$link = fn(array $over) => '?' . http_build_query(array_merge(['page' => 'admin-language-strings', 'code' => $code, 'filter' => $filter, 'q' => $search, 'p' => $pageNo], $over));
$srcBadge = [
    'file' => ['bg-secondary-lt', t('admin.lang_src_file')],
    'ai' => ['bg-purple-lt', t('admin.lang_src_ai')],
    'manual' => ['bg-blue-lt', t('admin.lang_src_manual')],
    '' => ['bg-red-lt', t('admin.lang_src_missing')],
];
$pageActions = '<a class="btn btn-outline-secondary" href="?page=admin-languages"><i class="ti ti-arrow-left me-1"></i>' . $e(t('admin.lang_back')) . '</a>';

ob_start();
?>
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="admin-language-strings">
            <input type="hidden" name="code" value="<?= $e($code) ?>">
            <div class="col-12 col-md">
                <input type="search" name="q" value="<?= $e($search) ?>" class="form-control" placeholder="<?= $e(t('admin.lang_search')) ?>" aria-label="<?= $e(t('admin.lang_search')) ?>">
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group">
                    <?php foreach (['all', 'missing', 'ai', 'manual'] as $f): ?>
                    <a class="btn<?= $filter === $f ? ' btn-primary' : ' btn-outline-secondary' ?>" href="<?= $e($link(['filter' => $f, 'p' => 1])) ?>"><?= $e(t('admin.lang_filter_' . $f)) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-auto"><button class="btn btn-outline-secondary"><i class="ti ti-search"></i></button></div>
        </form>
    </div>
</div>

<?php if ($isEnglish): ?>
<div class="alert alert-info"><?= $e(t('admin.lang_english_master')) ?></div>
<?php endif; ?>

<div class="card">
    <div class="list-group list-group-flush">
        <?php if (!$rows): ?>
        <div class="list-group-item text-secondary"><?= $e(t('admin.no_results')) ?></div>
        <?php endif; ?>
        <?php foreach ($rows as $r): [$bCls, $bTxt] = $srcBadge[$r['source']]; ?>
        <div class="list-group-item" id="k-<?= $e($r['key']) ?>">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                <code class="small text-break"><?= $e($r['key']) ?></code>
                <span class="badge <?= $bCls ?>"><?= $e($bTxt) ?></span>
            </div>
            <?php if (!$isEnglish): ?>
            <div class="text-secondary small mb-2 text-break"><span class="text-uppercase fw-semibold me-1">en</span><?= $e($r['english']) ?></div>
            <?php endif; ?>
            <?php if ($canWrite && !$isEnglish): ?>
            <form method="POST" action="?page=admin-language-string-action" class="d-flex flex-column flex-md-row gap-2">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="code" value="<?= $e($code) ?>">
                <input type="hidden" name="key" value="<?= $e($r['key']) ?>">
                <input type="hidden" name="filter" value="<?= $e($filter) ?>">
                <input type="hidden" name="q" value="<?= $e($search) ?>">
                <input type="hidden" name="p" value="<?= (int)$pageNo ?>">
                <textarea name="value" rows="<?= mb_strlen($r['english']) > 90 ? 3 : 1 ?>" class="form-control form-control-sm" dir="<?= $e($lang['dir']) ?>" lang="<?= $e($code) ?>" aria-label="<?= $e($r['key']) ?>"><?= $e($r['value']) ?></textarea>
                <div class="d-flex gap-2 align-self-start">
                    <button class="btn btn-sm btn-primary" name="action" value="save"><?= $e(t('admin.lang_save')) ?></button>
                    <?php if ($r['source'] === 'ai' || $r['source'] === 'manual'): ?>
                    <button class="btn btn-sm btn-ghost-secondary" name="action" value="reset" title="<?= $e(t('admin.lang_reset_help')) ?>"><?= $e(t('admin.lang_reset')) ?></button>
                    <?php endif; ?>
                </div>
            </form>
            <?php else: ?>
            <div class="text-break" dir="<?= $e($lang['dir']) ?>" lang="<?= $e($code) ?>"><?= $e($r['value'] !== '' ? $r['value'] : '—') ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-secondary small"><?= $e(t('admin.lang_paging', ['p' => $pageNo, 'pages' => $pages, 'total' => $total])) ?></span>
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary<?= $pageNo <= 1 ? ' disabled' : '' ?>" href="<?= $e($link(['p' => $pageNo - 1])) ?>"><?= $e(t('admin.prev_page')) ?></a>
            <a class="btn btn-sm btn-outline-secondary<?= $pageNo >= $pages ? ' disabled' : '' ?>" href="<?= $e($link(['p' => $pageNo + 1])) ?>"><?= $e(t('admin.next_page')) ?></a>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
