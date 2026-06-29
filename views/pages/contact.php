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

      <!-- Form -->
      <form method="POST" action="mailto:support@aitut.com" class="space-y-5">
        <div>
          <label for="name" class="block text-body-md text-on-surface-variant mb-1.5 font-medium"><?= __('contact.name_label') ?></label>
          <input type="text" id="name" name="name" required 
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
            placeholder="<?= __('contact.name_placeholder') ?>" />
        </div>

        <div>
          <label for="email" class="block text-body-md text-on-surface-variant mb-1.5 font-medium"><?= __('contact.email_label') ?></label>
          <input type="email" id="email" name="email" required 
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
            placeholder="<?= __('contact.email_placeholder') ?>" />
        </div>

        <div>
          <label for="message" class="block text-body-md text-on-surface-variant mb-1.5 font-medium"><?= __('contact.message_label') ?></label>
          <textarea id="message" name="message" rows="5" required 
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition resize-none"
            placeholder="<?= __('contact.message_placeholder') ?>"></textarea>
        </div>

        <button type="submit" 
          class="w-full bg-primary text-on-primary font-semibold py-3.5 rounded-xl transition duration-300 hover:opacity-90 flex items-center justify-center gap-2 shadow-lg hover:shadow-primary/10">
          <span class="material-symbols-outlined text-[20px]">send</span>
          <span><?= __('contact.send_btn') ?></span>
        </button>
      </form>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
