<?php
$title = __('admin.dashboard');
ob_start();
?>
<div class="page">
  <div class="container-xl">
    <div class="row row-deck row-cards">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-sm">
          <div class="card-body">
            <h3 class="card-title"><?= __('admin.user_count') ?></h3>
            <div class="h1 mb-0"><?= htmlspecialchars($userCount ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-sm">
          <div class="card-body">
            <h3 class="card-title"><?= __('admin.paid_users') ?></h3>
            <div class="h1 mb-0"><?= htmlspecialchars($paidCount ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-sm">
          <div class="card-body">
            <h3 class="card-title"><?= __('admin.total_messages') ?></h3>
            <div class="h1 mb-0"><?= htmlspecialchars($msgCount ?? 0) ?></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-sm">
          <div class="card-body">
            <h3 class="card-title"><?= __('admin.revenue') ?></h3>
            <div class="h1 mb-0"><?= htmlspecialchars(number_format($revenue ?? 0, 2)) ?> TL</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/admin_layout.php';
?>
