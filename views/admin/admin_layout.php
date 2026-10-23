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
    t('admin.nav_general') => [
        ['admin-dashboard', 'layout-dashboard', t('admin.dashboard')],
    ],
    t('admin.users') => [
        ['admin-users', 'users', t('admin.users')],
        ['admin-conversations', 'messages', t('admin.conversations')],
        ['admin-reports', 'flag', t('admin.nav_reports')],
    ],
    t('admin.nav_revenue') => [
        ['admin-payments', 'credit-card', t('admin.nav_payments')],
        ['admin-activity', 'activity', t('admin.nav_activity')],
    ],
    t('admin.nav_content') => [
        ['admin-languages', 'language', t('admin.nav_languages')],
        ['admin-lexicon', 'vocabulary', t('admin.nav_lexicon')],
    ],
    t('admin.nav_system') => [
        ['admin-health', 'heart-rate-monitor', t('admin.nav_health')],
        ['admin-ai-usage', 'sparkles', t('admin.nav_ai_usage')],
        ['admin-audit', 'list-details', t('admin.nav_audit')],
        ['admin-admins', 'shield-lock', t('admin.admins')],
        ['admin-settings', 'adjustments', t('admin.nav_settings')],
    ],
];
// Detail pages highlight their parent list.
$activeAlias = ['admin-conversation' => 'admin-conversations', 'admin-user' => 'admin-users', 'admin-language-strings' => 'admin-languages', 'admin-lexicon-entry' => 'admin-lexicon'];
$adminUiLang = \App\Src\Language::currentLang();
$adminBack = '?' . http_build_query($_GET);
$activePage = $activeAlias[$currentPage] ?? $currentPage;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($adminUiLang) ?>" dir="<?= \App\Src\Language::dir() ?>" data-bs-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?= htmlspecialchars($title) ?> · Jumplearner Admin</title>
    <link rel="icon" href="/favicon.ico?v=3" sizes="any">
    <link rel="stylesheet" href="/css/brand.css?v=1">
    <script>try { if (localStorage.getItem('jl_admin_sidebar') === 'collapsed') document.documentElement.classList.add('jl-sb-collapsed'); } catch (e) {}</script>
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
        /* Brand row: emblem, wordmark and a small ADMIN tag that always fit. */
        .jl-brand-row { display: flex; align-items: center; gap: .5rem; min-width: 0; padding: 0 .35rem 0 .6rem; }
        html.jl-sb-collapsed .jl-brand-row { padding: 0; }
        .navbar-vertical .navbar-brand.jl-brand { display: flex; align-items: center; gap: .55rem; min-width: 0; margin: 0; padding: .9rem 0; font-weight: 800; letter-spacing: -.01em; font-size: 1.08rem; white-space: nowrap; }
        .jl-brand .jl-emblem { height: 28px; flex: none; }
        .jl-brand-text { overflow: hidden; text-overflow: ellipsis; }
        .jl-admin-badge { flex: none; font-size: .58rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; padding: .22rem .42rem; border-radius: 6px;
            color: #c7d2fe; background: rgba(109, 139, 255, .16); border: 1px solid rgba(109, 139, 255, .3); }
        .jl-sb-toggle { margin-left: auto; flex: none; width: 30px; height: 30px; border: 0; border-radius: 8px; background: transparent; color: #94a3b8;
            display: inline-flex; align-items: center; justify-content: center; transition: background .15s, color .15s; }
        .jl-sb-toggle:hover, .jl-sb-toggle:focus-visible { background: rgba(148, 163, 184, .12); color: #e2e8f0; }
        .jl-sb-toggle i { font-size: 1.15rem; transition: transform .2s; }
        /* Thin scrollbar that matches the dark sidebar. */
        .navbar-vertical, .navbar-vertical .navbar-collapse { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, .22) transparent; }
        .navbar-vertical ::-webkit-scrollbar, .navbar-vertical::-webkit-scrollbar { width: 6px; }
        .navbar-vertical ::-webkit-scrollbar-track, .navbar-vertical::-webkit-scrollbar-track { background: transparent; }
        .navbar-vertical ::-webkit-scrollbar-thumb, .navbar-vertical::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, .2); border-radius: 6px; }
        .navbar-vertical ::-webkit-scrollbar-thumb:hover, .navbar-vertical::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, .38); }
        @media (min-width: 992px) {
            .navbar-vertical.navbar-expand-lg { transition: width .2s ease; overflow-x: hidden; }
            .navbar-vertical.navbar-expand-lg ~ .page-wrapper { transition: margin-left .2s ease; }
            /* Collapsed: icons only, names as tooltips. */
            html.jl-sb-collapsed .navbar-vertical.navbar-expand-lg { width: 4.5rem; }
            html.jl-sb-collapsed .navbar-vertical.navbar-expand-lg ~ .page-wrapper { margin-left: 4.5rem; }
            html.jl-sb-collapsed .jl-brand-row { flex-direction: column; gap: .25rem; padding-top: .6rem; }
            html.jl-sb-collapsed .navbar-vertical .navbar-brand.jl-brand { padding: .3rem 0; }
            html.jl-sb-collapsed .jl-brand-text, html.jl-sb-collapsed .jl-admin-badge,
            html.jl-sb-collapsed .navbar-vertical .nav-link-title, html.jl-sb-collapsed .jl-sb-footer-detail { display: none !important; }
            html.jl-sb-collapsed .jl-sb-toggle { margin: 0 auto .4rem; }
            html.jl-sb-collapsed .jl-sb-toggle i { transform: rotate(180deg); }
            html.jl-sb-collapsed .nav-section { font-size: 0; padding: .6rem 1rem .3rem; }
            html.jl-sb-collapsed .nav-section::after { content: ''; display: block; height: 1px; background: var(--tblr-border-color); }
            html.jl-sb-collapsed .navbar-vertical .nav-link { justify-content: center; padding-left: 0; padding-right: 0; }
            html.jl-sb-collapsed .navbar-vertical .nav-link-icon { margin: 0; }
            html.jl-sb-collapsed .jl-sb-footer { padding: .6rem .3rem !important; }
            html.jl-sb-collapsed .jl-sb-footer .jl-sb-account { flex-direction: column; }
        }
        .nav-section { font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #64748b; padding: 1rem 1rem .35rem; }
        .navbar-vertical .nav-link.active { background: rgba(109, 139, 255, .14); color: #c7d2fe; border-radius: 8px; }
        .navbar-vertical .nav-link { border-radius: 8px; margin: 1px .5rem; }
        .card { background: var(--tblr-bg-surface); border-color: var(--tblr-border-color); }
        .table { --tblr-table-bg: transparent; }
        .kpi-delta { font-size: .75rem; font-weight: 600; }
        .text-up { color: #4ade80; } .text-down { color: #f87171; }
        .page-body { margin-top: 1.25rem; }
        /* Never let one long word / URL / id push the page wider than the phone. */
        .page-wrapper { min-width: 0; }
        main.container-xl { overflow-wrap: break-word; }
        /* Older admin views still use inline grey/orange colours tuned for dark. */
        main.legacy table:not(.table) { width: 100%; }
    </style>
</head>
<body>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="<?= htmlspecialchars(t('admin.menu')) ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="jl-brand-row">
                <a href="?page=admin-dashboard" class="navbar-brand navbar-brand-autodark jl-brand" title="Jumplearner admin">
                    <img src="/brand/emblem.svg?v=2" alt="" width="26" height="28" class="jl-emblem"><span class="jl-brand-text"><span class="text-primary">jump</span>learner</span><span class="jl-admin-badge">admin</span>
                </a>
                <button type="button" class="jl-sb-toggle d-none d-lg-inline-flex" id="jl-sb-toggle" aria-controls="sidebar-menu" aria-expanded="true"
                    aria-label="<?= htmlspecialchars(t('admin.collapse_menu')) ?>" title="<?= htmlspecialchars(t('admin.collapse_menu')) ?>">
                    <i class="ti ti-layout-sidebar-left-collapse"></i>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-1">
                    <?php foreach ($navSections as $section => $items): ?>
                        <li class="nav-section"><?= htmlspecialchars($section) ?></li>
                        <?php foreach ($items as [$pageKey, $icon, $label]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activePage === $pageKey ? ' active' : '' ?>" href="?page=<?= $pageKey ?>" title="<?= htmlspecialchars($label) ?>">
                                <span class="nav-link-icon"><i class="ti ti-<?= $icon ?> fs-2"></i></span>
                                <span class="nav-link-title"><?= htmlspecialchars($label) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <li class="nav-section"><?= htmlspecialchars(t('admin.nav_site')) ?></li>
                    <li class="nav-item">
                        <a class="nav-link" href="/" target="_blank" rel="noopener" title="<?= htmlspecialchars(t('admin.open_site')) ?>">
                            <span class="nav-link-icon"><i class="ti ti-external-link fs-2"></i></span>
                            <span class="nav-link-title"><?= htmlspecialchars(t('admin.open_site')) ?></span>
                        </a>
                    </li>
                </ul>
                <div class="mt-auto p-3 border-top jl-sb-footer" style="border-color:var(--tblr-border-color)!important;">
                    <label class="d-flex align-items-center gap-2 mb-2 small text-secondary jl-sb-footer-detail">
                        <i class="ti ti-language"></i>
                        <span class="visually-hidden"><?= htmlspecialchars(t('admin.ui_language')) ?></span>
                        <select class="form-select form-select-sm" aria-label="<?= htmlspecialchars(t('admin.ui_language')) ?>"
                            onchange="location.href='?page=admin-lang&lang='+encodeURIComponent(this.value)+'&back='+encodeURIComponent(<?= htmlspecialchars(json_encode($adminBack)) ?>)">
                            <?php foreach (\App\Src\Language::listed('ui', $adminUiLang) as $l): ?>
                            <option value="<?= $l ?>" <?= $l === $adminUiLang ? 'selected' : '' ?>><?= htmlspecialchars(\App\Src\Language::nativeName($l)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="d-flex align-items-center gap-2 jl-sb-account">
                        <span class="avatar avatar-sm bg-primary-lt jl-sb-footer-detail"><i class="ti ti-user"></i></span>
                        <div class="flex-fill text-truncate jl-sb-footer-detail" style="min-width:0;">
                            <div class="text-truncate small fw-semibold"><?= htmlspecialchars($adminRow['email'] ?? '') ?></div>
                            <div class="text-secondary" style="font-size:.7rem;"><?= htmlspecialchars($adminRole === 'admin' ? t('admin.role_full') : t('admin.role_readonly')) ?></div>
                        </div>
                        <a href="?page=admin-2fa" class="btn btn-icon btn-ghost-secondary btn-sm" title="<?= htmlspecialchars(t('admin.account_security')) ?>" aria-label="<?= htmlspecialchars(t('admin.account_security')) ?>">
                            <i class="ti ti-<?= !empty($adminRow['totp_enabled_at']) ? 'shield-check text-green' : 'shield-exclamation text-yellow' ?>"></i>
                        </a>
                        <a href="?page=admin-logout" class="btn btn-icon btn-ghost-secondary btn-sm" title="<?= htmlspecialchars(t('admin.logout')) ?>" aria-label="<?= htmlspecialchars(t('admin.logout')) ?>">
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
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="<?= htmlspecialchars(t('admin.close')) ?>"></a>
                </div>
                <?php endif; ?>
                <?php if (isset($content)) { echo $content; } ?>
            </main>
        </div>
    </div>
</div>
<script>
// Desktop sidebar: collapse to icons and remember it in this browser.
(function () {
    var btn = document.getElementById('jl-sb-toggle'), root = document.documentElement;
    if (!btn) return;
    function sync() {
        var collapsed = root.classList.contains('jl-sb-collapsed');
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        var label = collapsed ? <?= json_encode(t('admin.expand_menu')) ?> : <?= json_encode(t('admin.collapse_menu')) ?>;
        btn.setAttribute('aria-label', label); btn.title = label;
    }
    btn.addEventListener('click', function () {
        root.classList.toggle('jl-sb-collapsed');
        try { localStorage.setItem('jl_admin_sidebar', root.classList.contains('jl-sb-collapsed') ? 'collapsed' : 'open'); } catch (e) {}
        sync();
    });
    sync();
})();
</script>
</body>
</html>
