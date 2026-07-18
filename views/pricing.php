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
$starterYearlyPriceId = $config['paddle_starter_yearly_price_id'] ?? '';
$proYearlyPriceId = $config['paddle_pro_yearly_price_id'] ?? '';
$premiumYearlyPriceId = $config['paddle_premium_yearly_price_id'] ?? '';
$hasYearlyOption = $starterYearlyPriceId !== '' || $proYearlyPriceId !== '' || $premiumYearlyPriceId !== '';
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
    $currentPlanRank = \App\Src\TokenManager::planRank($userPlan);
    // Per-card button label/tone: only meaningful once the user already has a
    // paid plan, so we know whether switching to a given card is an upgrade
    // or a downgrade rather than always saying the same generic "Switch Plan".
    $planDirection = function (string $planKey) use ($currentPlanRank) {
        $rank = \App\Src\TokenManager::planRank($planKey);
        return $rank > $currentPlanRank ? 'upgrade' : 'downgrade';
    };
    $hasCancelPending = !empty($currentUser['cancel_requested_at']);
    $hasChangePending = !empty($currentUser['pending_plan_change']);
    $nextBilledLabel = !empty($currentUser['next_billed_at']) ? date('j M Y', strtotime($currentUser['next_billed_at'])) : '';
    $pendingChangeLabel = $hasChangePending ? ($planLabels[$currentUser['pending_plan_change']] ?? $currentUser['pending_plan_change']) : '';
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
        <p class="text-body-md text-on-surface-variant mb-3"><?= __('pricing.already_subscribed_body') ?></p>

        <?php if ($hasChangePending && $nextBilledLabel): ?>
          <p class="text-body-md text-primary font-semibold mb-6"><?= sprintf(__('pricing.change_pending_notice'), $pendingChangeLabel, $nextBilledLabel) ?></p>
        <?php elseif ($hasCancelPending): ?>
          <p class="text-body-md text-error font-semibold mb-6">
            <?= ($currentUser['cancel_method'] ?? '') === 'api' ? __('pricing.cancel_pending_api') : __('pricing.cancel_pending_manual') ?>
          </p>
        <?php elseif ($nextBilledLabel): ?>
          <p class="text-body-md text-on-surface-variant mb-6"><?= sprintf(__('pricing.renews_on'), $nextBilledLabel) ?></p>
        <?php else: ?>
          <div class="mb-2"></div>
        <?php endif; ?>

        <div class="flex flex-wrap items-center justify-center gap-3">
          <a href="?page=chat" class="inline-flex items-center gap-2 bg-primary text-on-primary hover:opacity-90 font-semibold text-sm px-xl py-3 rounded-xl transition-all shadow-md glow-hover">
            <?= __('pricing.go_to_chat') ?>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
          <?php if ($hasCancelPending || $hasChangePending): ?>
            <button type="button" id="resume-sub-btn" onclick="resumeSubscription()"
              class="inline-flex items-center gap-2 border border-primary/30 text-primary hover:bg-primary/10 font-semibold text-sm px-xl py-3 rounded-xl transition-all">
              <span class="material-symbols-outlined text-[16px]">undo</span>
              <?= __('pricing.resume_subscription') ?>
            </button>
          <?php else: ?>
            <button type="button" id="cancel-sub-btn" onclick="requestCancelSubscription()"
              class="inline-flex items-center gap-2 border border-error/30 text-error hover:bg-error/10 font-semibold text-sm px-xl py-3 rounded-xl transition-all">
              <span class="material-symbols-outlined text-[16px]">cancel</span>
              <?= __('pricing.cancel_subscription') ?>
            </button>
          <?php endif; ?>
          <?php if (empty($currentUser['refund_requested_at'])): ?>
            <button type="button" id="refund-btn" onclick="requestRefund()"
              class="inline-flex items-center gap-2 text-xs text-on-surface-variant hover:text-on-surface underline underline-offset-2 px-2 py-3">
              <?= __('pricing.request_refund') ?>
            </button>
          <?php else: ?>
            <span class="inline-flex items-center gap-2 text-xs text-on-surface-variant px-2 py-3">
              <?= __('pricing.refund_requested_note') ?>
            </span>
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

      <?php if ($hasYearlyOption): ?>
      <div class="flex items-center justify-center gap-2 mb-8">
        <button type="button" id="interval-toggle-monthly" onclick="setBillingInterval('month')"
          class="billing-toggle-btn px-5 py-2 rounded-full text-body-md font-semibold transition-all bg-primary text-on-primary">
          <?= __('pricing.toggle_monthly') ?>
        </button>
        <button type="button" id="interval-toggle-yearly" onclick="setBillingInterval('year')"
          class="billing-toggle-btn px-5 py-2 rounded-full text-body-md font-semibold transition-all bg-surface-container-high text-on-surface-variant flex items-center gap-2">
          <?= __('pricing.toggle_yearly') ?>
          <span class="text-[10px] bg-primary/20 text-primary px-2 py-0.5 rounded-full font-bold"><?= __('pricing.toggle_save_badge') ?></span>
        </button>
      </div>
      <?php endif; ?>

      <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-8 max-w-5xl mx-auto items-stretch">
      <!-- Starter Plan Card -->
      <div class="glass-panel rounded-2xl p-8 flex flex-col justify-between transition-transform duration-300 hover:scale-[1.02] relative <?= $userPlan === 'starter' ? 'border-2 border-primary ring-2 ring-primary/30' : 'border border-outline/20' ?>">
        <?php if ($userPlan === 'starter'): ?>
          <div class="absolute top-0 right-0 bg-primary text-on-primary text-[10px] font-bold tracking-widest uppercase py-1 px-4 rounded-bl-xl rounded-tr-2xl font-label-md flex items-center gap-1">
            <span class="material-symbols-outlined text-[12px]">check_circle</span>
            <?= __('pricing.current_plan') ?>
          </div>
        <?php endif; ?>
        <div>
          <div class="text-outline text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.starter_title') ?></div>
          <div class="text-4xl font-bold text-on-surface mb-1">
            <span class="price-monthly"><?= __('pricing.starter_monthly') ?></span>
            <?php if ($starterYearlyPriceId !== ''): ?>
              <span class="price-yearly hidden"><?= __('pricing.starter_yearly') ?></span>
            <?php endif; ?>
          </div>
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
              <?= __('pricing.current_plan') ?>
            </button>
          <?php elseif ($isPaidUser): $dir = $planDirection('starter'); ?>
            <button onclick="changePlan('starter', <?= json_encode($dir) ?>, <?= json_encode($planLabels['starter']) ?>)"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 relative flex items-center justify-center text-center glow-hover">
              <span><?= $dir === 'upgrade' ? __('pricing.action_upgrade') : __('pricing.action_downgrade') ?></span>
              <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-[20px]"><?= $dir === 'upgrade' ? 'arrow_upward' : 'arrow_downward' ?></span>
            </button>
          <?php else: ?>
            <button onclick="openCheckout(planPriceId('starter'))"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 relative flex items-center justify-center text-center glow-hover"
              <?= (empty($paddleClientToken) || empty($starterPriceId)) ? 'disabled' : '' ?>>
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
            <?= __('pricing.current_plan') ?>
          <?php else: ?>
            <?= __('pricing.recommended') ?>
          <?php endif; ?>
        </div>

        <div>
          <div class="text-primary text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.pro_title') ?></div>
          <div class="text-4xl font-bold text-on-surface mb-1">
            <span class="price-monthly"><?= __('pricing.pro_monthly') ?></span>
            <?php if ($proYearlyPriceId !== ''): ?>
              <span class="price-yearly hidden"><?= __('pricing.pro_yearly') ?></span>
            <?php endif; ?>
          </div>
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
              <?= __('pricing.current_plan') ?>
            </button>
          <?php elseif ($isPaidUser): $dir = $planDirection('pro'); ?>
            <button onclick="changePlan('pro', <?= json_encode($dir) ?>, <?= json_encode($planLabels['pro']) ?>)"
              class="w-full bg-primary text-on-primary hover:opacity-90 font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover">
              <?= $dir === 'upgrade' ? __('pricing.action_upgrade') : __('pricing.action_downgrade') ?>
              <span class="material-symbols-outlined"><?= $dir === 'upgrade' ? 'arrow_upward' : 'arrow_downward' ?></span>
            </button>
          <?php else: ?>
            <button onclick="openCheckout(planPriceId('pro'))"
              class="w-full bg-primary text-on-primary hover:opacity-90 font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
              <?= (empty($paddleClientToken) || empty($proPriceId)) ? 'disabled' : '' ?>>
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
            <?= __('pricing.current_plan') ?>
          </div>
        <?php endif; ?>
        <div class="text-primary text-label-md font-semibold mb-2 uppercase tracking-wide"><?= __('pricing.premium_title') ?></div>
        <div class="text-4xl font-bold text-on-surface mb-1">
          <span class="price-monthly"><?= __('pricing.premium_monthly') ?></span>
          <?php if ($premiumYearlyPriceId !== ''): ?>
            <span class="price-yearly hidden"><?= __('pricing.premium_yearly') ?></span>
          <?php endif; ?>
        </div>
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
              <?= __('pricing.current_plan') ?>
            </button>
          <?php elseif ($isPaidUser): $dir = $planDirection('active'); ?>
            <button onclick="changePlan('active', <?= json_encode($dir) ?>, <?= json_encode($planLabels['active']) ?>)"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover">
              <?= $dir === 'upgrade' ? __('pricing.action_upgrade') : __('pricing.action_downgrade') ?>
              <span class="material-symbols-outlined"><?= $dir === 'upgrade' ? 'arrow_upward' : 'arrow_downward' ?></span>
            </button>
          <?php else: ?>
            <button onclick="openCheckout(planPriceId('active'))"
              class="w-full bg-secondary-container hover:bg-outline/20 text-on-surface font-semibold py-3 rounded-xl transition duration-300 flex items-center justify-center gap-2 glow-hover"
              <?= (empty($paddleClientToken) || empty($premiumPriceId)) ? 'disabled' : '' ?>>
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

<!-- Confirm / result modal for plan-change and cancellation actions -->
<div id="action-modal" class="fixed inset-0 bg-surface-container-lowest/80 backdrop-blur-md flex items-center justify-center z-[100] hidden">
  <div class="glass-panel max-w-sm w-full p-8 rounded-2xl border border-primary/30 text-center shadow-2xl">
    <div id="action-modal-icon" class="w-14 h-14 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary mx-auto mb-4">
      <span class="material-symbols-outlined text-[30px]">help</span>
    </div>
    <h3 id="action-modal-title" class="font-headline-sm text-[20px] font-semibold text-on-surface mb-2"></h3>
    <p id="action-modal-body" class="text-body-md text-on-surface-variant mb-6"></p>
    <div id="action-modal-buttons" class="flex gap-3 justify-center">
      <button type="button" id="action-modal-secondary" onclick="closeActionModal()"
        class="bg-surface-container-high hover:bg-outline/10 text-on-surface font-semibold text-xs px-xl py-3 rounded-xl transition-all border border-outline-variant/30">
        <?= __('pricing.modal_go_back') ?>
      </button>
      <button type="button" id="action-modal-primary"
        class="bg-primary text-on-primary hover:opacity-90 font-semibold text-xs px-xl py-3 rounded-xl transition-all shadow-md">
        <?= __('pricing.modal_confirm') ?>
      </button>
    </div>
  </div>
</div>

<script>
  let currentCheckout = null;
  let currentCheckoutPriceId = null;

  // Monthly/yearly billing toggle. Each plan can have a yearly Price in
  // Paddle in addition to its monthly one; falls back to monthly if a
  // plan has no yearly price configured.
  const PRICE_IDS = {
    starter: { month: <?= json_encode($starterPriceId) ?>, year: <?= json_encode($starterYearlyPriceId) ?> },
    pro: { month: <?= json_encode($proPriceId) ?>, year: <?= json_encode($proYearlyPriceId) ?> },
    active: { month: <?= json_encode($premiumPriceId) ?>, year: <?= json_encode($premiumYearlyPriceId) ?> },
  };
  let billingInterval = 'month';

  function planPriceId(planKey) {
    const ids = PRICE_IDS[planKey] || {};
    return (ids[billingInterval] || ids.month || '');
  }

  function setBillingInterval(interval) {
    billingInterval = interval === 'year' ? 'year' : 'month';
    document.querySelectorAll('.price-monthly').forEach(function(el) { el.classList.toggle('hidden', billingInterval !== 'month'); });
    document.querySelectorAll('.price-yearly').forEach(function(el) { el.classList.toggle('hidden', billingInterval !== 'year'); });

    const monthBtn = document.getElementById('interval-toggle-monthly');
    const yearBtn = document.getElementById('interval-toggle-yearly');
    [[monthBtn, billingInterval === 'month'], [yearBtn, billingInterval === 'year']].forEach(function(pair) {
      const btn = pair[0], active = pair[1];
      if (!btn) return;
      btn.classList.toggle('bg-primary', active);
      btn.classList.toggle('text-on-primary', active);
      btn.classList.toggle('bg-surface-container-high', !active);
      btn.classList.toggle('text-on-surface-variant', !active);
    });
  }

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
            fetch('?page=confirm-payment&price_id=' + encodeURIComponent(currentCheckoutPriceId || '')).catch(function(e) { console.error(e); });
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
      currentCheckoutPriceId = priceId;
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
  const I18N = {
    processing: <?= json_encode(__('pricing.processing')) ?>,
    modalConfirm: <?= json_encode(__('pricing.modal_confirm')) ?>,
    modalGoBack: <?= json_encode(__('pricing.modal_go_back')) ?>,
    manualChangeRequired: <?= json_encode(__('pricing.manual_change_required')) ?>,
    changeError: <?= json_encode(__('pricing.change_error')) ?>,
    cancelError: <?= json_encode(__('pricing.cancel_error')) ?>,
    cancelSuccessApi: <?= json_encode(__('pricing.cancel_success_api')) ?>,
    cancelSuccessManual: <?= json_encode(__('pricing.cancel_success_manual')) ?>,
    cancellationPending: <?= json_encode(__('pricing.cancellation_pending_error')) ?>,
    resumeError: <?= json_encode(__('pricing.resume_error')) ?>,
    resumeSuccess: <?= json_encode(__('pricing.resume_success')) ?>,
    confirmResumeTitle: <?= json_encode(__('pricing.confirm_resume_title')) ?>,
    confirmResumeBody: <?= json_encode(__('pricing.confirm_resume_body')) ?>,
    resumeSubscriptionLabel: <?= json_encode(__('pricing.resume_subscription')) ?>,
    changePending: <?= json_encode(__('pricing.change_pending_error')) ?>,
    changeDeferredTitle: <?= json_encode(__('pricing.change_deferred_title')) ?>,
    changeDeferredBody: <?= json_encode(__('pricing.change_deferred_body')) ?>,
    confirmRefundTitle: <?= json_encode(__('pricing.confirm_refund_title')) ?>,
    confirmRefundBody: <?= json_encode(__('pricing.confirm_refund_body')) ?>,
    requestRefundLabel: <?= json_encode(__('pricing.request_refund')) ?>,
    refundSuccess: <?= json_encode(__('pricing.refund_success')) ?>,
    refundError: <?= json_encode(__('pricing.refund_error')) ?>,
  };

  // ── Generic confirm / result modal (replaces browser confirm()/alert()) ──
  const actionModal = document.getElementById('action-modal');
  const actionModalIcon = document.getElementById('action-modal-icon');
  const actionModalTitle = document.getElementById('action-modal-title');
  const actionModalBody = document.getElementById('action-modal-body');
  const actionModalButtons = document.getElementById('action-modal-buttons');
  const actionModalPrimary = document.getElementById('action-modal-primary');
  const actionModalSecondary = document.getElementById('action-modal-secondary');

  function closeActionModal() {
    actionModal.classList.add('hidden');
  }

  function setModalIcon(name, tone) {
    const toneClasses = {
      primary: 'bg-primary/10 border-primary/20 text-primary',
      error: 'bg-error-container border-error/30 text-error',
      success: 'bg-primary/10 border-primary/20 text-primary',
      info: 'bg-secondary-container border-outline-variant/30 text-on-surface',
    };
    actionModalIcon.className = 'w-14 h-14 rounded-2xl border flex items-center justify-center mx-auto mb-4 ' + (toneClasses[tone] || toneClasses.primary);
    actionModalIcon.innerHTML = '<span class="material-symbols-outlined text-[30px]">' + name + '</span>';
  }

  // Shows a Yes/No confirmation. Resolves true if the user confirmed.
  function askConfirm(title, body, confirmLabel) {
    return new Promise((resolve) => {
      setModalIcon('help', 'primary');
      actionModalTitle.textContent = title;
      actionModalBody.textContent = body;
      actionModalButtons.classList.remove('hidden');
      actionModalSecondary.classList.remove('hidden');
      actionModalPrimary.textContent = confirmLabel || I18N.modalConfirm;
      actionModalPrimary.disabled = false;
      actionModal.classList.remove('hidden');

      const cleanup = () => {
        actionModalPrimary.onclick = null;
        actionModalSecondary.onclick = null;
      };
      actionModalPrimary.onclick = () => { cleanup(); closeActionModal(); resolve(true); };
      actionModalSecondary.onclick = () => { cleanup(); closeActionModal(); resolve(false); };
    });
  }

  function showModalProcessing() {
    setModalIcon('sync', 'primary');
    actionModalTitle.textContent = I18N.processing;
    actionModalBody.textContent = '';
    actionModalButtons.classList.add('hidden');
    actionModal.classList.remove('hidden');
  }

  function showModalResult(tone, title, body) {
    const icons = { error: 'error', success: 'check_circle', info: 'info' };
    setModalIcon(icons[tone] || 'check_circle', tone);
    actionModalTitle.textContent = title;
    actionModalBody.textContent = body;
    actionModalButtons.classList.remove('hidden');
    actionModalSecondary.classList.add('hidden');
    actionModalPrimary.textContent = I18N.modalConfirm;
    actionModalPrimary.onclick = () => closeActionModal();
    actionModal.classList.remove('hidden');
  }

  async function changePlan(planKey, direction, planLabel) {
    const isUpgrade = direction === 'upgrade';
    const title = isUpgrade
      ? <?= json_encode(__('pricing.confirm_upgrade_title')) ?>
      : <?= json_encode(__('pricing.confirm_downgrade_title')) ?>;
    const bodyTemplate = isUpgrade
      ? <?= json_encode(__('pricing.confirm_upgrade_body')) ?>
      : <?= json_encode(__('pricing.confirm_downgrade_body')) ?>;
    const body = bodyTemplate.replace('%s', planLabel);
    const confirmLabel = isUpgrade ? <?= json_encode(__('pricing.action_upgrade')) ?> : <?= json_encode(__('pricing.action_downgrade')) ?>;

    const confirmed = await askConfirm(title, body, confirmLabel);
    if (!confirmed) return;

    showModalProcessing();
    try {
      const res = await fetch('?page=change-subscription-plan', {
        method: 'POST',
        body: new URLSearchParams({ plan: planKey, interval: billingInterval, csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      if (data.ok && data.deferred) {
        showModalResult('success', I18N.changeDeferredTitle, I18N.changeDeferredBody.replace('%s', planLabel));
        actionModalPrimary.onclick = () => { window.location.href = '?page=pricing'; };
      } else if (data.ok) {
        window.location.href = '?page=pricing';
      } else if (data.error === 'manual_required') {
        showModalResult('info', I18N.modalConfirm, I18N.manualChangeRequired);
      } else if (data.error === 'cancellation_pending') {
        showModalResult('info', I18N.modalConfirm, I18N.cancellationPending);
      } else if (data.error === 'change_pending') {
        showModalResult('info', I18N.modalConfirm, I18N.changePending);
      } else {
        showModalResult('error', I18N.modalConfirm, I18N.changeError);
      }
    } catch (error) {
      console.error('Error changing plan:', error);
      showModalResult('error', I18N.modalConfirm, I18N.changeError);
    }
  }

  async function resumeSubscription() {
    const confirmed = await askConfirm(I18N.confirmResumeTitle, I18N.confirmResumeBody, I18N.resumeSubscriptionLabel);
    if (!confirmed) return;

    const btn = document.getElementById('resume-sub-btn');
    if (btn) btn.disabled = true;
    showModalProcessing();
    try {
      const res = await fetch('?page=resume-subscription', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      if (data.ok) {
        showModalResult('success', I18N.modalConfirm, I18N.resumeSuccess);
        actionModalPrimary.onclick = () => { window.location.href = '?page=pricing'; };
      } else {
        showModalResult('error', I18N.modalConfirm, I18N.resumeError);
        if (btn) btn.disabled = false;
      }
    } catch (error) {
      console.error('Error resuming subscription:', error);
      showModalResult('error', I18N.modalConfirm, I18N.resumeError);
      if (btn) btn.disabled = false;
    }
  }

  async function requestRefund() {
    const confirmed = await askConfirm(I18N.confirmRefundTitle, I18N.confirmRefundBody, I18N.requestRefundLabel);
    if (!confirmed) return;

    const btn = document.getElementById('refund-btn');
    if (btn) btn.disabled = true;
    showModalProcessing();
    try {
      const res = await fetch('?page=request-refund', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      if (data.ok) {
        showModalResult('success', I18N.modalConfirm, I18N.refundSuccess);
        actionModalPrimary.onclick = () => { window.location.href = '?page=pricing'; };
      } else {
        showModalResult('error', I18N.modalConfirm, I18N.refundError);
        if (btn) btn.disabled = false;
      }
    } catch (error) {
      console.error('Error requesting refund:', error);
      showModalResult('error', I18N.modalConfirm, I18N.refundError);
      if (btn) btn.disabled = false;
    }
  }

  async function requestCancelSubscription() {
    const confirmed = await askConfirm(
      <?= json_encode(__('pricing.confirm_cancel_title')) ?>,
      <?= json_encode(__('pricing.confirm_cancel')) ?>,
      <?= json_encode(__('pricing.cancel_subscription')) ?>
    );
    if (!confirmed) return;

    const btn = document.getElementById('cancel-sub-btn');
    if (btn) btn.disabled = true;
    showModalProcessing();
    try {
      const res = await fetch('?page=cancel-subscription', {
        method: 'POST',
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN }),
      });
      const data = await res.json();
      if (data.ok) {
        const msg = data.method === 'api' ? I18N.cancelSuccessApi : I18N.cancelSuccessManual;
        showModalResult('success', I18N.modalConfirm, msg);
        actionModalPrimary.onclick = () => { window.location.href = '?page=pricing'; };
      } else if (data.error === 'change_pending') {
        showModalResult('info', I18N.modalConfirm, I18N.changePending);
        if (btn) btn.disabled = false;
      } else {
        showModalResult('error', I18N.modalConfirm, I18N.cancelError);
        if (btn) btn.disabled = false;
      }
    } catch (error) {
      console.error('Error requesting cancellation:', error);
      showModalResult('error', I18N.modalConfirm, I18N.cancelError);
      if (btn) btn.disabled = false;
    }
  }
</script>

</body>
</html>
