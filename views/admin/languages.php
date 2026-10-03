<?php
// Languages registry + UI-string coverage. Set by AdminController::languages():
// $registry, $coverage, $geminiReady, $csrf.
$title = t('admin.languages_title');
$pageHeader = t('admin.languages_title');
$pagePretitle = t('admin.languages_pretitle');
$e = fn($v) => htmlspecialchars((string)$v);
$canWrite = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$statusBadge = [
    'published' => 'bg-green-lt',
    'draft' => 'bg-yellow-lt',
    'disabled' => 'bg-secondary-lt',
];

ob_start();
?>
<?php if (!$geminiReady): ?>
<div class="alert alert-warning"><?= $e(t('admin.lang_ai_missing_key')) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body small text-secondary">
        <strong class="text-body"><?= $e(t('admin.lang_how_title')) ?></strong>
        <?= t('admin.lang_how_body') ?>
    </div>
</div>

<div class="row row-cards">
    <?php foreach ($registry as $code => $l):
        $cov = $coverage[$code];
        $pct = $cov['total'] ? (int)floor($cov['translated'] * 100 / $cov['total']) : 0; ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100" id="lang-<?= $e($code) ?>">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2 flex-fill" style="min-width:0;">
                    <?php if ($l['flag']): ?><img src="https://flagcdn.com/<?= $e($l['flag']) ?>.svg" alt="" width="22" height="16" style="border-radius:2px;object-fit:cover;"><?php endif; ?>
                    <div class="text-truncate">
                        <div class="fw-semibold text-truncate" lang="<?= $e($code) ?>"><?= $e($l['native_name']) ?></div>
                        <div class="text-secondary small"><?= $e($l['name']) ?> · <code><?= $e($code) ?></code><?= $l['dir'] === 'rtl' ? ' · RTL' : '' ?></div>
                    </div>
                </div>
                <span class="badge <?= $statusBadge[$l['status']] ?? '' ?>"><?= $e(t('admin.lang_status_' . $l['status'])) ?></span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= $e(t('admin.lang_col_coverage')) ?></span>
                    <span class="text-secondary" data-cov-text><?= $cov['translated'] ?> / <?= $cov['total'] ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress progress-sm mb-2"><div class="progress-bar" data-cov-bar style="width: <?= $pct ?>%"></div></div>
                <div class="text-secondary small mb-3"><?= $e(t('admin.lang_db_counts', ['db' => $cov['db'], 'ai' => $cov['ai']])) ?></div>

                <form method="POST" action="?page=admin-languages-action" class="mb-3">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="code" value="<?= $e($code) ?>">
                    <div class="row g-2 align-items-center">
                        <div class="col-12">
                            <select name="status" class="form-select form-select-sm" <?= $canWrite && $code !== \App\Src\Language::DEFAULT ? '' : 'disabled' ?> aria-label="<?= $e(t('admin.lang_col_status')) ?>">
                                <?php foreach (\App\Src\UiTranslator::STATUSES as $st): ?>
                                <option value="<?= $st ?>" <?= $l['status'] === $st ? 'selected' : '' ?>><?= $e(t('admin.lang_status_' . $st)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($code === \App\Src\Language::DEFAULT): ?><input type="hidden" name="status" value="published"><?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <label class="form-check form-check-inline mb-0 small">
                                <input class="form-check-input" type="checkbox" name="ui_enabled" value="1" <?= $l['ui_enabled'] ? 'checked' : '' ?> <?= $canWrite ? '' : 'disabled' ?>>
                                <span class="form-check-label"><?= $e(t('admin.lang_use_ui')) ?></span>
                            </label>
                            <label class="form-check form-check-inline mb-0 small">
                                <input class="form-check-input" type="checkbox" name="learn_enabled" value="1" <?= $l['learn_enabled'] ? 'checked' : '' ?> <?= $canWrite ? '' : 'disabled' ?>>
                                <span class="form-check-label"><?= $e(t('admin.lang_use_learn')) ?></span>
                            </label>
                        </div>
                        <?php if ($canWrite): ?>
                        <div class="col-auto ms-auto"><button class="btn btn-sm btn-primary"><?= $e(t('admin.lang_save')) ?></button></div>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($canWrite && $code !== \App\Src\Language::DEFAULT && $geminiReady): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-translate="<?= $e($code) ?>" <?= $cov['missing'] ? '' : 'disabled' ?>>
                        <i class="ti ti-sparkles me-1"></i><?= $e(t('admin.lang_translate_ai')) ?>
                    </button>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="?page=admin-language-strings&code=<?= $e($code) ?>"><i class="ti ti-pencil me-1"></i><?= $e(t('admin.lang_edit_strings')) ?></a>
                    <a class="btn btn-sm btn-outline-secondary" href="?page=admin-language-export&code=<?= $e($code) ?>"><i class="ti ti-download me-1"></i><?= $e(t('admin.lang_export')) ?></a>
                    <a class="btn btn-sm btn-ghost-secondary" href="/?ui_lang=<?= $e($code) ?>" target="_blank" rel="noopener"><i class="ti ti-external-link me-1"></i><?= $e(t('admin.lang_view_site')) ?></a>
                    <?php if ($canWrite && $cov['ai'] > 0): ?>
                    <form method="POST" action="?page=admin-languages-action" onsubmit="return confirm(<?= $e(json_encode(t('admin.lang_clear_ai_confirm'), JSON_UNESCAPED_UNICODE)) ?>)">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="action" value="clear_ai">
                        <input type="hidden" name="code" value="<?= $e($code) ?>">
                        <button class="btn btn-sm btn-ghost-danger"><i class="ti ti-trash me-1"></i><?= $e(t('admin.lang_clear_ai')) ?></button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="small mt-2" data-translate-status="<?= $e($code) ?>" aria-live="polite"></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($canWrite): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><?= $e(t('admin.lang_add_title')) ?></h3></div>
            <div class="card-body">
                <p class="text-secondary small"><?= $e(t('admin.lang_add_help')) ?></p>
                <form method="POST" action="?page=admin-languages-action" class="row g-2">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="col-4"><label class="form-label small"><?= $e(t('admin.lang_code')) ?></label><input name="code" class="form-control form-control-sm" required pattern="[a-z]{2,3}" maxlength="3" placeholder="ru"></div>
                    <div class="col-8"><label class="form-label small"><?= $e(t('admin.lang_name_en')) ?></label><input name="name" class="form-control form-control-sm" required maxlength="40" placeholder="Russian"></div>
                    <div class="col-12"><label class="form-label small"><?= $e(t('admin.lang_native_name')) ?></label><input name="native_name" class="form-control form-control-sm" required maxlength="40" placeholder="Русский"></div>
                    <div class="col-4"><label class="form-label small"><?= $e(t('admin.lang_flag')) ?></label><input name="flag" class="form-control form-control-sm" maxlength="10" placeholder="ru"></div>
                    <div class="col-4"><label class="form-label small"><?= $e(t('admin.lang_dir')) ?></label>
                        <select name="dir" class="form-select form-select-sm"><option value="ltr">LTR</option><option value="rtl">RTL</option></select></div>
                    <div class="col-4"><label class="form-label small"><?= $e(t('admin.lang_speech')) ?></label><input name="speech_locale" class="form-control form-control-sm" maxlength="10" placeholder="ru-RU"></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary"><?= $e(t('admin.lang_add_btn')) ?></button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
  var csrf = <?= json_encode($csrf) ?>;
  var T = <?= json_encode([
      'running' => t('admin.lang_translating'),
      'done' => t('admin.lang_translate_done'),
      'rejected' => t('admin.lang_translate_rejected'),
      'failed' => t('admin.lang_ai_failed'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  function fmt(s, v) { for (var k in v) { s = s.split('{' + k + '}').join(v[k]); } return s; }
  document.querySelectorAll('[data-translate]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var code = btn.getAttribute('data-translate');
      var out = document.querySelector('[data-translate-status="' + code + '"]');
      var done = 0, rejected = 0, lastRemaining = -1;
      btn.disabled = true;
      function step() {
        var body = new URLSearchParams({ csrf: csrf, code: code });
        fetch('?page=admin-language-translate', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (r) {
            if (!r.ok) { out.className = 'small mt-2 text-danger'; out.textContent = r.error || T.failed; btn.disabled = false; return; }
            done += r.translated; rejected += r.rejected.length;
            out.className = 'small mt-2 text-secondary';
            out.textContent = fmt(T.running, { done: done, remaining: r.remaining });
            // Stop when nothing is left, or when a batch made no progress
            // (every string in it was rejected) so we don't loop forever.
            if (r.remaining > 0 && r.translated > 0 && r.remaining !== lastRemaining) { lastRemaining = r.remaining; step(); return; }
            out.className = 'small mt-2 text-success';
            out.textContent = fmt(T.done, { done: done }) + (rejected ? ' ' + fmt(T.rejected, { n: rejected }) : '');
            setTimeout(function () { location.reload(); }, 1500);
          })
          .catch(function () { out.className = 'small mt-2 text-danger'; out.textContent = T.failed; btn.disabled = false; });
      }
      step();
    });
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
