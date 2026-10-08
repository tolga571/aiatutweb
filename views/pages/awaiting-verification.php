<?php
$pageTitle = __('auth.verify_email_subject');
// The address from sign-up (or from the last resend), kept in the session.
$pendingEmail = (string)($_SESSION['pending_verify_email'] ?? '');
$resent = !empty($_SESSION['resend_success']);
unset($_SESSION['resend_success']);
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col items-center justify-center p-6 bg-radial-gradient">
  <div class="max-w-md w-full bg-surface-container rounded-3xl p-8 border border-outline-variant/30 text-center" data-m="rise">
    <span class="material-symbols-outlined text-primary text-6xl mb-4" aria-hidden="true">mail</span>
    <h1 class="text-headline-sm font-semibold text-on-surface mb-3"><?= __('auth.verify_email_subject') ?></h1>
    <p class="text-body-md text-on-surface-variant mb-6">
      <?= htmlspecialchars($pendingEmail !== '' ? t('auth.check_email_sent', ['email' => $pendingEmail]) : __('auth.check_email_generic')) ?>
    </p>

    <a href="?page=login" class="inline-block bg-primary text-on-primary px-6 py-2.5 rounded-full font-medium hover:opacity-90 transition w-full">
      <?= __('auth.sign_in') ?>
    </a>

    <form action="?page=resend-verification" method="POST" class="mt-8 text-left space-y-3">
      <?= csrf_field() ?>
      <p class="text-on-surface font-semibold text-center"><?= __('auth.resend_question') ?></p>
      <label for="resend-email" class="block text-label-md text-on-surface-variant"><?= __('auth.email') ?></label>
      <input id="resend-email" type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($pendingEmail) ?>"
        class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-2.5 text-on-surface focus:outline-none focus:border-primary">
      <?php if ($resent): ?>
        <p class="text-body-md text-primary" role="status"><?= __('auth.resend_done') ?></p>
      <?php endif; ?>
      <button type="submit" class="w-full border border-outline-variant/40 text-on-surface px-6 py-2.5 rounded-full font-medium hover:bg-surface-container-high transition">
        <?= __('auth.resend_btn') ?>
      </button>
    </form>
  </div>
</main>

</body>
</html>
