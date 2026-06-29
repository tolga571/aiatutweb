<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$title = $title ?? __('admin.title');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tabler@latest/dist/css/tabler.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/tabler@latest/dist/js/tabler.min.js" defer></script>
</head>
<body>
<div class="page">
    <header class="navbar navbar-expand-md navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="?page=admin-dashboard"><?= __('admin.title') ?></a>
            <div class="d-flex ms-auto">
                <a href="?page=admin-logout" class="nav-link text-white"><?= __('admin.logout') ?></a>
            </div>
        </div>
    </header>
    <div class="navbar-expand-md">
        <div class="d-md-flex">
            <aside class="sidebar border-end p-3" style="width: 220px;">
                <ul class="navbar-nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="?page=admin-dashboard"><?= __('admin.dashboard') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=admin-users"><?= __('admin.users') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=admin-admins"><?= __('admin.admins') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=admin-payments"><?= __('admin.payments') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=admin-conversations"><?= __('admin.conversations') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=admin-settings"><?= __('admin.settings') ?></a></li>
<li class="nav-item"><a class="nav-link" href="?page=privacy-policy"><?= __('nav.privacy_policy') ?></a></li>
<li class="nav-item"><a class="nav-link" href="?page=terms-and-conditions"><?= __('nav.terms_conditions') ?></a></li>
<li class="nav-item"><a class="nav-link" href="?page=refund-policy"><?= __('nav.refund_policy') ?></a></li>
<li class="nav-item"><a class="nav-link" href="?page=cookie-policy"><?= __('nav.cookie_policy') ?></a></li>

<li class="nav-item"><a class="nav-link" href="?page=pricing"><?= __('nav.pricing') ?></a></li>
<li class="nav-item"><a class="nav-link" href="?page=about"><?= __('admin.pages_about') ?></a></li>

<li class="nav-item"><a class="nav-link" href="?page=contact"><?= __('admin.pages_contact') ?></a></li>
<li class="nav-item"><a class="nav-link" href="?page=faq"><?= __('nav.faq') ?></a></li>
                </ul>
            </aside>
            <main class="flex-fill p-4">
                <?php if (isset($content)) { echo $content; } ?>
            </main>
        </div>
    </div>
</div>
</body>
</html>
