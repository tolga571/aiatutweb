<?php
$title = __('admin.admins');
$pageHeader = __('admin.admins');
$pagePretitle = count($admins) . ' admin hesabı';
$pageActions = '<a href="?page=admin-export&amp;type=admins" class="btn btn-outline-secondary"><i class="ti ti-download me-1"></i>CSV indir</a>';
$isFullAdmin = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);
$e = fn($v) => htmlspecialchars((string)$v);

ob_start();
?>
<?php if (!empty($adminsError)): ?>
    <div class="alert alert-danger"><?= $e($adminsError) ?></div>
<?php endif; ?>

<div class="row row-cards">
    <div class="<?= $isFullAdmin ? 'col-lg-8' : 'col-12' ?>">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Admin</th><th>Yetki</th><th>Eklendi</th><?php if ($isFullAdmin): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $a): ?>
                        <tr>
                            <td style="min-width:200px;">
                                <div class="fw-semibold"><?= $e($a['name'] ?: '—') ?><?= (int)$a['id'] === $currentAdminId ? ' <span class="badge bg-primary-lt">sen</span>' : '' ?></div>
                                <div class="text-secondary small text-break"><?= $e($a['email']) ?></div>
                            </td>
                            <td><span class="badge <?= $a['role'] === 'admin' ? 'bg-blue-lt' : 'bg-secondary-lt' ?>"><?= $a['role'] === 'admin' ? 'Tam yetkili' : 'Salt okunur' ?></span></td>
                            <td class="text-secondary text-nowrap"><?= date('d.m.Y', strtotime($a['created_at'])) ?></td>
                            <?php if ($isFullAdmin): ?>
                            <td class="text-end">
                                <?php if ((int)$a['id'] !== $currentAdminId): ?>
                                <form method="POST" action="?page=admin-delete-admin" onsubmit="return confirm('<?= $e(__('admin.confirm_remove')) ?>');" class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost-danger"><i class="ti ti-trash me-1"></i>Kaldır</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($isFullAdmin): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-user-plus me-1"></i>Admin ekle</h3></div>
            <div class="card-body">
                <form method="POST" action="?page=admin-create-admin">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <div class="mb-2"><label class="form-label">E-posta</label><input type="email" name="email" required class="form-control" autocomplete="off"></div>
                    <div class="mb-2"><label class="form-label">İsim</label><input type="text" name="name" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Şifre <span class="text-secondary small">(en az 8 karakter)</span></label><input type="password" name="password" required minlength="8" class="form-control" autocomplete="new-password"></div>
                    <div class="mb-3">
                        <label class="form-label">Yetki</label>
                        <select name="role" class="form-select">
                            <option value="viewer">Salt okunur — her şeyi görür, değiştiremez</option>
                            <option value="admin">Tam yetkili</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Admin ekle</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
