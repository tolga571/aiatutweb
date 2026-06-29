<?php $pageTitle = __('pricing.page_title'); ?>
<?php require __DIR__ . '/partials/head.php'; ?>
<?php require __DIR__ . '/partials/navbar.php'; ?>

<?php
// Expose the config options
$paddleClientToken = $config['paddle_client_token'] ?? '';
$paddleEnvironment = $config['paddle_environment'] ?? 'sandbox';
$starterPriceId = $config['paddle_starter_price_id'] ?? '';
$proPriceId = $config['paddle_pro_price_id'] ?? '';
$premiumPriceId = $config['paddle_premium_price_id'] ?? '';
?>

<!-- Load Paddle.js -->
<script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>

<main class="flex-1 overflow-y-auto flex flex-col items-center pt-16 pb-12 px-6 bg-radial-gradient">
  <div class="max-w-4xl w-full">
    <div class="text-center mb-10">
      <h1 class="font-headline-lg text-headline-lg text-on-surface mb-2"><?= __('pricing.heading') ?></h1>
      <p class="text-body-lg text-on-surface-variant max-w-xl mx-auto"><?= __('pricing.subtitle') ?></p>
    </div>

    <?php if (empty($paddleClientToken)): ?>
      <!-- Configuration warning banner for the developer -->
      <div class="bg-error-container border border-error text-on-error-container p-4 rounded-xl mb-8 max-w-lg mx-auto text-left flex items-start gap-3">
        <span class="material-symbols-outlined text-error text-2xl">warning</span>
        <div>
          <h3 class="font-semibold text-body-lg mb-1"><?= __('pricing.paddle_pending_title') ?></h3>
          <p class="text-body-md opacity-90"><?= __('pricing.paddle_pending_body') ?></p>
        </div>
      </div>
    <?php endif; ?>

    <?php
    $isTrialUser = (($currentUser['plan_status'] ?? '') === 'trial');
    $isPaidUser = ($currentUser && !$isTrialUser && (($currentUser['plan_status'] ?? '') === 'active' || ($currentUser['has_paid'] ?? 0) == 1));
    $trialMessagesSent = $isTrialUser ? $auth->getTrialMessagesSent($currentUser['id']) : 0;
    ?>

    <?php if (!$isPaidUser): ?>
      <!-- Free Trial Promo Banner -->
      <div class="max-w-2xl mx-auto mb-10 p-6 rounded-2xl border border-primary/30 bg-surface-container-high/60 backdrop-blur-md shadow-lg relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-6 transition-all hover:border-primary/50">
        <!-- Glow effect -->
        <div class="absolute -right-16 -top-16 w-36 h-36 bg-primary/20 rounded-full blur-2xl pointer-events-none"></div>
        
        <div class="flex items-center gap-4 text-left">
          <div class="w-12 h-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
            <span class="material-symbols-outlined text-[28px] <?= $isTrialUser ? '' : 'animate-pulse' ?>">chat_bubble</span>
          </div>
          <div>
            <?php if ($isTrialUser): ?>
              <h3 class="text-lg font-bold text-on-surface"><?= __('pricing.trial_active_title') ?></h3>
              <p class="text-xs text-on-surface-variant"><?= sprintf(__('pricing.trial_active_desc'), max(0, 5 - $trialMessagesSent)) ?></p>
            <?php else: ?>
              <h3 class="text-lg font-bold text-on-surface"><?= __('pricing.trial_prompt_title') ?></h3>
              <p class="text-xs text-on-surface-variant"><?= __('pricing.trial_prompt_desc') ?></p>
            <?php endif; ?>
          </div>
        </div>
        
        <?php if ($isTrialUser): ?>
          <a href="?page=chat" class="w-full sm:w-auto bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold text-xs px-xl py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 shrink-0">
            <?= __('pricing.go_to_chat') ?>
            <span class="material-symbols-outlined text-[16px]">chat</span>
          </a>
        <?php else: ?>
          <a href="?page=start-trial" class="w-full sm:w-auto bg-primary text-on-primary hover:opacity-90 font-semibold text-xs px-xl py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 shrink-0 glow-hover">
            <?= __('pricing.start_trial') ?>
            <span class="material-symbols-outlined text-[16px]">play_arrow</span>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-8 max-w-5xl mx-auto items-stretch">
      <!-- Starter Plan Card -->
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] border border-outline/20 relative">
        <div>
          <div class="text-outline text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.starter_title') ?></div>
          <div class="text-4xl font-bold text-on-surface mb-1"><?= __('pricing.starter_monthly') ?></div>
          <p class="text-outline text-body-md mb-6"><?= __('pricing.starter_desc') ?></p>

          <div class="border-t border-outline/10 my-4"></div>

          <ul class="space-y-3 mb-8 text-body-md text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.starter_feature_1') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.starter_feature_2') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.starter_feature_3') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.starter_feature_4') ?>
            </li>
          </ul>
        </div>

        <div>
          <button onclick="openCheckout('<?= htmlspecialchars($starterPriceId) ?>')"
            class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
            <?= empty($paddleClientToken) ? 'disabled' : '' ?>>
            <?= __('pricing.starter_btn') ?>
            <span class="material-symbols-outlined">arrow_forward</span>
          </button>
        </div>
      </div>

      <!-- Pro Plan Card (Popular) -->
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] border border-primary/40 relative overflow-hidden">
        <div class="absolute top-0 right-0 bg-primary text-on-primary text-[10px] font-bold tracking-widest uppercase py-1 px-4 rounded-bl-xl font-label-md">
          <?= __('pricing.recommended') ?>
        </div>

        <div>
          <div class="text-primary text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.pro_title') ?></div>
          <div class="text-4xl font-bold text-on-surface mb-1"><?= __('pricing.pro_monthly') ?></div>
          <p class="text-outline text-body-md mb-6"><?= __('pricing.pro_desc') ?></p>

          <div class="border-t border-outline/10 my-4"></div>

          <ul class="space-y-3 mb-8 text-body-md text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.pro_feature_1') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.pro_feature_2') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.pro_feature_3') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.pro_feature_4') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg">check</span>
              <?= __('pricing.pro_feature_5') ?>
            </li>
          </ul>
        </div>

        <div>
          <button onclick="openCheckout('<?= htmlspecialchars($proPriceId) ?>')"
            class="w-full bg-primary text-on-primary hover:opacity-90 font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
            <?= empty($paddleClientToken) ? 'disabled' : '' ?>>
            <?= __('pricing.pro_btn') ?>
            <span class="material-symbols-outlined">bolt</span>
          </button>
        </div>
      </div>

      <!-- Premium Plan Card -->
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] border border-outline/20 relative">
        <div class="text-primary text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.premium_title') ?></div>
        <div class="text-4xl font-bold text-on-surface mb-1"><?= __('pricing.premium_monthly') ?></div>
        <p class="text-outline text-body-md mb-6"><?= __('pricing.premium_desc') ?></p>

        <div class="border-t border-outline/10 my-4"></div>

        <ul class="space-y-3 mb-8 text-body-md text-on-surface-variant">
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg">check</span> <?= __('pricing.premium_feature_1') ?></li>
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg">check</span> <?= __('pricing.premium_feature_2') ?></li>
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg">check</span> <?= __('pricing.premium_feature_3') ?></li>
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg">check</span> <?= __('pricing.premium_feature_4') ?></li>
        </ul>

        <div>
          <button onclick="openCheckout('<?= htmlspecialchars($premiumPriceId) ?>')"
            class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
            <?= empty($paddleClientToken) ? 'disabled' : '' ?>>
            <?= __('pricing.premium_btn') ?>
            <span class="material-symbols-outlined">star</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Payment processing modal -->
<div id="payment-loading-modal" class="fixed inset-0 bg-surface-container-lowest/80 backdrop-blur-md flex items-center justify-center z-[100] hidden">
  <div class="glass-panel max-w-sm w-full p-8 rounded-2xl border border-primary/30 text-center shadow-2xl">
    <div class="relative flex justify-center mb-6">
      <!-- Loading spinner with double ring glow -->
      <div class="animate-spin rounded-full h-12 w-12 border-4 border-primary border-t-transparent"></div>
    </div>
    <h3 class="font-headline-sm text-[20px] font-semibold text-on-surface mb-2"><?= __('pricing.payment_modal_title') ?></h3>
    <p class="text-body-md text-on-surface-variant mb-6"><?= __('pricing.payment_modal_body') ?></p>
  </div>
</div>

<script>
  // Initialize Paddle.js
  <?php if (!empty($paddleClientToken)): ?>
    try {
      if ("<?= $paddleEnvironment ?>" === "sandbox") {
        Paddle.Environment.set("sandbox");
      }
      Paddle.Initialize({
        token: "<?= htmlspecialchars($paddleClientToken) ?>",
        eventCallback: function(event) {
          if (event.name === 'checkout.completed') {
            handleCheckoutSuccess();
          }
        }
      });
    } catch (e) {
      console.error("Paddle initialization failed:", e);
    }
  <?php endif; ?>

  function handleCheckoutSuccess() {
    // Show the processing overlay
    const modal = document.getElementById('payment-loading-modal');
    if (modal) {
      modal.classList.remove('hidden');
    }

    // Start polling the server to verify webhook has completed the activation
    const interval = setInterval(function() {
      fetch('?page=check-payment-status')
        .then(response => response.json())
        .then(data => {
          if (data.paid) {
            clearInterval(interval);
            // Redirect straight to chat page!
            window.location.href = '?page=chat';
          }
        })
        .catch(error => {
          console.error("Error checking payment status:", error);
        });
    }, 1500); // check every 1.5 seconds
  }

  function openCheckout(priceId) {
    if (!priceId) {
      alert("<?= __('pricing.price_id_error') ?>");
      return;
    }

    try {
      Paddle.Checkout.open({
        items: [{
          priceId: priceId,
          quantity: 1
        }],
        customer: {
          email: "<?= htmlspecialchars($currentUser['email'] ?? '') ?>"
        },
        customData: {
          user_id: "<?= htmlspecialchars($currentUser['id'] ?? '') ?>"
        },
        settings: {
          displayMode: "overlay",
          allowLogout: false
        }
      });
    } catch (error) {
      console.error("Error opening checkout:", error);
      alert("<?= __('pricing.checkout_error') ?>");
    }
  }
</script>

</body>
</html>
