<?php $pageTitle = __('privacy.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-4xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-12 border border-outline-variant/20 shadow-2xl backdrop-blur-md">
      
      <!-- Icon & Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">security</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('privacy.heading') ?></h1>
        </div>
      </div>

      <!-- Policy Content -->
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-6">
        <div>
          <p>
            At AiTut, we respect your privacy and are committed to protecting your personal data. This privacy policy explains how we collect, process, and protect your information when you use our service.
          </p>
        </div>

        <div class="border-t border-outline-variant/10 my-6"></div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('privacy.s1_title') ?></h2>
          <p class="mb-3">We collect information to provide a personalized language learning experience:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li><strong>Account Information:</strong> Name, email address, password, and registration date.</li>
            <li><strong>Learning Preferences:</strong> Native language, target language, proficiency level (CEFR), and learning goals.</li>
            <li><strong>Chat Logs:</strong> The text messages exchanged with our AI tutor to track your progress and generate feedback.</li>
            <li><strong>Payment & Billing:</strong> Transaction identifiers and plan status processed securely via our payment gateway (Paddle). We do not store raw credit card details.</li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('privacy.s2_title') ?></h2>
          <p class="mb-3">Your data is processed for the following purposes:</p>
          <ul class="list-disc list-inside space-y-2 ml-4">
            <li>To operate and maintain your personalized AI tutor sessions.</li>
            <li>To generate grammar corrections, vocabulary cards, and learning progress statistics.</li>
            <li>To manage your active plan and process payments securely via Paddle.</li>
            <li>To send you transactional notifications, security alerts, and support responses.</li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('privacy.s3_title') ?></h2>
          <p>
            We do not sell or rent your personal data to third parties. We share data only with trusted subprocessors necessary to deliver our services, such as:
          </p>
          <ul class="list-disc list-inside space-y-2 ml-4 mt-2">
            <li><strong>Google Gemini API:</strong> Message prompts are sent to Gemini to generate tutor replies (excluding your direct account credentials).</li>
            <li><strong>Paddle:</strong> Payment details are processed entirely by Paddle to handle subscriptions, billing, and compliance.</li>
          </ul>
        </div>

        <div>
          <h2 class="text-headline-sm text-on-surface font-semibold mb-3"><?= __('privacy.s4_title') ?></h2>
          <p>
            We deploy secure database procedures and encryption to safeguard your information. Our service adheres to the General Data Protection Regulation (GDPR) standards. You have the right to request access, correction, or deletion of your personal data at any time by contacting support.
          </p>
        </div>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
