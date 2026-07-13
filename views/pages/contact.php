<?php $pageTitle = __('contact.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-2xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-10 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-6">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">mail</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('contact.heading') ?></h1>
          <p class="text-body-md text-on-surface-variant"><?= __('contact.subtitle') ?></p>
        </div>
      </div>

      <div class="text-body-md text-on-surface-variant leading-relaxed mb-6">
        <p>
          <?= __('contact.intro') ?>
        </p>
      </div>

      <!-- Contact Link -->
      <div class="bg-surface-container border border-outline-variant/30 rounded-xl p-6 text-center">
        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center border border-primary/20 mx-auto mb-3">
          <span class="material-symbols-outlined text-primary text-2xl">alternate_email</span>
        </div>
        <h3 class="text-body-lg font-semibold text-on-surface mb-1"><?= __('contact.email_us') ?></h3>
        <p class="text-body-md text-on-surface-variant mb-4"><?= __('contact.email_desc') ?></p>
        <a href="mailto:support@aitut.com" class="inline-flex items-center justify-center gap-2 bg-primary text-on-primary font-semibold py-2.5 px-6 rounded-xl transition duration-300 hover:opacity-90 shadow-md">
          <span class="material-symbols-outlined text-[20px]">send</span>
          <span>support@aitut.com</span>
        </a>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
