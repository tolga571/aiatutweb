<?php $pageTitle = __('license.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">contract</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('license.heading') ?></h1>
        </div>
      </div>

      <!-- License Content -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <div>
          <p>
            This End User License Agreement governs your use of the AiTut application and services. By accessing the service, you agree to these terms.
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('license.s1_title') ?></h2>
          <p>
            AiTut grants you a personal, non-transferable, non-exclusive, revocable license to access and use the service for individual, personal language learning purposes only.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('license.s2_title') ?></h2>
          <p>
            This license does not grant rights to redistribute, copy, sublicense, lease, translate, modify, or commercially exploit the service or any part of the website layout and tutoring model outputs.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('license.s3_title') ?></h2>
          <p>
            This license is active until terminated. It will automatically terminate if you fail to comply with any of the terms outlined in this agreement or in our general Terms & Conditions.
          </p>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
