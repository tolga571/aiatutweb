<?php
$pageTitle = __('delacc.page_title');
// Public page (no sign-in needed): Google Play links here as the app's
// account-deletion URL. Button and tab names come from the real UI strings,
// so the steps always match what the user sees in their language.
$ui = static fn(string $key): string => htmlspecialchars(__($key));
$confirmStep = t('delacc.confirm_step');
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-8 sm:py-12 px-3 sm:px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-5 sm:p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">

      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-error/10 flex items-center justify-center border border-error/20">
          <span class="material-symbols-outlined text-error text-3xl" aria-hidden="true">person_remove</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('delacc.heading') ?></h1>
      </div>

      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <p><?= __('delacc.intro') ?></p>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('delacc.app_title') ?></h2>
          <ol class="list-decimal list-inside space-y-2 ml-4">
            <li><?= t('delacc.app_1', ['tab' => $ui('mobile.profile')]) ?></li>
            <li><?= t('delacc.tap_button', ['button' => $ui('account.delete_account_link')]) ?></li>
            <li><?= $confirmStep ?></li>
          </ol>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('delacc.web_title') ?></h2>
          <ol class="list-decimal list-inside space-y-2 ml-4">
            <li><?= t('delacc.web_1', ['dashboard' => $ui('nav.dashboard')]) ?></li>
            <li><?= t('delacc.web_2', ['section' => $ui('account.data_section_title'), 'button' => $ui('account.delete_account_link')]) ?></li>
            <li><?= $confirmStep ?></li>
          </ol>
          <a href="?page=account-delete-confirm" class="inline-flex items-center gap-2 mt-4 bg-error-container/30 text-error border border-error/30 font-semibold py-2 px-4 rounded-lg transition hover:bg-error-container/50 text-sm">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span><?= __('delacc.cta') ?>
          </a>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('delacc.email_title') ?></h2>
          <p><?= __('delacc.email_body') ?></p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('delacc.deleted_title') ?></h2>
          <p><?= __('delacc.deleted_body') ?></p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('delacc.kept_title') ?></h2>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li><?= __('delacc.kept_1') ?></li>
            <li><?= __('delacc.kept_2') ?></li>
            <li><?= __('delacc.kept_3') ?></li>
            <li><?= __('delacc.kept_4') ?></li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('delacc.subs_title') ?></h2>
          <p><?= __('delacc.subs_body') ?></p>
        </div>

        <p class="text-on-surface"><?= t('delacc.export', ['button' => $ui('account.download_data')]) ?></p>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
