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

    <?php if (empty($paddleClientToken) && $paddleEnvironment !== 'production'): ?>
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
    $isLoggedIn = $currentUser !== null;
    $isTrialUser = $isLoggedIn && ($currentUser['plan_status'] ?? '') === 'trial';
    $isPaidUser = $isLoggedIn && !$isTrialUser && (($currentUser['plan_status'] ?? '') === 'active' || ($currentUser['has_paid'] ?? 0) == 1);
    $trialMessagesSent = $isTrialUser && $isLoggedIn ? $auth->getTrialMessagesSent($currentUser['id']) : 0;
    $userPlan = $currentUser['plan_status'] ?? 'inactive';
    $planLabels = [
        'starter' => __('pricing.starter_title'),
        'pro' => __('pricing.pro_title'),
        'active' => __('pricing.premium_title'),
    ];
    $currentPlanLabel = $planLabels[$userPlan] ?? '';
    ?>

    <?php if ($isPaidUser): ?>
      <!-- Already subscribed message -->
      <div class="max-w-xl mx-auto mb-10 p-8 rounded-2xl border border-primary/30 bg-primary/5 backdrop-blur-md shadow-lg text-center">
        <div class="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary mx-auto mb-4">
          <span class="material-symbols-outlined text-[36px]">workspace_premium</span>
        </div>
        <span class="inline-block bg-primary/20 text-primary text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full mb-3">
          <?= $currentPlanLabel ? sprintf(__('pricing.status_active_plan'), $currentPlanLabel) : __('pricing.status_active') ?>
        </span>
        <h3 class="text-2xl font-bold text-on-surface mb-2"><?= __('pricing.already_subscribed_title') ?></h3>
        <p class="text-body-md text-on-surface-variant mb-6"><?= __('pricing.already_subscribed_body') ?></p>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <a href="?page=chat" class="inline-flex items-center gap-2 bg-primary text-on-primary hover:opacity-90 font-semibold text-sm px-xl py-3 rounded-xl transition-all shadow-md glow-hover">
            <?= __('pricing.go_to_chat') ?>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
          <?php if (!empty($currentUser['cancel_requested_at'])): ?>
            <span class="inline-flex items-center gap-2 text-xs text-on-surface-variant border border-outline-variant/30 rounded-xl px-4 py-3">
              <span class="material-symbols-outlined text-[16px]">schedule</span>
              <?= __('pricing.cancel_pending') ?>
            </span>
          <?php else: ?>
            <button type="button" id="cancel-sub-btn" onclick="requestCancelSubscription()"
              class="inline-flex items-center gap-2 border border-error/30 text-error hover:bg-error/10 font-semibold text-sm px-xl py-3 rounded-xl transition-all">
              <span class="material-symbols-outlined text-[16px]">cancel</span>
              <?= __('pricing.cancel_subscription') ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    
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
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] relative <?= $userPlan === 'starter' ? 'border-2 border-primary ring-2 ring-primary/30' : 'border border-outline/20' ?>">
        <?php if ($userPlan === 'starter'): ?>
          <div class="absolute top-0 right-0 bg-primary text-on-primary text-[10px] font-bold tracking-widest uppercase py-1 px-4 rounded-bl-xl rounded-tr-2xl font-label-md flex items-center gap-1">
            <span class="material-symbols-outlined text-[12px]">check_circle</span>
            <?= __('pricing.current_plan') ?? 'Current Plan' ?>
          </div>
        <?php endif; ?>
        <div>
          <div class="text-outline text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.starter_title') ?></div>
          <div class="text-4xl font-bold text-on-surface mb-1"><?= __('pricing.starter_monthly') ?></div>
          <p class="text-outline text-body-md mb-6"><?= __('pricing.starter_desc') ?></p>

          <div class="border-t border-outline/10 my-4"></div>

          <ul class="space-y-3 mb-8 text-body-md text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.starter_feature_1') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.starter_feature_2') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.starter_feature_3') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.starter_feature_4') ?>
            </li>
          </ul>
        </div>

        <div>
          <?php if ($userPlan === 'starter'): ?>
            <button disabled class="w-full bg-surface-container-high text-on-surface-variant font-semibold py-3 rounded-xl cursor-not-allowed">
              <?= __('pricing.current_plan') ?? 'Current Plan' ?>
            </button>
          <?php elseif ($isPaidUser): ?>
            <button onclick="changePlan('starter')"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 relative flex items-center justify-center text-center glow-hover">
              <span><?= __('pricing.upgrade') ?? 'Switch Plan' ?></span>
              <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-[20px]">arrow_forward</span>
            </button>
          <?php else: ?>
            <button onclick="openCheckout('<?= htmlspecialchars($starterPriceId) ?>')"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 relative flex items-center justify-center text-center glow-hover"
              <?= empty($paddleClientToken) ? 'disabled' : '' ?>>
              <span><?= __('pricing.starter_btn') ?></span>
              <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-[20px]">arrow_forward</span>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Pro Plan Card (Popular) -->
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] relative overflow-hidden <?= $userPlan === 'pro' ? 'border-2 border-primary ring-2 ring-primary/30' : 'border border-primary/40' ?>">
        <div class="absolute top-0 right-0 bg-primary text-on-primary text-[10px] font-bold tracking-widest uppercase py-1 px-4 rounded-bl-xl font-label-md flex items-center gap-1">
          <?php if ($userPlan === 'pro'): ?>
            <span class="material-symbols-outlined text-[12px]">check_circle</span>
            <?= __('pricing.current_plan') ?? 'Current Plan' ?>
          <?php else: ?>
            <?= __('pricing.recommended') ?>
          <?php endif; ?>
        </div>

        <div>
          <div class="text-primary text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.pro_title') ?></div>
          <div class="text-4xl font-bold text-on-surface mb-1"><?= __('pricing.pro_monthly') ?></div>
          <p class="text-outline text-body-md mb-6"><?= __('pricing.pro_desc') ?></p>

          <div class="border-t border-outline/10 my-4"></div>

          <ul class="space-y-3 mb-8 text-body-md text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.pro_feature_1') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.pro_feature_2') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.pro_feature_3') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.pro_feature_4') ?>
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span>
              <?= __('pricing.pro_feature_5') ?>
            </li>
          </ul>
        </div>

        <div>
          <?php if ($userPlan === 'pro'): ?>
            <button disabled class="w-full bg-surface-container-high text-on-surface-variant font-semibold py-3 rounded-xl cursor-not-allowed">
              <?= __('pricing.current_plan') ?? 'Current Plan' ?>
            </button>
          <?php elseif ($isPaidUser): ?>
            <button onclick="changePlan('pro')"
              class="w-full bg-primary text-on-primary hover:opacity-90 font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover">
              <?= __('pricing.upgrade') ?? 'Switch Plan' ?>
              <span class="material-symbols-outlined">bolt</span>
            </button>
          <?php else: ?>
            <button onclick="openCheckout('<?= htmlspecialchars($proPriceId) ?>')"
              class="w-full bg-primary text-on-primary hover:opacity-90 font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
              <?= empty($paddleClientToken) ? 'disabled' : '' ?>>
              <?= __('pricing.pro_btn') ?>
              <span class="material-symbols-outlined">bolt</span>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Premium Plan Card -->
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] relative <?= $userPlan === 'active' ? 'border-2 border-primary ring-2 ring-primary/30' : 'border border-outline/20' ?>">
        <?php if ($userPlan === 'active'): ?>
          <div class="absolute top-0 right-0 bg-primary text-on-primary text-[10px] font-bold tracking-widest uppercase py-1 px-4 rounded-bl-xl rounded-tr-2xl font-label-md flex items-center gap-1">
            <span class="material-symbols-outlined text-[12px]">check_circle</span>
            <?= __('pricing.current_plan') ?? 'Current Plan' ?>
          </div>
        <?php endif; ?>
        <div class="text-primary text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.premium_title') ?></div>
        <div class="text-4xl font-bold text-on-surface mb-1"><?= __('pricing.premium_monthly') ?></div>
        <p class="text-outline text-body-md mb-6"><?= __('pricing.premium_desc') ?></p>

        <div class="border-t border-outline/10 my-4"></div>

        <ul class="space-y-3 mb-8 text-body-md text-on-surface-variant">
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span> <?= __('pricing.premium_feature_1') ?></li>
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span> <?= __('pricing.premium_feature_2') ?></li>
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span> <?= __('pricing.premium_feature_3') ?></li>
          <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-body-lg shrink-0">check</span> <?= __('pricing.premium_feature_4') ?></li>
        </ul>

        <div>
          <?php if ($userPlan === 'active'): ?>
            <button disabled class="w-full bg-surface-container-high text-on-surface-variant font-semibold py-3 rounded-xl cursor-not-allowed">
              <?= __('pricing.current_plan') ?? 'Current Plan' ?>
            </button>
          <?php elseif ($isPaidUser): ?>
            <button onclick="changePlan('active')"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover">
              <?= __('pricing.upgrade') ?? 'Switch Plan' ?>
              <span class="material-symbols-outlined">star</span>
            </button>
          <?php else: ?>
            <button onclick="openCheckout('<?= htmlspecialchars($premiumPriceId) ?>')"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
              <?= empty($paddleClientToken) ? 'disabled' : '' ?>>
              <?= __('pricing.premium_btn') ?>
              <span class="material-symbols-outlined">star</span>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Payment processing modal -->
<div id="payment-loading-modal" class="fixed inset-0 bg-surface-container-lowest/80 backdrop-blur-md flex items-center justify-center z-[100] hidden">
  <div class="glass-panel max-w-sm w-full p-8 rounded-2xl border border-primary/30 text-center shadow-2xl">
    <div id="payment-loading-content">
      <div class="relative flex justify-center mb-6">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-primary border-t-transparent"></div>
      </div>
      <h3 class="font-headline-sm text-[20px] font-semibold text-on-surface mb-2"><?= __('pricing.payment_modal_title') ?></h3>
      <p class="text-body-md text-on-surface-variant mb-6"><?= __('pricing.payment_modal_body') ?></p>
    </div>
  </div>
</div>

<script>
  let currentCheckout = null;

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
            if (currentCheckout) {
              currentCheckout.close();
              currentCheckout = null;
            }
            fetch('?page=confirm-payment').catch(function(e) { console.error(e); });
            handleCheckoutSuccess();
          }
        }
      });
    } catch (e) {
      console.error("Paddle initialization failed:", e);
    }
  <?php endif; ?>

  function handleCheckoutSuccess() {
    const modal = document.getElementById('payment-loading-modal');
    const content = document.getElementById('payment-loading-content');
    if (modal) {
      modal.classList.remove('hidden');
    }

    var pollCount = 0;
    const maxPolls = 60;
    var timeoutShown = false;

    const interval = setInterval(function() {
      pollCount++;
      fetch('?page=check-payment-status')
        .then(response => response.json())
        .then(data => {
          if (data.paid) {
            clearInterval(interval);
            window.location.href = '?page=chat';
          } else if (pollCount > maxPolls && !timeoutShown) {
            timeoutShown = true;
            if (content) {
              content.innerHTML =
                '<div class="w-16 h-16 rounded-2xl bg-error-container border border-error/30 flex items-center justify-center text-error mx-auto mb-4">' +
                  '<span class="material-symbols-outlined text-[36px]">warning</span>' +
                '</div>' +
                '<h3 class="font-headline-sm text-[20px] font-semibold text-on-surface mb-2"><?= __('pricing.timeout_title') ?></h3>' +
                '<p class="text-body-md text-on-surface-variant mb-6"><?= __('pricing.timeout_body') ?></p>' +
                '<div class="flex gap-3 justify-center">' +
                  '<button onclick="checkAgain()" class="bg-surface-container-high hover:bg-outline/10 text-on-surface font-semibold text-xs px-xl py-3 rounded-xl transition-all border border-outline-variant/30"><?= __('pricing.timeout_retry') ?></button>' +
                  '<a href="?page=chat" class="bg-primary text-on-primary hover:opacity-90 font-semibold text-xs px-xl py-3 rounded-xl transition-all shadow-md"><?= __('pricing.timeout_chat') ?></a>' +
                '</div>';
            }
          }
        })
        .catch(error => {
          console.error("Error checking payment status:", error);
        });
    }, 1500);
  }

  function checkAgain() {
    fetch('?page=check-payment-status')
      .then(response => response.json())
      .then(data => {
        if (data.paid) {
          window.location.href = '?page=chat';
        } else {
          location.reload();
        }
      })
      .catch(() => location.reload());
  }

  async function openCheckout(priceId) {
    if (!priceId) {
      alert("<?= __('pricing.price_id_error') ?>");
      return;
    }

    <?php if (!$isLoggedIn): ?>
      window.location.href = '?page=login';
      return;
    <?php endif; ?>

    try {
      currentCheckout = await Paddle.Checkout.open({
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

  const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

  async function changePlan(planKey) {
    if (!confirm(<?= json_encode(__('pricing.confirm_change')) ?>)) return;
    try {
      const res = await fetch('?page=change-subscription-plan', {
        method: 'POST',
        body: new URLSearchParams({ plan: planKey, csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      if (data.ok) {
        window.location.href = '?page=pricing';
      } else if (data.error === 'manual_required') {
        alert(<?= json_encode(__('pricing.manual_change_required')) ?>);
      } else {
        alert(<?= json_encode(__('pricing.checkout_error')) ?>);
      }
    } catch (error) {
      console.error('Error changing plan:', error);
      alert(<?= json_encode(__('pricing.checkout_error')) ?>);
    }
  }

  async function requestCancelSubscription() {
    if (!confirm(<?= json_encode(__('pricing.confirm_cancel')) ?>)) return;
    const btn = document.getElementById('cancel-sub-btn');
    if (btn) btn.disabled = true;
    try {
      const res = await fetch('?page=cancel-subscription', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      if (data.ok) {
        alert(data.method === 'api' ? <?= json_encode(__('pricing.cancel_success_api')) ?> : <?= json_encode(__('pricing.cancel_success_manual')) ?>);
        window.location.href = '?page=pricing';
      } else {
        alert(<?= json_encode(__('pricing.checkout_error')) ?>);
        if (btn) btn.disabled = false;
      }
    } catch (error) {
      console.error('Error requesting cancellation:', error);
      alert(<?= json_encode(__('pricing.checkout_error')) ?>);
      if (btn) btn.disabled = false;
    }
  }
</script>

</body>
</html>
