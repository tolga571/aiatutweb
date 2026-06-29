<?php $pageTitle = __('faq.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-3xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-10 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">help_outline</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('faq.heading') ?></h1>
          <p class="text-body-md text-on-surface-variant"><?= __('faq.subtitle') ?></p>
        </div>
      </div>

      <!-- FAQ Accordion -->
      <div class="space-y-4">
        
        <details class="group bg-surface-container-low/40 border border-outline-variant/10 rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden">
          <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer select-none text-on-surface hover:bg-surface-variant/30 transition duration-200">
            <span class="font-medium text-body-lg"><?= __('faq.q1') ?></span>
            <span class="material-symbols-outlined transition duration-300 group-open:-rotate-180 text-primary">expand_more</span>
          </summary>
          <div class="px-5 pb-5 text-body-md text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-3">
            <p>
              <?= __('faq.a1_1') ?>
            </p>
          </div>
        </details>

        <details class="group bg-surface-container-low/40 border border-outline-variant/10 rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden">
          <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer select-none text-on-surface hover:bg-surface-variant/30 transition duration-200">
            <span class="font-medium text-body-lg"><?= __('faq.q2') ?></span>
            <span class="material-symbols-outlined transition duration-300 group-open:-rotate-180 text-primary">expand_more</span>
          </summary>
          <div class="px-5 pb-5 text-body-md text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-3">
            <p>
              <?= __('faq.a2_1') ?>
            </p>
          </div>
        </details>

        <details class="group bg-surface-container-low/40 border border-outline-variant/10 rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden">
          <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer select-none text-on-surface hover:bg-surface-variant/30 transition duration-200">
            <span class="font-medium text-body-lg"><?= __('faq.q3') ?></span>
            <span class="material-symbols-outlined transition duration-300 group-open:-rotate-180 text-primary">expand_more</span>
          </summary>
          <div class="px-5 pb-5 text-body-md text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-3">
            <p>
              <?= __('faq.a3_1') ?>
            </p>
          </div>
        </details>

        <details class="group bg-surface-container-low/40 border border-outline-variant/10 rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden">
          <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer select-none text-on-surface hover:bg-surface-variant/30 transition duration-200">
            <span class="font-medium text-body-lg"><?= __('faq.q4') ?></span>
            <span class="material-symbols-outlined transition duration-300 group-open:-rotate-180 text-primary">expand_more</span>
          </summary>
          <div class="px-5 pb-5 text-body-md text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-3">
            <p>
              <?= __('faq.a4_1') ?>
            </p>
          </div>
        </details>

        <details class="group bg-surface-container-low/40 border border-outline-variant/10 rounded-xl overflow-hidden [&_summary::-webkit-details-marker]:hidden">
          <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer select-none text-on-surface hover:bg-surface-variant/30 transition duration-200">
            <span class="font-medium text-body-lg"><?= __('faq.q5') ?></span>
            <span class="material-symbols-outlined transition duration-300 group-open:-rotate-180 text-primary">expand_more</span>
          </summary>
          <div class="px-5 pb-5 text-body-md text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-3">
            <p>
              <?= __('faq.a5_1') ?>
            </p>
          </div>
        </details>

      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
