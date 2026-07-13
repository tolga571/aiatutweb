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
          <?php if (is_array($registerError)): ?>
            <?php foreach ($registerError as $err): ?>
              <div class="flex items-start gap-2 <?= $err !== reset($registerError) ? 'mt-2' : '' ?>">
                <span class="material-symbols-outlined text-[16px] mt-0.5 shrink-0">error</span>
                <span><?= htmlspecialchars($err) ?></span>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <?= htmlspecialchars($registerError) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php $actionUrl = '?page=register' . (isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : ''); ?>
      <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>" class="space-y-4" id="register-form">
        <?= csrf_field() ?>
        <div>
          <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.full_name') ?></label>
          <input type="text" name="name" required autocomplete="name"
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
        <div>
          <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('auth.confirm_password') ?></label>
          <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"
            class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface placeholder-on-surface-variant/50 focus:outline-none focus:border-primary transition"
            placeholder="<?= __('auth.password_min') ?>" />
        </div>
        <div class="flex items-start gap-3 mt-4 mb-2">
          <input type="checkbox" name="terms" id="terms" required class="mt-1 shrink-0 w-4 h-4 rounded border-outline-variant/30 text-primary focus:ring-primary bg-surface-container-high">
          <label for="terms" class="text-xs text-on-surface-variant">
            <?= __('auth.terms_agreement') ?> <a href="?page=terms-and-conditions" class="text-primary hover:underline" target="_blank"><?= __('auth.terms') ?></a> <?= __('auth.and') ?> <a href="?page=privacy-policy" class="text-primary hover:underline" target="_blank"><?= __('auth.privacy') ?></a>.
          </label>
        </div>
        <button type="submit" id="register-submit-btn"
          class="w-full bg-primary text-on-primary font-semibold py-3 rounded-xl transition mt-2 hover:opacity-90 disabled:opacity-60 disabled:cursor-not-allowed">
          <?= __('auth.create_account_btn') ?>
        </button>
      </form>
      <script>
        document.getElementById('register-form')?.addEventListener('submit', function () {
          var btn = document.getElementById('register-submit-btn');
          if (btn) { btn.disabled = true; }
        });
      </script>

      <?php $googleClientId = $config['google_client_id'] ?? ''; ?>
      <?php if (!empty($googleClientId)): ?>
      <!-- Divider -->
      <div class="relative my-6 flex items-center justify-center">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-outline-variant/20"></div>
        </div>
        <span class="relative bg-surface-container px-3 text-[10px] text-outline uppercase font-bold tracking-wider">
          <?= __('auth.or_continue_with') ?>
        </span>
      </div>

      <!-- Real Google Sign-Up Button -->
      <div id="google-signin-container" class="flex justify-center w-full">
        <div id="g_id_onload"
             data-client_id="<?= htmlspecialchars($googleClientId) ?>"
             data-context="signup"
             data-ux_mode="popup"
             data-callback="handleCredentialResponse"
             data-auto_select="false"
             data-itp_support="true">
        </div>
        <div class="g_id_signin w-full"
             data-type="standard"
             data-shape="rectangular"
             data-theme="filled_blue"
             data-text="signup_with"
             data-size="large"
             data-logo_alignment="left"
             data-width="382">
        </div>
      </div>
      <?php endif; ?>

      <p class="text-center text-body-md text-outline mt-6">
        <?= __('auth.has_account') ?>
        <a href="?page=login<?= isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '' ?>" class="text-primary hover:text-primary-fixed transition"><?= __('auth.sign_in_link') ?></a>
      </p>
    </div>
  </div>
</div>

<?php if (!empty($googleClientId)): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function handleCredentialResponse(response) {
  if (response.credential) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?page=google-login<?= isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '' ?>';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'credential';
    input.value = response.credential;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }
}
</script>
<?php endif; ?>

</body>
</html>
