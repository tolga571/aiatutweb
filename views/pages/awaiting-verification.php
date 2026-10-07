<?php $pageTitle = __('auth.verify_email_subject'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col items-center justify-center p-6 bg-radial-gradient">
  <div class="max-w-md w-full bg-surface-container rounded-3xl p-8 border border-outline-variant/30 text-center" data-m="rise">
    <span class="material-symbols-outlined text-primary text-6xl mb-4">mail</span>
    <h1 class="text-headline-sm font-semibold text-on-surface mb-3"><?= __('auth.verify_email_subject') ?></h1>
    
    <?php if (isset($_SESSION['resend_success'])): ?>
        <div class="bg-primary/10 border border-primary/30 text-primary rounded-xl px-4 py-3 mb-5 text-body-md">
            If an account exists, a new verification link has been sent.
        </div>
        <?php unset($_SESSION['resend_success']); ?>
    <?php endif; ?>

    <p class="text-body-md text-on-surface-variant mb-8">
      <?= __('auth.verify_email_body') ?>
    </p>
    
    <div class="space-y-4">
        <a href="?page=login" class="inline-block bg-primary/10 text-primary px-6 py-2 rounded-full font-medium hover:bg-primary/20 transition-colors w-full">
          <?= __('auth.sign_in') ?>
        </a>
        
        <form action="?page=resend-verification" method="POST">
            <input type="hidden" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? '') ?>">
            <button type="button" onclick="const email = prompt('Enter your email to resend verification link:'); if(email) { this.form.email.value = email; this.form.submit(); }" class="text-primary font-medium text-sm hover:underline">
                Didn't receive it? Resend
            </button>
        </form>
    </div>
  </div>
</main>
