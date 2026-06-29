<?php $pageTitle = __('about.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">info</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('about.heading') ?></h1>
          <p class="text-body-md text-primary font-medium tracking-wider uppercase"><?= __('about.tagline') ?></p>
        </div>
      </div>

      <!-- Content sections -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-8">
        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('about.mission_title') ?></h2>
          <p class="mb-4">
            <?= __('about.mission_1') ?>
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-8"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-4"><?= __('about.features_title') ?></h2>
          <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-surface-container-low/50 border border-outline-variant/10 rounded-xl p-5">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-primary">psychology</span>
                <h3 class="font-semibold text-on-surface text-body-lg"><?= __('about.feature_1_title') ?></h3>
              </div>
              <p class="text-body-md text-on-surface-variant">
                <?= __('about.feature_1_desc') ?>
              </p>
            </div>
            <div class="bg-surface-container-low/50 border border-outline-variant/10 rounded-xl p-5">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-tertiary">history_edu</span>
                <h3 class="font-semibold text-on-surface text-body-lg"><?= __('about.feature_2_title') ?></h3>
              </div>
              <p class="text-body-md text-on-surface-variant">
                <?= __('about.feature_2_desc') ?>
              </p>
            </div>
            <div class="bg-surface-container-low/50 border border-outline-variant/10 rounded-xl p-5">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-primary">bookmark</span>
                <h3 class="font-semibold text-on-surface text-body-lg"><?= __('about.feature_3_title') ?></h3>
              </div>
              <p class="text-body-md text-on-surface-variant">
                <?= __('about.feature_3_desc') ?>
              </p>
            </div>
            <div class="bg-surface-container-low/50 border border-outline-variant/10 rounded-xl p-5">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-tertiary">bolt</span>
                <h3 class="font-semibold text-on-surface text-body-lg"><?= __('about.feature_4_title') ?></h3>
              </div>
              <p class="text-body-md text-on-surface-variant">
                <?= __('about.feature_4_desc') ?>
              </p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
