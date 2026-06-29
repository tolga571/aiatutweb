<?php
$title = __('pricing.page_title');
ob_start();
?>
<h2 class="my-4"><?= __('pricing.heading') ?></h2>
<p class="text-muted"><?= __('pricing.subtitle') ?></p>
<table class="table table-hover table-striped">
    <thead class="table-dark">
        <tr>
            <th><?= __('admin.plan_col') ?></th>
            <th><?= __('pricing.starter_monthly') ?></th>
            <th><?= __('common.features') ?></th>
            <th><?= __('admin.purchase') ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?= __('pricing.starter_title') ?></td>
            <td>$9/mo</td>
            <td><?= __('pricing.starter_feature_3') ?></td>
            <td><a href="?page=register" class="btn btn-primary btn-sm"><?= __('pricing.starter_btn') ?></a></td>
        </tr>
        <tr>
            <td><?= __('pricing.premium_title') ?></td>
            <td>$19/mo</td>
            <td><?= __('pricing.premium_feature_4') ?></td>
            <td><a href="?page=register" class="btn btn-primary btn-sm"><?= __('pricing.premium_btn') ?></a></td>
        </tr>
        <tr>
            <td><?= __('pricing.pro_title') ?></td>
            <td>$29/mo</td>
            <td><?= __('pricing.pro_feature_5') ?></td>
            <td><a href="?page=register" class="btn btn-primary btn-sm"><?= __('pricing.pro_btn') ?></a></td>
        </tr>
    </tbody>
</table>
<?php
$content = ob_get_clean();
$content .= file_get_contents(__DIR__ . '/../partials/footer.php');
require __DIR__ . '/../admin/admin_layout.php';
?>
