<?php
// Story list + categories. Set by AdminController::stories(): $list, $categories, $csrf.
$e = fn($v) => htmlspecialchars((string)$v);
$title = t('admin.nav_stories');
$pageHeader = t('admin.nav_stories');
$pagePretitle = t('admin.nav_content');
$canWrite = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$langTotal = count(\App\Src\Language::registry());
$pageActions = $canWrite ? '<a href="?page=admin-story" class="btn btn-primary"><i class="ti ti-plus me-1"></i>' . $e(t('admin.story_new')) . '</a>' : '';
ob_start();
?>
<div class="card mb-3">
    <div class="card-body small text-secondary"><?= $e(t('admin.story_how')) ?></div>
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr>
                <th><?= $e(t('admin.story_title')) ?></th><th><?= $e(t('admin.story_category')) ?></th><th><?= $e(t('admin.story_length')) ?></th>
                <th><?= $e(t('admin.story_languages')) ?></th><th><?= $e(t('admin.col_status')) ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$list): ?>
                <tr><td colspan="6" class="text-secondary"><?= $e(t('admin.story_none')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($list as $s): ?>
                <tr>
                    <td><a href="?page=admin-story&amp;id=<?= (int)$s['id'] ?>"><?= $e($s['title_en'] ?: (json_decode((string)$s['source_json'], true)['title'] ?? $s['slug'])) ?></a><div class="small text-secondary"><?= $e($s['slug']) ?></div></td>
                    <td><?= $e($s['category']) ?></td>
                    <td><?= $e($s['length']) ?></td>
                    <td><?= (int)$s['langs'] ?> / <?= $langTotal ?></td>
                    <td><span class="badge <?= $s['status'] === 'published' ? 'bg-green-lt' : 'bg-secondary-lt' ?>"><?= $e(t('admin.story_status_' . $s['status'])) ?></span></td>
                    <td class="text-end"><?php if ($s['status'] === 'published'): ?><a href="/stories/<?= $e($s['slug']) ?>/en" target="_blank" rel="noopener"><?= $e(t('admin.story_view')) ?></a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><?= $e(t('admin.story_categories')) ?></h3></div>
    <div class="card-body">
        <p class="mb-3"><?php foreach ($categories as $c): ?><span class="badge bg-secondary-lt me-1"><?= $e($c['slug']) ?> · <?= (int)$c['count'] ?></span><?php endforeach; ?></p>
        <?php if ($canWrite): ?>
        <form method="POST" action="?page=admin-story-action" class="row g-2 align-items-end">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="category">
            <div class="col-sm-4"><label class="form-label small text-secondary" for="cat-slug"><?= $e(t('admin.story_cat_slug')) ?></label><input id="cat-slug" name="slug" class="form-control" maxlength="40" required pattern="[a-z0-9-]+"></div>
            <div class="col-sm-5"><label class="form-label small text-secondary" for="cat-name"><?= $e(t('admin.story_cat_name')) ?></label><input id="cat-name" name="name" class="form-control" maxlength="60" required></div>
            <div class="col-sm-3"><button class="btn btn-outline-primary w-100"><?= $e(t('admin.story_cat_add')) ?></button></div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
