<?php $pageTitle = 'Register – AiTut'; ?>
<?php require __DIR__ . '/partials/head.php'; ?>

<div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/10 blur-[100px] rounded-full pointer-events-none"></div>
  <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-tertiary/10 blur-[100px] rounded-full pointer-events-none"></div>

  <div class="relative z-10 w-full max-w-md">
    <div class="text-center mb-8">
      <a href="?page=home" class="inline-flex items-center gap-2 font-bold text-xl mb-6">
        <div class="flex flex-col items-start">
          <h1 class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</h1>
          <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
        </div>
      </a>
      <h1 class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface mb-2"><?= __('auth.create_account') ?></h1>
      <p class="text-body-md text-on-surface-variant"><?= __('auth.start_learning') ?></p>
    </div>

    <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-8">
      <?php if (!empty($registerError)): ?>
        <div class="bg-error-container/30 border border-error/30 text-error rounded-xl px-4 py-3 mb-5 text-body-md">
          <?= htmlspecialchars($registerError) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="?page=register" class="space-y-4">
        <div>
          <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.full_name') ?></label>
          <input type="text" name="name" autocomplete="name"
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
            placeholder="John Doe" />
        </div>
        <div>
          <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.email') ?></label>
          <input type="email" name="email" required autocomplete="email"
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
            placeholder="you@example.com" />
        </div>
        <div>
          <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.password') ?></label>
          <input type="password" name="password" required minlength="8" autocomplete="new-password"
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
            placeholder="<?= __('auth.password_min') ?>" />
        </div>
        <button type="submit"
          class="w-full bg-primary text-on-primary font-semibold py-3 rounded-xl transition mt-2 hover:opacity-90">
          <?= __('auth.create_account_btn') ?>
        </button>
      </form>

      <p class="text-center text-body-md text-outline mt-6">
        <?= __('auth.has_account') ?>
        <a href="?page=login" class="text-primary hover:text-primary-fixed transition"><?= __('auth.sign_in_link') ?></a>
      </p>
    </div>
  </div>
</div>

</body>
</html>
