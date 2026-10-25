<?php
// New story / edit a story. Set by AdminController::storyEdit():
// $story (or null), $source, $have, $langs, $steps, $categories, $csrf.
$e = fn($v) => htmlspecialchars((string)$v);
$title = $story ? ($source['title'] ?? $story['slug']) : t('admin.story_new');
$pageHeader = $title;
$pagePretitle = t('admin.nav_stories');
$canWrite = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$missing = array_values(array_diff($langs, $have));
ob_start();
?>
<div class="row g-3">
<div class="col-lg-7">
<div class="card">
    <div class="card-header"><h3 class="card-title"><?= $e(t('admin.story_source')) ?></h3></div>
    <div class="card-body">
        <p class="small text-secondary"><?= $e(t('admin.story_source_help')) ?></p>
        <form method="POST" action="?page=admin-story-action">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="save">
            <?php if ($story): ?><input type="hidden" name="id" value="<?= (int)$story['id'] ?>"><?php endif; ?>
            <div class="mb-3"><label class="form-label" for="s-title"><?= $e(t('admin.story_title')) ?></label>
                <input id="s-title" name="title" class="form-control" maxlength="120" required value="<?= $e($source['title'] ?? '') ?>" <?= $canWrite ? '' : 'disabled' ?>></div>
            <div class="mb-3"><label class="form-label" for="s-summary"><?= $e(t('admin.story_summary')) ?></label>
                <input id="s-summary" name="summary" class="form-control" maxlength="300" value="<?= $e($source['summary'] ?? '') ?>" <?= $canWrite ? '' : 'disabled' ?>></div>
            <div class="row g-2 mb-3">
                <div class="col-sm-6"><label class="form-label" for="s-cat"><?= $e(t('admin.story_category')) ?></label>
                    <select id="s-cat" name="category" class="form-select" <?= $canWrite ? '' : 'disabled' ?>>
                        <?php foreach ($categories as $c): ?><option value="<?= $e($c['slug']) ?>" <?= ($story['category'] ?? '') === $c['slug'] ? 'selected' : '' ?>><?= $e($c['slug']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-sm-6"><label class="form-label" for="s-src"><?= $e(t('admin.story_origin')) ?></label>
                    <input id="s-src" name="source" class="form-control" maxlength="200" value="<?= $e($story['source'] ?? '') ?>" <?= $canWrite ? '' : 'disabled' ?>></div>
            </div>
            <div class="mb-3"><label class="form-label" for="s-body"><?= $e(t('admin.story_body')) ?></label>
                <textarea id="s-body" name="body" class="form-control" rows="14" required <?= $canWrite ? '' : 'disabled' ?>><?= $e(implode("\n", $source['sentences'] ?? [])) ?></textarea>
                <div class="form-hint"><?= $e(t('admin.story_body_help')) ?></div></div>
            <?php if ($canWrite): ?>
            <button class="btn btn-primary"><?= $e(t($story ? 'admin.story_save_rebuild' : 'admin.story_create')) ?></button>
            <?php if ($story): ?><div class="form-hint mt-2"><?= $e(t('admin.story_save_warning')) ?></div><?php endif; ?>
            <?php endif; ?>
        </form>
    </div>
</div>
</div>

<?php if ($story): ?>
<div class="col-lg-5">
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title"><?= $e(t('admin.story_languages')) ?></h3></div>
    <div class="card-body">
        <p class="small text-secondary"><?= $e(t('admin.story_build_help', ['n' => $steps])) ?></p>
        <ul class="list-unstyled mb-3" id="build-list">
            <?php foreach ($langs as $l): ?>
            <li class="d-flex justify-content-between py-1 border-bottom" data-lang="<?= $e($l) ?>">
                <span><?= $e(\App\Src\Language::langName($l)) ?> <span class="text-secondary">(<?= $e($l) ?>)</span></span>
                <span class="build-state <?= in_array($l, $have, true) ? 'text-green' : 'text-secondary' ?>"><?= $e(in_array($l, $have, true) ? t('admin.story_lang_ready') : t('admin.story_lang_missing')) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($canWrite): ?>
        <button type="button" class="btn btn-outline-primary w-100" id="build-btn" <?= $missing ? '' : 'disabled' ?>><?= $e(t('admin.story_build')) ?></button>
        <p class="small mt-2 mb-0" id="build-msg" role="status"></p>
        <?php endif; ?>
    </div>
</div>
<div class="card">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <span class="badge <?= $story['status'] === 'published' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= $e(t('admin.story_status_' . $story['status'])) ?></span>
        <?php if ($story['status'] === 'published'): ?><a href="/stories/<?= $e($story['slug']) ?>/en" target="_blank" rel="noopener"><?= $e(t('admin.story_view')) ?></a><?php endif; ?>
        <?php if ($canWrite): ?>
        <form method="POST" action="?page=admin-story-action" class="ms-auto d-flex gap-2">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$story['id'] ?>">
            <?php if ($story['status'] === 'published'): ?>
                <button name="action" value="unpublish" class="btn btn-outline-secondary"><?= $e(t('admin.story_unpublish')) ?></button>
            <?php else: ?>
                <button name="action" value="publish" class="btn btn-success" <?= $missing ? 'disabled' : '' ?>><?= $e(t('admin.story_publish')) ?></button>
            <?php endif; ?>
            <button name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm(<?= $e(json_encode(t('admin.story_delete_confirm'))) ?>)"><?= $e(t('admin.story_delete')) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>
<?php endif; ?>
</div>

<?php if ($story && $canWrite): ?>
<script>
(function () {
  // Builds every missing language, one step (a few sentences) per request:
  // each request must answer within Cloudflare's 100 s.
  var btn = document.getElementById('build-btn'), msg = document.getElementById('build-msg');
  var missing = <?= json_encode($missing) ?>, steps = <?= (int)$steps ?>;
  var T = <?= json_encode(['working' => t('admin.story_lang_working'), 'ready' => t('admin.story_lang_ready'), 'failed' => t('admin.story_lang_failed'), 'done' => t('admin.story_build_done')]) ?>;
  function state(l, text, cls) {
    var el = document.querySelector('#build-list [data-lang="' + l + '"] .build-state');
    el.textContent = text; el.className = 'build-state ' + cls;
  }
  function call(l, step) {
    var fd = new FormData();
    fd.append('csrf', <?= json_encode($csrf) ?>); fd.append('action', 'build');
    fd.append('id', <?= (int)$story['id'] ?>); fd.append('lang', l); fd.append('step', step);
    return fetch('?page=admin-story-action', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, error: 'network' }; });
  }
  btn.addEventListener('click', async function () {
    btn.disabled = true;
    var failed = 0;
    for (var i = 0; i < missing.length; i++) {
      var l = missing[i], ok = true;
      for (var st = 0; st < steps; st++) {
        state(l, T.working + ' ' + (st + 1) + '/' + steps, 'text-yellow');
        var r = await call(l, st);
        if (!r.ok) { ok = false; state(l, T.failed + (r.detail ? ': ' + r.detail : ''), 'text-red'); break; }
      }
      if (ok) state(l, T.ready, 'text-green'); else failed++;
    }
    msg.textContent = failed ? '' : T.done;
    if (!failed) setTimeout(function () { location.reload(); }, 800); else btn.disabled = false;
  });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
