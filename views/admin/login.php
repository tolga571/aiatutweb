<?php
// views/admin/login.php
// Admin login form – CSRF token is generated in AdminController::showLogin()
$title = __('admin.login_title');
ob_start();
?>
<div class="content" style="max-width:400px;margin:auto;">
    <h2><?= __('admin.login_heading') ?></h2>
    <?php if (!empty($_SESSION['admin_login_error'])): ?>
        <div style="color:#ff6b6b;">
            <?= htmlspecialchars($_SESSION['admin_login_error']) ?>
        </div>
    <?php endif; unset($_SESSION['admin_login_error']); ?>
    <form method="POST" action="?page=admin-login">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label for="email"><?= __('admin.email') ?></label>
        <input type="email" id="email" name="email" required style="width:100%;margin:5px 0;">
        <label for="password"><?= __('admin.password') ?></label>
        <input type="password" id="password" name="password" required style="width:100%;margin:5px 0;">
        <button type="submit" style="background:#28a745;color:#fff;padding:8px 16px;border:none;cursor:pointer;"><?= __('admin.login_btn') ?></button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
