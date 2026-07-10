<?php $pageTitle = __('home.page_title'); ?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<main
  class="flex flex-col items-center justify-center min-h-[calc(100vh-56px)] px-4 text-center relative overflow-hidden">
  <div class="absolute top-1/4 left-1/3 w-96 h-96 bg-primary/10 blur-[120px] rounded-full pointer-events-none"></div>
  <div class="absolute bottom-1/4 right-1/3 w-80 h-80 bg-tertiary/10 blur-[120px] rounded-full pointer-events-none">
  </div>

  <div class="relative z-10 max-w-2xl">
    <div
      class="inline-flex items-center gap-2 bg-primary/10 border border-primary/20 rounded-full px-4 py-1.5 text-primary text-sm mb-6">
      <span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span>
      <?= __('home.badge') ?>
    </div>

    <h1 class="font-headline-lg text-headline-lg text-on-surface mb-4">
      <?= __('home.heading') ?>
    </h1>

    <p class="text-body-lg text-on-surface-variant mb-8">
      <?= __('home.subtitle') ?>
    </p>

    <?php
    $isLoggedIn = isset($auth) && $auth->isLoggedIn();
    $hasPlan = false;
    if ($isLoggedIn) {
        $curr = $auth->currentUser();
        $hasPlan = (($curr['plan_status'] ?? 'inactive') !== 'inactive' || ($curr['has_paid'] ?? 0) == 1);
    }
    ?>
    <div class="flex items-center justify-center gap-4">
      <a href="<?= $isLoggedIn ? '?page=chat' : '?page=register' ?>"
        class="bg-primary text-on-primary font-semibold px-8 py-3 rounded-xl transition text-body-lg hover:opacity-90">
        <?= $hasPlan ? (__('home.continue_learning') ?? 'Continue Learning') : __('home.get_started') ?>
      </a>
      <a href="?page=blog"
        class="border border-outline-variant hover:border-outline text-on-surface-variant hover:text-on-surface font-semibold px-8 py-3 rounded-xl transition text-body-lg">
        <?= __('home.read_blog') ?>
      </a>
    </div>

    <div class="grid grid-cols-3 gap-4 mt-16">
      <div class="bg-surface-container-high border border-outline-variant/20 rounded-2xl p-5 text-left">
        <div class="text-2xl mb-3">
          <span class="material-symbols-outlined text-primary text-3xl">track_changes</span>
        </div>
        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= __('home.feature_1_title') ?></h3>
        <p class="text-body-md text-on-surface-variant"><?= __('home.feature_1_desc') ?></p>
      </div>
      <div class="bg-surface-container-high border border-outline-variant/20 rounded-2xl p-5 text-left">
        <div class="text-2xl mb-3">
          <span class="material-symbols-outlined text-tertiary text-3xl">edit_note</span>
        </div>
        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= __('home.feature_2_title') ?></h3>
        <p class="text-body-md text-on-surface-variant"><?= __('home.feature_2_desc') ?></p>
      </div>
      <div class="bg-surface-container-high border border-outline-variant/20 rounded-2xl p-5 text-left">
        <div class="text-2xl mb-3">
          <span class="material-symbols-outlined text-primary text-3xl">book</span>
        </div>
        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= __('home.feature_3_title') ?></h3>
        <p class="text-body-md text-on-surface-variant"><?= __('home.feature_3_desc') ?></p>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>

</html>
