<?php
// Standalone admin sign-in page (no sidebar). Rendered by AdminController::showLogin().
$h = fn($v) => htmlspecialchars((string)$v);
?>
<!DOCTYPE html>
<html lang="<?= $h(\App\Src\Language::currentLang()) ?>" dir="<?= \App\Src\Language::dir() ?>" data-bs-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?= $h(t('admin.signin')) ?> · Jumplearner Admin</title>
    <link rel="icon" type="image/gif" href="/favicon.gif">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.48.0/dist/tabler-icons.min.css" rel="stylesheet" />
    <style>
        :root, [data-bs-theme="dark"] {
            --tblr-font-sans-serif: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --tblr-primary: #6d8bff;
            --tblr-primary-rgb: 109, 139, 255;
            --tblr-bg-surface: #111a2c;
            --tblr-border-color: rgba(148, 163, 184, .16);
        }
        body {
            min-height: 100vh;
            background:
                radial-gradient(600px 400px at 20% 15%, rgba(109, 139, 255, .16), transparent 70%),
                radial-gradient(500px 380px at 85% 85%, rgba(56, 189, 248, .10), transparent 70%),
                #0b1220;
        }
        .login-card { background: rgba(17, 26, 44, .85); backdrop-filter: blur(12px); border: 1px solid var(--tblr-border-color); border-radius: 16px; }
        .brand { font-weight: 800; font-size: 1.6rem; letter-spacing: -.02em; }
    </style>
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4" style="max-width: 26rem;">
        <div class="text-center mb-4">
            <div class="brand"><span class="text-primary">jump</span>learner</div>
            <div class="text-secondary small mt-1"><?= $h(t('admin.panel')) ?></div>
        </div>
        <div class="card login-card shadow-lg">
            <div class="card-body p-4 p-sm-5">
                <?php $is2fa = ($step ?? '') === '2fa'; ?>
                <h2 class="h3 text-center mb-<?= $is2fa ? '2' : '4' ?>"><?= $h($is2fa ? t('admin.verification_code') : t('admin.admin_signin')) ?></h2>
                <?php if ($is2fa): ?>
                    <p class="text-secondary text-center small mb-4"><?= $h(t('admin.enter_2fa_code', ['email' => $pendingEmail])) ?></p>
                <?php endif; ?>
                <?php if ($loginError !== ''): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                        <i class="ti ti-alert-circle fs-2"></i><div><?= htmlspecialchars($loginError) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($is2fa): ?>
                <form method="POST" action="?page=admin-login-2fa" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-4">
                        <input type="text" name="code" class="form-control form-control-lg text-center" inputmode="numeric" autocomplete="one-time-code"
                               placeholder="123 456" maxlength="12" required autofocus style="letter-spacing:.3em;font-size:1.4rem;">
                        <div class="form-hint text-center mt-2"><?= $h(t('admin.backup_code_hint')) ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="ti ti-shield-check me-1"></i> <?= $h(t('admin.verify')) ?></button>
                </form>
                <div class="text-center mt-3"><a href="?page=admin-logout" class="small link-secondary"><?= $h(t('admin.use_other_account')) ?></a></div>
                <?php else: ?>
                <form method="POST" action="?page=admin-login" autocomplete="on" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email"><?= $h(t('admin.email')) ?></label>
                        <div class="input-icon">
                            <span class="input-icon-addon"><i class="ti ti-mail"></i></span>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password"><?= $h(t('admin.password')) ?></label>
                        <div class="input-group input-group-flat">
                            <span class="input-group-text"><i class="ti ti-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                            <span class="input-group-text">
                                <button type="button" class="btn btn-link link-secondary p-0" id="toggle-pw" aria-label="<?= $h(t('admin.show_password')) ?>" title="<?= $h(t('admin.show_password')) ?>">
                                    <i class="ti ti-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="ti ti-login-2 me-1"></i> <?= $h(t('admin.signin_btn')) ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center text-secondary small mt-3">
            <a href="/" class="link-secondary"><i class="ti ti-arrow-left"></i> <?= $h(t('admin.back_to_site')) ?></a>
        </div>
    </div>
</div>
<script>
var pwToggle = document.getElementById('toggle-pw');
if (pwToggle) pwToggle.addEventListener('click', function () {
    var input = document.getElementById('password');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    this.querySelector('i').className = show ? 'ti ti-eye-off' : 'ti ti-eye';
});
</script>
</body>
</html>
