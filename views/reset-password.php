<?php $pageTitle = 'Reset Password – AiTut'; ?>
<?php require __DIR__ . '/partials/head.php'; ?>

<div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/10 blur-[100px] rounded-full pointer-events-none"></div>
  <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-tertiary/10 blur-[100px] rounded-full pointer-events-none"></div>

  <div class="relative z-10 w-full max-w-md">
    <div class="text-center mb-8">
      <a href="?page=home" class="inline-flex items-center gap-2 font-bold text-xl mb-6">
        <div class="flex flex-col items-start">
          <p class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</p>
          <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
        </div>
      </a>
      <h1 class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface mb-2"><?= __('auth.reset_password_heading') ?></h1>
    </div>

    <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-8">
      <?php if (!$resetUserId): ?>
        <div class="bg-error-container/30 border border-error/30 text-error rounded-xl px-4 py-3 mb-5 text-body-md">
          <?= __('auth.reset_invalid_token') ?>
        </div>
        <p class="text-center text-body-md text-outline mt-2">
          <a href="?page=forgot-password" class="text-primary hover:text-primary-fixed transition"><?= __('auth.forgot_password') ?></a>
        </p>
      <?php else: ?>
        <?php if (!empty($resetError)): ?>
          <div class="bg-error-container/30 border border-error/30 text-error rounded-xl px-4 py-3 mb-5 text-body-md">
            <?= htmlspecialchars($resetError) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="?page=reset-password" class="space-y-4" id="reset-form">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken) ?>" />
          <div>
            <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.new_password') ?></label>
            <input type="password" name="password" required autocomplete="new-password"
              class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
              placeholder="<?= __('auth.password_min') ?>" />
          </div>
          <div>
            <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.confirm_new_password') ?></label>
            <input type="password" name="password_confirm" required autocomplete="new-password"
              class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
              placeholder="<?= __('auth.password_min') ?>" />
          </div>
          <button type="submit" id="reset-submit-btn"
            class="w-full bg-primary text-on-primary font-semibold py-3 rounded-xl transition mt-2 hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">
            <?= __('auth.reset_password_btn') ?>
          </button>
        </form>
        <script>
          document.getElementById('reset-form')?.addEventListener('submit', function () {
            var btn = document.getElementById('reset-submit-btn');
            if (btn) { btn.disabled = true; }
          });
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
