<?php $pageTitle = __('terms.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">gavel</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('terms.heading') ?></h1>
        </div>
      </div>

      <!-- Terms Content -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <div>
          <p>
            <?= __('terms.intro') ?>
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s1_title') ?></h2>
          <p>
            <?= __('terms.s1_body') ?>
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s2_title') ?></h2>
          <p class="mb-2"><?= __('terms.s2_intro') ?></p>
          <ul class="list-disc list-inside space-y-1 ml-4">
            <li><?= __('terms.s2_1') ?></li>
            <li><?= __('terms.s2_2') ?></li>
            <li><?= __('terms.s2_3') ?></li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s3_title') ?></h2>
          <p>
            <?= __('terms.s3_body') ?>
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s4_title') ?></h2>
          <p>
            <?= __('terms.s4_body') ?>
          </p>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
