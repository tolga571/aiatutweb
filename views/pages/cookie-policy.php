<?php $pageTitle = __('cookie.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">cookie</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('cookie.heading') ?></h1>
        </div>
      </div>

      <!-- Cookie Content -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <div>
          <p>
            Our website uses cookies to enhance your user experience, analyze traffic, and personalize content. By continuing to browse our website, you agree to our use of cookies.
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('cookie.s1_title') ?></h2>
          <p>
            Cookies are small text files that are stored on your computer or device when you visit a website. They help the site remember your preferences, active login session, and details for a smoother experience.
          </p>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('cookie.s2_title') ?></h2>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li><strong>Essential Cookies:</strong> Required to keep you logged in to your account and store temporary session settings.</li>
            <li><strong>Preference Cookies:</strong> Used to remember your learning choices, native and target languages, and level.</li>
            <li><strong>Third-Party Cookies:</strong> Set by components like Paddle to handle payment checkouts and secure transaction validation.</li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('cookie.s3_title') ?></h2>
          <p>
            You can restrict or block cookies using your browser settings. However, disabling essential cookies may prevent you from logging in or using the interactive tutoring features of AiTut.
          </p>
          <p class="mt-2">
            For detailed information about how we protect your personal data, please refer to our <a href="?page=privacy-policy" class="text-primary hover:underline font-medium">Privacy Policy</a>.
          </p>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
