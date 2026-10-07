<?php $pageTitle = __('auth.verify_email_subject'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col items-center justify-center p-6 bg-radial-gradient">
  <div class="max-w-md w-full bg-surface-container rounded-3xl p-8 border border-outline-variant/30 text-center" data-m="rise">
    <span class="material-symbols-outlined text-primary text-6xl mb-4">mail</span>
    <h1 class="text-headline-sm font-semibold text-on-surface mb-3"><?= __('auth.verify_email_subject') ?></h1>
    <p class="text-body-md text-on-surface-variant mb-8">
      <?= __('auth.verify_email_body') ?>
    </p>
    <a href="?page=login" class="bg-primary/10 text-primary px-6 py-2 rounded-full font-medium hover:bg-primary/20 transition-colors">
      <?= __('auth.sign_in') ?>
    </a>
  </div>
</main>
