<?php
// One lexicon entry with everything every source has for it.
// Set by AdminController::lexiconEntry(): $data (see AdminLexicon::entry()).
$e = fn($v) => htmlspecialchars((string)$v);
$en = $data['entry'];
$title = $en['headword'] . ' · ' . t('admin.nav_lexicon');
$pageHeader = $en['headword'];
$pagePretitle = $en['lang'] . ($en['reading'] !== '' ? ' · ' . $en['reading'] : '') . ($en['pos'] !== '' ? ' · ' . $en['pos'] : '') . ($en['level'] ? ' · ' . $en['level'] : '');
$pageActions = '<a href="?page=admin-lexicon&amp;lang=' . $e($en['lang']) . '&amp;q=' . rawurlencode($en['headword']) . '&amp;exact=1" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>' . $e(t('admin.nav_lexicon')) . '</a>';
$visBadge = ['public' => 'bg-green-lt', 'admin_only' => 'bg-red-lt', 'review' => 'bg-yellow-lt', 'disabled' => 'bg-secondary-lt'];
$src = fn(array $r) => '<span class="badge ' . ($visBadge[$r['source_visibility']] ?? '') . '" title="' . $e(t('admin.lex_vis_' . $r['source_visibility'])) . '">' . $e($r['source_code']) . '</span>';
$dir = \App\Src\Language::info($en['lang'])['dir'] ?? ($en['lang'] === 'ar' ? 'rtl' : 'ltr');

ob_start();
?>
<div class="row row-cards">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="display-6 mb-1" lang="<?= $e($en['lang']) ?>" dir="<?= $e($dir) ?>"><?= $e($en['headword']) ?></div>
                <?php if ($en['reading'] !== ''): ?><div class="fs-3 text-secondary mb-2"><?= $e($en['reading']) ?></div><?php endif; ?>
                <div class="d-flex flex-wrap gap-1 mb-2"><?php foreach ($data['sources'] as $r): ?><?= $src($r) ?><?php endforeach; ?></div>
                <?php if ($en['extra']): ?>
                <div class="small text-secondary text-break"><code><?= $e($en['extra']) ?></code></div>
                <?php endif; ?>
                <?php foreach ($data['prons'] as $p): ?>
                <div class="small mt-1"><?= $src($p) ?> <?= $e($p['word']) ?> <?= $p['ipa'] ? '<span class="text-secondary">' . $e($p['ipa']) . '</span>' : '' ?><?= $p['stress'] !== null ? ' · ' . $e(t('admin.lex_stress', ['n' => $p['stress']])) : '' ?><?= $p['variant'] ? ' (' . $e($p['variant']) . ')' : '' ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.lex_glosses')) ?></h3></div>
            <div class="list-group list-group-flush">
                <?php foreach ($data['senses'] as $s): ?>
                <div class="list-group-item">
                    <div class="d-flex gap-2 align-items-start"><?= $src($s) ?><span class="badge bg-secondary-lt"><?= $e($s['gloss_lang']) ?></span><div class="text-break"><?= $e($s['gloss']) ?></div></div>
                    <?php if ($s['definition'] && $s['definition'] !== $s['gloss']): ?><div class="small text-secondary mt-1 text-break"><?= $e(mb_substr($s['definition'], 0, 600)) ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (!$data['senses']): ?><div class="list-group-item text-secondary"><?= $e(t('admin.no_records')) ?></div><?php endif; ?>
            </div>
        </div>

        <?php if ($data['links']): ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.lex_links')) ?></h3></div>
            <div class="list-group list-group-flush small">
                <?php foreach ($data['links'] as $l): ?>
                <a class="list-group-item list-group-item-action d-flex gap-2 align-items-center" href="?page=admin-lexicon-entry&amp;id=<?= (int)$l['other_id'] ?>">
                    <?= $src($l) ?><span class="text-secondary"><?= $e($l['link_type']) ?></span>
                    <code><?= $e($l['other_lang']) ?></code><span lang="<?= $e($l['other_lang']) ?>"><?= $e($l['other_headword']) ?></span>
                    <?php if ($l['confidence'] !== null): ?><span class="ms-auto text-secondary"><?= $e(round((float)$l['confidence'], 2)) ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <?php if ($data['conj']): ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.lex_conjugation')) ?></h3><span class="card-subtitle ms-2"><?= count($data['conj']) ?></span></div>
            <div class="table-responsive" style="max-height:520px;">
                <table class="table table-sm table-vcenter card-table">
                    <thead><tr><th><?= $e(t('admin.lex_tense')) ?></th><th><?= $e(t('admin.lex_person')) ?></th><th><?= $e(t('admin.lex_form')) ?></th><th><?= $e(t('admin.lex_gloss')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($data['conj'] as $c): ?>
                        <tr><td class="small text-secondary"><?= $e($c['tense']) ?></td><td class="small"><?= $e($c['person']) ?></td>
                            <td class="fw-semibold" lang="<?= $e($en['lang']) ?>" dir="<?= $e($dir) ?>"><?= $e($c['form']) ?><?= $c['translit'] ? ' <span class="text-secondary fw-normal small">' . $e($c['translit']) . '</span>' : '' ?></td>
                            <td class="small text-secondary"><?= $e($c['form_gloss'] ?? '') ?></td><td><?= $src($c) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($data['forms']): ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.lex_forms')) ?></h3><span class="card-subtitle ms-2"><?= count($data['forms']) ?></span></div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php foreach ($data['forms'] as $f): ?>
                <span class="border rounded px-2 py-1 small" style="border-color:var(--tblr-border-color)!important;" title="<?= $e($f['source_code'] . ' · ' . $f['tags']) ?>">
                    <span lang="<?= $e($en['lang']) ?>"><?= $e($f['form']) ?></span><?= $f['tags'] !== '' ? ' <span class="text-secondary">' . $e(mb_substr($f['tags'], 0, 24)) . '</span>' : '' ?>
                    <span class="badge <?= $visBadge[$f['source_visibility']] ?? '' ?> ms-1" style="font-size:.6rem;"><?= $e($f['source_code']) ?></span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($data['sentences']): ?>
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.lex_sentences')) ?></h3></div>
            <div class="list-group list-group-flush">
                <?php foreach ($data['sentences'] as $s): ?>
                <div class="list-group-item">
                    <div class="d-flex gap-2 align-items-start"><?= $src($s) ?><div lang="<?= $e($en['lang']) ?>" dir="<?= $e($dir) ?>" class="text-break"><?= $e($s['text']) ?></div></div>
                    <?php if ($s['translit']): ?><div class="small text-secondary"><?= $e($s['translit']) ?></div><?php endif; ?>
                    <?php if ($s['translation']): ?><div class="small text-secondary"><?= $e($s['translation']) ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
