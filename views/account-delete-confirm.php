<?php $pageTitle = __('account.delete_heading'); ?>
<?php require __DIR__ . '/partials/head.php'; ?>

<div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-error/10 blur-[100px] rounded-full pointer-events-none"></div>

  <div class="relative z-10 w-full max-w-md">
    <div class="text-center mb-8">
      <a href="?page=home" class="inline-flex items-center gap-2 font-bold text-xl mb-6">
        <div class="flex flex-col items-start">
          <p class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</p>
          <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
        </div>
      </a>
      <h1 class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface mb-2"><?= __('account.delete_heading') ?></h1>
    </div>

    <div class="bg-surface-container border border-error/30 rounded-2xl p-8">
      <div class="bg-error-container/30 border border-error/30 text-error rounded-xl px-4 py-3 mb-5 text-body-md">
        <?= __('account.delete_warning') ?>
      </div>

      <?php if (!empty($accountDeleteError)): ?>
        <div class="bg-error-container/30 border border-error/30 text-error rounded-xl px-4 py-3 mb-5 text-body-md">
          <?= htmlspecialchars($accountDeleteError) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="?page=account-delete" class="space-y-4" id="delete-account-form">
        <?= csrf_field() ?>
        <div>
          <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('account.delete_password_label') ?></label>
          <input type="password" name="password" required autocomplete="current-password"
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-error transition"
            placeholder="••••••••" />
        </div>
        <button type="submit" id="delete-account-btn"
          class="w-full bg-error text-on-error font-semibold py-3 rounded-xl transition mt-2 hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">
          <?= __('account.delete_confirm_btn') ?>
        </button>
      </form>
      <script>
        document.getElementById('delete-account-form')?.addEventListener('submit', function () {
          var btn = document.getElementById('delete-account-btn');
          if (btn) { btn.disabled = true; }
        });
      </script>

      <p class="text-center text-body-md text-outline mt-6">
        <a href="?page=dashboard" class="text-primary hover:text-primary-fixed transition"><?= __('account.cancel_delete') ?></a>
      </p>
    </div>
  </div>
</div>

</body>
</html>
