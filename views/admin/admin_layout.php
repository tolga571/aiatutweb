<?php
// Shared admin shell: dark Tabler layout with a vertical sidebar.
// Views set $content (and optionally $title, $pageHeader, $pagePretitle,
// $pageActions) and then require this file. Library versions are pinned —
// an unpinned @latest once changed the panel's look on its own.
$title = $title ?? __('admin.title');
$currentPage = (string)($_GET['page'] ?? 'admin-dashboard');
$adminRow = isset($this) && property_exists($this, 'currentAdmin') ? $this->currentAdmin : null;
$adminRole = $_SESSION['admin_role'] ?? 'admin';

$navSections = [
    'Genel' => [
        ['admin-dashboard', 'layout-dashboard', __('admin.dashboard')],
    ],
    'Kullanıcılar' => [
        ['admin-users', 'users', __('admin.users')],
        ['admin-conversations', 'messages', __('admin.conversations')],
    ],
    'Gelir' => [
        ['admin-payments', 'credit-card', 'Gelir & abonelikler'],
        ['admin-activity', 'activity', 'Olay akışı'],
    ],
    'Sistem' => [
        ['admin-ai-usage', 'sparkles', 'AI kullanımı & maliyet'],
        ['admin-admins', 'shield-lock', __('admin.admins')],
        ['admin-settings', 'settings', __('admin.settings')],
    ],
];
// Detail pages highlight their parent list.
$activeAlias = ['admin-conversation' => 'admin-conversations'];
$activePage = $activeAlias[$currentPage] ?? $currentPage;
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?= htmlspecialchars($title) ?> · Jumplearner Admin</title>
    <link rel="icon" type="image/gif" href="/favicon.gif">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.48.0/dist/tabler-icons.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/js/tabler.min.js" defer></script>
    <style>
        :root, [data-bs-theme="dark"] {
            --tblr-font-sans-serif: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --tblr-primary: #6d8bff;
            --tblr-primary-rgb: 109, 139, 255;
            --tblr-body-bg: #0b1220;
            --tblr-bg-surface: #111a2c;
            --tblr-bg-surface-secondary: #0e1626;
            --tblr-border-color: rgba(148, 163, 184, .14);
        }
        body { background: var(--tblr-body-bg); }
        .navbar-vertical { background: #0e1626 !important; border-right: 1px solid var(--tblr-border-color); }
        .navbar-vertical .navbar-brand { font-weight: 800; letter-spacing: -.01em; }
        .nav-section { font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #64748b; padding: 1rem 1rem .35rem; }
        .navbar-vertical .nav-link.active { background: rgba(109, 139, 255, .14); color: #c7d2fe; border-radius: 8px; }
        .navbar-vertical .nav-link { border-radius: 8px; margin: 1px .5rem; }
        .card { background: var(--tblr-bg-surface); border-color: var(--tblr-border-color); }
        .table { --tblr-table-bg: transparent; }
        .kpi-delta { font-size: .75rem; font-weight: 600; }
        .text-up { color: #4ade80; } .text-down { color: #f87171; }
        .page-body { margin-top: 1.25rem; }
        /* Older admin views still use inline grey/orange colours tuned for dark. */
        main.legacy table:not(.table) { width: 100%; }
    </style>
</head>
<body>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Menü">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a href="?page=admin-dashboard" class="navbar-brand navbar-brand-autodark py-lg-3">
                <span><span class="text-primary">jump</span>learner</span> <span class="badge bg-primary-lt ms-2">admin</span>
            </a>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-1">
                    <?php foreach ($navSections as $section => $items): ?>
                        <li class="nav-section"><?= htmlspecialchars($section) ?></li>
                        <?php foreach ($items as [$pageKey, $icon, $label]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activePage === $pageKey ? ' active' : '' ?>" href="?page=<?= $pageKey ?>">
                                <span class="nav-link-icon"><i class="ti ti-<?= $icon ?> fs-2"></i></span>
                                <span class="nav-link-title"><?= htmlspecialchars($label) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <li class="nav-section">Site</li>
                    <li class="nav-item">
                        <a class="nav-link" href="/" target="_blank" rel="noopener">
                            <span class="nav-link-icon"><i class="ti ti-external-link fs-2"></i></span>
                            <span class="nav-link-title">Siteyi aç</span>
                        </a>
                    </li>
                </ul>
                <div class="mt-auto p-3 border-top" style="border-color:var(--tblr-border-color)!important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-primary-lt"><i class="ti ti-user"></i></span>
                        <div class="flex-fill text-truncate" style="min-width:0;">
                            <div class="text-truncate small fw-semibold"><?= htmlspecialchars($adminRow['email'] ?? '') ?></div>
                            <div class="text-secondary" style="font-size:.7rem;"><?= $adminRole === 'admin' ? 'Tam yetkili' : 'Salt okunur' ?></div>
                        </div>
                        <a href="?page=admin-logout" class="btn btn-icon btn-ghost-secondary btn-sm" title="<?= htmlspecialchars(__('admin.logout')) ?>" aria-label="<?= htmlspecialchars(__('admin.logout')) ?>">
                            <i class="ti ti-logout"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <?php if (!empty($pageHeader)): ?>
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <?php if (!empty($pagePretitle)): ?><div class="page-pretitle"><?= htmlspecialchars($pagePretitle) ?></div><?php endif; ?>
                        <h2 class="page-title"><?= htmlspecialchars($pageHeader) ?></h2>
                    </div>
                    <?php if (!empty($pageActions)): ?><div class="col-auto ms-auto"><?= $pageActions ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="page-body">
            <main class="container-xl<?= empty($pageHeader) ? ' legacy' : '' ?>">
                <?php if (!empty($_SESSION['admin_flash'])): $flash = $_SESSION['admin_flash']; unset($_SESSION['admin_flash']); ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible d-flex align-items-center gap-2" role="alert">
                    <i class="ti ti-<?= $flash['type'] === 'success' ? 'circle-check' : 'alert-circle' ?> fs-2"></i>
                    <div><?= htmlspecialchars($flash['message']) ?></div>
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></a>
                </div>
                <?php endif; ?>
                <?php if (isset($content)) { echo $content; } ?>
            </main>
        </div>
    </div>
</div>
</body>
</html>
