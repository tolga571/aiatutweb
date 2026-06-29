<?php $pageTitle = __('refund.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">currency_exchange</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('refund.heading') ?></h1>
        </div>
      </div>

      <!-- Refund Content -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <div>
          <p>
            We want you to be fully satisfied with your language learning journey. This document outlines the refund conditions for our subscription plans.
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('refund.s1_title') ?></h2>
          <p>
            If you are unsatisfied with AiTut within <strong>14 days</strong> of your first payment, contact support for a full refund.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('refund.s2_title') ?></h2>
          <p>
            After 14 days, refunds are handled on a case-by-case basis depending on technical issues, account usage, and platform compliance.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('refund.s3_title') ?></h2>
          <p>
            To submit a request, please contact us via our <a href="?page=contact" class="text-primary hover:underline font-medium">Contact Page</a> or write an email to support@aitut.com containing your registration email address and purchase details.
          </p>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
