<?php
$title = 'AI kullanımı & maliyet';
$pageHeader = 'AI kullanımı & maliyet';
$pagePretitle = 'Gemini API · ücretli katman fiyatlarıyla, istek başına kayıt';
$e = fn($v) => htmlspecialchars((string)$v);
$usd = fn($v, $dec = 2) => '$' . number_format((float)$v, $dec, ',', '.');
$num = fn($v) => number_format((int)$v, 0, ',', '.');
$planLabels = ['trial' => 'Deneme', 'starter' => 'Starter', 'pro' => 'Pro', 'active' => 'Premium', 'inactive' => 'Pasif'];
$periodLabels = ['today' => 'Bugün', 'week' => 'Son 7 gün', 'month' => 'Bu ay'];

ob_start();
?>

<div class="row row-cards mb-4">
    <?php foreach ($periods as $key => $p):
        $okCount = (int)$p['requests'] - (int)$p['failed']; ?>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary"><?= $periodLabels[$key] ?></div>
            <div class="h1 mb-1"><?= $usd($p['cost']) ?></div>
            <div class="text-secondary" style="font-size:13px;">
                <?= $num($p['requests']) ?> istek<?= (int)$p['failed'] ? ' · <span style="color:#ff8a80;">' . $num($p['failed']) . ' hatalı</span>' : '' ?><br>
                <?= $okCount ? $usd((float)$p['cost'] / $okCount, 4) . ' / yanıt' : '—' ?>
            </div>
        </div></div>
    </div>
    <?php endforeach; ?>
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary">Ay sonu tahmini</div>
            <div class="h1 mb-1"><?= $usd($projectedMonthCost) ?></div>
            <div class="text-secondary" style="font-size:13px;"><?= (int)$daysInMonth ?> günün ilk <?= (int)$dayOfMonth ?> gününün hızıyla</div>
        </div></div>
    </div>
</div>

<h3>Plana göre — bu ay <span class="text-secondary small fw-normal">(gelir sadece gerçek Dodo abonelerinden)</span></h3>
<div class="table-responsive mb-4">
<table class="table table-hover table-striped">
    <thead><tr><th>Plan</th><th>Dönem</th><th>Gerçek abone</th><th>Tahmini aylık gelir</th><th>Kota / kişi</th><th>AI isteği</th><th>AI maliyeti</th><th>AI maliyeti / gelir</th></tr></thead>
    <tbody>
    <?php if (!$byPlan): ?><tr><td colspan="8" style="text-align:center;color:#9aa0a6;padding:20px;">Henüz plan veya kullanım yok.</td></tr><?php endif; ?>
    <?php foreach ($byPlan as $r):
        $interval = $r['billing_interval'] === 'year' ? 'year' : 'month';
        $revenue = ($prices[$r['plan']][$interval] ?? 0) * (int)$r['paying_users'];
        $share = $revenue > 0 ? (float)$r['cost'] / $revenue * 100 : null; ?>
        <tr>
            <td><?= $e($planLabels[$r['plan']] ?? $r['plan']) ?></td>
            <td><?= (int)$r['paying_users'] === 0 ? '—' : ($interval === 'year' ? 'Yıllık' : 'Aylık') ?></td>
            <td><?= $num($r['paying_users']) ?></td>
            <td><?= $revenue > 0 ? $usd($revenue) : '—' ?></td>
            <td><?= $num($quota->getBaseLimit((string)$r['plan'])) ?></td>
            <td><?= $num($r['requests']) ?></td>
            <td><?= $usd($r['cost']) ?></td>
            <td><?= $share === null ? '—' : '<span style="color:' . ($share > 40 ? '#ff8a80' : ($share > 25 ? '#ffd180' : '#b9f6ca')) . ';">' . number_format($share, 1) . '%</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="row">
    <div class="col-12 col-lg-6">
        <h3>Modele göre — bu ay</h3>
        <div class="table-responsive mb-4">
<table class="table table-hover table-striped">
            <thead><tr><th>Model</th><th>İstek</th><th>Giriş token</th><th>Çıkış token</th><th>Maliyet</th></tr></thead>
            <tbody>
            <?php if (!$byModel): ?><tr><td colspan="5" style="text-align:center;color:#9aa0a6;padding:20px;">Henüz istek yok.</td></tr><?php endif; ?>
            <?php foreach ($byModel as $r): ?>
                <tr><td><?= $e($r['model']) ?></td><td><?= $num($r['requests']) ?></td><td><?= $num($r['prompt_tokens']) ?></td><td><?= $num($r['output_tokens']) ?></td><td><?= $usd($r['cost']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
</div>
    </div>
    <div class="col-12 col-lg-6">
        <h3>Son 14 gün</h3>
        <div class="table-responsive mb-4">
<table class="table table-hover table-striped">
            <thead><tr><th>Gün</th><th>İstek</th><th>Hatalı</th><th>Maliyet</th></tr></thead>
            <tbody>
            <?php if (!$daily): ?><tr><td colspan="4" style="text-align:center;color:#9aa0a6;padding:20px;">Henüz istek yok.</td></tr><?php endif; ?>
            <?php foreach ($daily as $r): ?>
                <tr><td style="font-variant-numeric:tabular-nums;"><?= $e($r['day']) ?></td><td><?= $num($r['requests']) ?></td><td><?= (int)$r['failed'] ? '<span style="color:#ff8a80;">' . $num($r['failed']) . '</span>' : '0' ?></td><td><?= $usd($r['cost']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
</div>
    </div>
</div>

<h3>En çok AI maliyeti olan kullanıcılar — bu ay</h3>
<div class="table-responsive mb-4">
<table class="table table-hover table-striped">
    <thead><tr><th>Kullanıcı</th><th>Plan</th><th>İstek</th><th>Kota</th><th>Maliyet</th></tr></thead>
    <tbody>
    <?php if (!$topUsers): ?><tr><td colspan="5" style="text-align:center;color:#9aa0a6;padding:20px;">Henüz istek yok.</td></tr><?php endif; ?>
    <?php foreach ($topUsers as $r): ?>
        <tr>
            <td><a href="?page=admin-user&amp;id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?> <?= $e($r['email']) ?></a></td>
            <td><?= $e($planLabels[$r['plan_status']] ?? $r['plan_status']) ?></td>
            <td><?= $num($r['requests']) ?></td>
            <td><?= $num($quota->getBaseLimit((string)$r['plan_status'])) ?></td>
            <td><?= $usd($r['cost'], 3) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<p style="color:#9aa0a6;font-size:12px;">
    Kullanılan fiyatlar (1M token başına USD, giriş / çıkış, düşünme dahil):
    <?php foreach ($modelPrices as $m => $pr): ?>
        <?= $e($m) ?> <?= $usd($pr['in']) ?> / <?= $usd($pr['out']) ?>;
    <?php endforeach; ?>
    Gelir = liste fiyatı × şu anki gerçek abone sayısı, ödeme kesintisi öncesi.
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
