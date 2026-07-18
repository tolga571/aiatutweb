<?php
// views/admin/settings.php
// Admin settings page – form to edit .env variables

$title = __('admin.settings');
ob_start();
?>
<h2 class="my-4"><?= __('admin.settings_title') ?></h2>

<?php if (!empty($_SESSION['admin_settings_msg'])): ?>
    <div class="alert alert-success" role="alert">
        <?= htmlspecialchars($_SESSION['admin_settings_msg']) ?>
    </div>
    <?php unset($_SESSION['admin_settings_msg']); ?>
<?php endif; ?>

<form method="POST" action="?page=admin-settings-save" class="grid">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div class="form-group mb-3">
        <label for="premium_price_id" class="form-label"><?= __('admin.premium_price_id') ?></label>
        <input type="text" class="form-control" id="premium_price_id" name="premium_price_id" value="<?= htmlspecialchars($config['PADDLE_PREMIUM_PLAN_PRICE_ID'] ?? '') ?>">
    </div>
    <div class="form-group mb-3">
        <label for="starter_price_id" class="form-label"><?= __('admin.starter_price_id') ?></label>
        <input type="text" class="form-control" id="starter_price_id" name="starter_price_id" value="<?= htmlspecialchars($config['PADDLE_STARTER_PLAN_PRICE_ID'] ?? '') ?>">
    </div>
    <div class="form-group mb-3">
        <label for="pro_price_id" class="form-label"><?= __('admin.pro_price_id') ?></label>
        <input type="text" class="form-control" id="pro_price_id" name="pro_price_id" value="<?= htmlspecialchars($config['PADDLE_PRO_PLAN_PRICE_ID'] ?? '') ?>">
    </div>
    <div class="form-group mb-3">
        <label for="starter_yearly_price_id" class="form-label"><?= __('admin.starter_yearly_price_id') ?></label>
        <input type="text" class="form-control" id="starter_yearly_price_id" name="starter_yearly_price_id" value="<?= htmlspecialchars($config['PADDLE_STARTER_YEARLY_PRICE_ID'] ?? '') ?>">
    </div>
    <div class="form-group mb-3">
        <label for="pro_yearly_price_id" class="form-label"><?= __('admin.pro_yearly_price_id') ?></label>
        <input type="text" class="form-control" id="pro_yearly_price_id" name="pro_yearly_price_id" value="<?= htmlspecialchars($config['PADDLE_PRO_YEARLY_PRICE_ID'] ?? '') ?>">
    </div>
    <div class="form-group mb-3">
        <label for="premium_yearly_price_id" class="form-label"><?= __('admin.premium_yearly_price_id') ?></label>
        <input type="text" class="form-control" id="premium_yearly_price_id" name="premium_yearly_price_id" value="<?= htmlspecialchars($config['PADDLE_PREMIUM_YEARLY_PRICE_ID'] ?? '') ?>">
    </div>
    <div class="form-group mb-3">
        <label for="webhook_secret" class="form-label"><?= __('admin.webhook_secret') ?></label>
        <input type="text" class="form-control" id="webhook_secret" name="webhook_secret" value="<?= htmlspecialchars($config['PADDLE_WEBHOOK_SECRET'] ?? '') ?>">
    </div>

    <button type="submit" class="btn btn-primary"><?= __('admin.save_settings') ?></button>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
