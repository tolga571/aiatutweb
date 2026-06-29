<?php $pageTitle = __('terms.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">gavel</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('terms.heading') ?></h1>
        </div>
      </div>

      <!-- Terms Content -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <div>
          <p>
            Welcome to AiTut. By accessing or using our website and services, you agree to comply with and be bound by the following terms and conditions.
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s1_title') ?></h2>
          <p>
            By using AiTut, you agree to use the service for personal, non-commercial language learning purposes only. You may not share access to your account or use the service for third-party instruction without explicit permission.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s2_title') ?></h2>
          <p class="mb-2">You may not attempt to:</p>
          <ul class="list-disc list-inside space-y-1 ml-4">
            <li>Reverse-engineer, decompile, or copy the codebase or underlying AI mechanisms of AiTut.</li>
            <li>Scrape, automate data retrieval, or systematically download chat histories or vocabulary logs.</li>
            <li>Misuse or overload the AI endpoints or network protocols in a way that degrades performance.</li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s3_title') ?></h2>
          <p>
            AiTut reserves the right to suspend or terminate accounts that violate these terms, attempt fraudulent payment activities, or act in a malicious manner towards the platform infrastructure.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('terms.s4_title') ?></h2>
          <p>
            We may revise these terms from time to time. The most current version will always be posted on our website. By continuing to use the service after changes become effective, you agree to be bound by the updated terms.
          </p>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
