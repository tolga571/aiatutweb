<nav
  class="relative w-full bg-surface-container-low/80 backdrop-blur-md border-b border-outline-variant/10 px-lg h-14 grid grid-cols-[1fr_auto_1fr] items-center z-50 shrink-0">
  <link rel="stylesheet" href="/css/navbar.css?v=2">
  <script src="/js/navbar.js?v=3" defer></script>
  <div class="flex items-center min-w-0 col-start-1">
    <a href="?page=home" class="flex flex-col shrink-0">
      <p class="font-headline-md text-[18px] font-extrabold leading-none tracking-tight"><span class="text-primary">jump</span><span class="text-on-surface">learner</span></p>
      <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
    </a>
    <?php if (isset($auth) && $auth->isLoggedIn()):
      $planBadge = $auth->currentUser()['plan_status'] ?? 'inactive';
      $badgeLabel = match($planBadge) {
        'trial' => 'Trial',
        'starter' => 'Starter',
        'pro' => 'Pro',
        'active' => 'Premium',
        default => 'Free',
      };
      $badgeClass = match($planBadge) {
        'trial' => 'bg-warning/20 text-warning border-warning/30',
        'starter', 'pro', 'active' => 'bg-primary/20 text-primary border-primary/30',
        default => 'bg-surface-variant/50 text-on-surface-variant border-outline-variant/20',
      }; ?>
      <span class="hidden sm:inline-block ml-3 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border <?= $badgeClass ?>"><?= $badgeLabel ?></span>
    <?php endif; ?>
  </div>

  <div id="nav-center" class="hidden lg:flex items-center justify-center gap-base shrink-0 col-start-2">
    <a href="?page=home"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]" aria-hidden="true">home</span>
      <span class="nav-label"><?= __('nav.home') ?></span>
    </a>
    <a href="?page=chat"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]" aria-hidden="true">forum</span>
      <span class="nav-label"><?= __('nav.chat') ?></span>
    </a>
    <div class="relative inline-block">
      <button type="button" id="pagesBtn"
        class="nav-link pages-btn flex items-center gap-xs text-on-surface-variant px-lg py-2 hover:text-primary hover:bg-surface-variant/40 transition-colors rounded-full border border-outline-variant/20 bg-surface-container-high/60 shadow-sm"
        aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-outlined text-[18px]">web</span>
        <span class="nav-label font-semibold"><?= __('nav.pages') ?></span>
        <span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
      </button>
      <div id="pagesMenu"
        class="hidden absolute left-1/2 -translate-x-1/2 top-full mt-2 w-[min(36rem,calc(100vw-2rem))] bg-surface-container-high border border-outline-variant/20 rounded-xl shadow-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container-low/50">
          <p class="text-sm font-semibold text-on-surface"><?= __('nav.all_pages') ?></p>
          <p class="text-xs text-on-surface-variant mt-0.5"><?= __('nav.pages_subtitle') ?></p>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <p class="pages-menu-section-label"><?= __('nav.section_app') ?></p>
            <div class="space-y-1">
              <a href="?page=dashboard" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">dashboard</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.dashboard') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.dashboard_desc') ?></span>
                </span>
              </a>
              <a href="?page=chat-tips" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">menu_book</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.instructions') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.instructions_desc') ?></span>
                </span>
              </a>
              <a href="?page=alphabet" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">abc</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.alphabet') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.alphabet_desc') ?></span>
                </span>
              </a>
              <a href="?page=mistakes" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">edit_note</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.mistakes') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.mistakes_desc') ?></span>
                </span>
              </a>
            </div>
          </div>
          <div>
            <p class="pages-menu-section-label"><?= __('nav.section_company') ?></p>
            <div class="space-y-1">
              <a href="?page=about" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">info</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.about') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.about_desc') ?></span>
                </span>
              </a>
              <a href="?page=contact" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">mail</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.contact') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.contact_desc') ?></span>
                </span>
              </a>
              <a href="?page=faq" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">help</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.faq') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.faq_desc') ?></span>
                </span>
              </a>
              <a href="?page=blog" class="pages-menu-item">
                <span class="material-symbols-outlined pages-menu-icon">newspaper</span>
                <span>
                  <span class="pages-menu-title"><?= __('nav.blog') ?></span>
                  <span class="pages-menu-desc"><?= __('nav.blog_desc') ?></span>
                </span>
              </a>
            </div>
          </div>
          <div class="sm:col-span-2">
            <p class="pages-menu-section-label"><?= __('nav.section_legal') ?></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
              <a href="?page=privacy-policy" class="pages-menu-item pages-menu-item-compact">
                <span class="material-symbols-outlined pages-menu-icon">shield</span>
                <span class="pages-menu-title"><?= __('nav.privacy_policy') ?></span>
              </a>
              <a href="?page=terms-and-conditions" class="pages-menu-item pages-menu-item-compact">
                <span class="material-symbols-outlined pages-menu-icon">gavel</span>
                <span class="pages-menu-title"><?= __('nav.terms_conditions') ?></span>
              </a>
              <a href="?page=refund-policy" class="pages-menu-item pages-menu-item-compact">
                <span class="material-symbols-outlined pages-menu-icon">replay</span>
                <span class="pages-menu-title"><?= __('nav.refund_policy') ?></span>
              </a>
              <a href="?page=cookie-policy" class="pages-menu-item pages-menu-item-compact">
                <span class="material-symbols-outlined pages-menu-icon">cookie</span>
                <span class="pages-menu-title"><?= __('nav.cookie_policy') ?></span>
              </a>
              <a href="?page=license-agreement" class="pages-menu-item pages-menu-item-compact">
                <span class="material-symbols-outlined pages-menu-icon">contract</span>
                <span class="pages-menu-title"><?= __('nav.license_agreement') ?></span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <a href="?page=flashcards"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]" aria-hidden="true">style</span>
      <span class="nav-label"><?= __('nav.flashcards') ?></span>
    </a>
    <a href="?page=pricing"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]" aria-hidden="true">payments</span>
      <span class="nav-label"><?= __('nav.pricing') ?></span>
    </a>
  </div>

  <?php
  // Interface-language picker: published UI languages, for guests too.
  // Links keep the current query and add ui_lang (index.php stores it and
  // redirects back without it).
  $navUiLang = \App\Src\Language::currentLang();
  $navUiLinks = [];
  foreach (\App\Src\Language::listed('ui', $navUiLang) as $l) {
    $navUiLinks[$l] = '?' . http_build_query(array_merge(array_diff_key($_GET, ['ui_lang' => 1]), ['ui_lang' => $l]));
  }
  ?>
  <div id="nav-right" class="flex items-center justify-end gap-md min-w-0 justify-self-end col-start-3">
    <!-- Interface language -->
    <div class="relative hidden xl:inline-block text-left" id="nav-ui-switcher" title="<?= htmlspecialchars(__('nav.interface_language')) ?>">
      <button type="button" aria-haspopup="true" aria-expanded="false" aria-label="<?= htmlspecialchars(__('nav.interface_language')) ?>"
        class="flex items-center gap-1 p-1.5 pr-2 rounded-full hover:bg-surface-variant/50 transition-colors border border-outline-variant/20 cursor-pointer text-on-surface-variant">
        <span class="material-symbols-outlined text-[18px]">translate</span>
        <span class="text-[11px] font-semibold uppercase"><?= htmlspecialchars($navUiLang) ?></span>
      </button>
      <div id="nav-ui-dropdown" class="hidden absolute right-0 pt-2 w-44 z-50">
        <div class="rounded-xl shadow-lg border border-outline-variant/20 bg-surface-container-high overflow-hidden max-h-80 overflow-y-auto">
          <?php foreach ($navUiLinks as $l => $href): ?>
            <a href="<?= htmlspecialchars($href) ?>" lang="<?= $l ?>" <?= $l === $navUiLang ? 'aria-current="true"' : '' ?>
              class="flex items-center justify-between gap-2 px-3 py-2.5 text-xs transition <?= $l === $navUiLang ? 'text-primary font-semibold bg-primary/10' : 'text-on-surface hover:bg-surface-variant' ?>">
              <span><?= htmlspecialchars(\App\Src\Language::nativeName($l)) ?></span>
              <span class="text-[10px] uppercase text-outline"><?= $l ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php if (isset($auth) && $auth->isLoggedIn()):
      $currUser = $auth->currentUser();
      $plan = $currUser['plan_status'] ?? 'inactive';
      $navTargetLang = strtolower($currUser['target_lang'] ?? 'en');
      $navNativeLang = strtolower($currUser['native_lang'] ?? 'en');
      $navTargetCountry = \App\Src\Language::flagCountry($navTargetLang) ?: 'us';
      ?>
      <!-- Language Switcher -->
      <div class="relative hidden lg:inline-block text-left group" id="nav-lang-switcher" title="<?= __('nav.learning_language') ?>">
        <button
          class="flex items-center gap-1.5 p-1.5 pr-2.5 rounded-full hover:bg-surface-variant/50 transition-colors border border-outline-variant/20 cursor-pointer">
          <img src="https://flagcdn.com/<?= $navTargetCountry ?>.svg"
            class="w-5 h-3.5 rounded-[2px] object-cover shrink-0" />
          <span class="material-symbols-outlined text-[14px] text-on-surface-variant">expand_more</span>
        </button>
        <div id="nav-lang-dropdown" class="hidden absolute right-0 pt-2 w-40 z-50">
          <div class="rounded-xl shadow-lg border border-outline-variant/20 bg-surface-container-high overflow-hidden">
            <?php foreach (\App\Src\Language::listed('learn') as $l): $c = \App\Src\Language::flagCountry($l);
              if ($l === $navTargetLang || $l === $navNativeLang)
                continue; ?>
              <a href="?page=update_lang&lang=<?= $l ?>"
                class="flex items-center gap-2 px-3 py-2.5 text-xs text-on-surface hover:bg-surface-variant transition">
                <img src="https://flagcdn.com/<?= $c ?>.svg" class="w-5 h-3.5 rounded-[2px] object-cover shrink-0" />
                <?= __("languages.{$l}") ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="relative hidden lg:inline-block text-left" id="nav-profile-switcher">
        <button
          class="flex items-center gap-2 hover:bg-surface-variant/50 p-1 pr-3 rounded-full transition-colors focus:outline-none border border-outline-variant/20 cursor-pointer">
          <div
            class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center text-sm font-bold shadow-sm overflow-hidden">
            <?php if (!empty($currUser['profile_image'])): ?>
              <img src="<?= htmlspecialchars($currUser['profile_image']) ?>" class="w-full h-full object-cover" referrerpolicy="no-referrer" />
            <?php else: ?>
              <?= strtoupper(substr($currUser['name'] ?? $currUser['email'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <span class="text-sm font-medium text-on-surface hidden sm:block">
            <?= htmlspecialchars($currUser['name'] ?? explode('@', $currUser['email'] ?? 'User')[0]) ?>
          </span>
          <span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
        </button>
        <div id="nav-profile-dropdown" class="origin-top-right absolute right-0 pt-2 w-48 z-50 hidden">
          <div
            class="rounded-md shadow-lg border border-outline-variant/20 bg-surface-container-high ring-1 ring-black ring-opacity-5">
            <div class="py-1" role="menu" aria-orientation="vertical">
              <a href="?page=dashboard" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.dashboard') ?></a>
              <a href="?page=chat" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.chat') ?></a>
              <a href="?page=flashcards" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.flashcards') ?></a>
              <a href="?page=mistakes" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.mistakes') ?></a>
              <a href="?page=chat-tips" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.instructions') ?></a>
              <a href="?page=logout" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant text-error"
                role="menuitem"><?= __('nav.logout') ?></a>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <a href="?page=login"
        class="hidden sm:flex items-center gap-2 px-5 py-2 rounded-full border border-primary/30 text-primary hover:bg-primary/10 transition-all font-semibold text-sm">
        <span class="material-symbols-outlined text-[18px]">login</span>
        <?= __('nav.login') ?>
      </a>
      <a href="?page=register"
        class="hidden sm:flex items-center gap-2 px-5 py-2 rounded-full bg-gradient-to-r from-primary to-primary/80 text-on-primary hover:opacity-90 shadow-md shadow-primary/20 transition-all font-semibold text-sm">
        <span class="material-symbols-outlined text-[18px]">person_add</span>
        <?= __('nav.register') ?>
      </a>
    <?php endif; ?>

    <!-- Hamburger Button (mobile) -->
    <button id="hamburgerBtn"
      class="lg:hidden flex items-center justify-center w-10 h-10 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors"
      aria-label="<?= htmlspecialchars(__('nav.toggle_menu')) ?>">
      <span class="material-symbols-outlined text-[24px]">menu</span>
    </button>
  </div>
</nav>

<!-- Mobile Menu Overlay -->
<div id="mobileMenu" class="fixed inset-0 z-[60] hidden lg:hidden">
  <div class="absolute inset-0 bg-surface-dim/80 backdrop-blur-md" id="mobileMenuBackdrop"></div>
  <!-- Drops down from the top (full width), not a side drawer -->
  <div id="mobileMenuPanel"
    class="absolute left-0 right-0 top-0 max-h-[calc(100vh-1.5rem)] supports-[height:100dvh]:max-h-[calc(100dvh-1.5rem)] bg-surface-container-high border-b border-outline-variant/20 rounded-b-2xl shadow-2xl flex flex-col overflow-y-auto overscroll-contain">
    <div class="flex items-center justify-between p-4 border-b border-outline-variant/10">
      <a href="?page=home" class="flex flex-col">
        <span class="font-headline-md text-[18px] font-extrabold leading-none tracking-tight"><span class="text-primary">jump</span><span class="text-on-surface">learner</span></span>
        <span class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</span>
      </a>
      <button id="hamburgerCloseBtn"
        class="flex items-center justify-center w-10 h-10 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors"
        aria-label="<?= htmlspecialchars(__('nav.close_menu')) ?>">
        <span class="material-symbols-outlined text-[24px]">close</span>
      </button>
    </div>

    <div class="flex-1 p-4 space-y-1">
      <!-- Chat is the product: it gets its own prominent button above the other links -->
      <a href="?page=chat"
        class="mb-2 flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-primary to-primary/80 px-4 py-3 text-on-primary font-semibold shadow-md shadow-primary/20 hover:opacity-90 active:opacity-90 transition-opacity">
        <span class="flex items-center gap-3">
          <span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24">forum</span>
          <?= __('nav.chat') ?>
        </span>
        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
      </a>
      <?php if (isset($auth) && $auth->isLoggedIn()): ?>
      <!-- Account + learning language: phones don't get the crowded top-bar switchers -->
      <div class="rounded-xl border border-outline-variant/15 bg-surface-container/60 p-3 mb-2">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-full bg-primary text-on-primary flex items-center justify-center text-base font-bold overflow-hidden shrink-0">
            <?php if (!empty($currUser['profile_image'])): ?>
              <img src="<?= htmlspecialchars($currUser['profile_image']) ?>" class="w-full h-full object-cover" referrerpolicy="no-referrer" alt="" />
            <?php else: ?>
              <?= strtoupper(substr($currUser['name'] ?? $currUser['email'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-on-surface truncate"><?= htmlspecialchars($currUser['name'] ?? explode('@', $currUser['email'] ?? 'User')[0]) ?></p>
            <span class="inline-block mt-0.5 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border <?= $badgeClass ?>"><?= $badgeLabel ?></span>
          </div>
        </div>
        <p class="mt-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-outline"><?= __('nav.learning_language') ?></p>
        <div class="flex gap-1.5 overflow-x-auto -mx-1 px-1 pb-1 [scrollbar-width:none]">
          <?php
          // current language first, so the highlighted chip is never scrolled out of view
          $navLangs = [];
          foreach (\App\Src\Language::listed('learn', $navTargetLang) as $l) {
            if ($l === $navNativeLang && $l !== $navTargetLang) continue; // can't learn your own native language
            $navLangs[$l] = \App\Src\Language::flagCountry($l);
          }
          if (isset($navLangs[$navTargetLang])) {
            $navLangs = [$navTargetLang => $navLangs[$navTargetLang]] + $navLangs;
          }
          foreach ($navLangs as $l => $c):
            $isCur = ($l === $navTargetLang); ?>
            <a href="<?= $isCur ? '#' : '?page=update_lang&lang=' . $l ?>" <?= $isCur ? 'aria-current="true"' : '' ?>
              class="shrink-0 flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-xs transition-colors <?= $isCur ? 'border-primary/50 bg-primary/15 text-primary font-semibold' : 'border-outline-variant/20 text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50' ?>">
              <img src="https://flagcdn.com/<?= $c ?>.svg" class="w-4 h-3 rounded-[2px] object-cover shrink-0" alt="" />
              <?= __("languages.{$l}") ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="grid grid-cols-2 gap-x-1">
      <a href="?page=home"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">home</span>
        <?= __('nav.home') ?>
      </a>
      <a href="?page=flashcards"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">style</span>
        <?= __('nav.flashcards') ?>
      </a>
      <a href="?page=mistakes"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">edit_note</span>
        <?= __('nav.mistakes') ?>
      </a>
      <a href="?page=pricing"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">payments</span>
        <?= __('nav.pricing') ?>
      </a>
      <a href="?page=chat-tips"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">menu_book</span>
        <?= __('nav.instructions') ?>
      </a>
      <a href="?page=blog"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">article</span>
        <?= __('nav.blog') ?>
      </a>
      <a href="?page=dashboard"
        class="mobile-nav-link flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">dashboard</span>
        <?= __('nav.dashboard') ?>
      </a>
      </div>

      <div class="border-t border-outline-variant/10 my-2"></div>
      <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-outline"><?= __('nav.section_legal') ?></p>
      <div class="grid grid-cols-2 gap-x-1">
        <a href="?page=privacy-policy"
          class="mobile-nav-link flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
          <span class="material-symbols-outlined text-[16px] shrink-0">shield</span>
          <span class="min-w-0"><?= __('nav.privacy_policy') ?></span>
        </a>
        <a href="?page=terms-and-conditions"
          class="mobile-nav-link flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
          <span class="material-symbols-outlined text-[16px] shrink-0">gavel</span>
          <span class="min-w-0"><?= __('nav.terms_conditions') ?></span>
        </a>
        <a href="?page=refund-policy"
          class="mobile-nav-link flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
          <span class="material-symbols-outlined text-[16px] shrink-0">replay</span>
          <span class="min-w-0"><?= __('nav.refund_policy') ?></span>
        </a>
        <a href="?page=cookie-policy"
          class="mobile-nav-link flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
          <span class="material-symbols-outlined text-[16px] shrink-0">cookie</span>
          <span class="min-w-0"><?= __('nav.cookie_policy') ?></span>
        </a>
        <a href="?page=license-agreement"
          class="mobile-nav-link flex items-center gap-2 px-3 py-2.5 rounded-xl text-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
          <span class="material-symbols-outlined text-[16px] shrink-0">contract</span>
          <span class="min-w-0"><?= __('nav.license_agreement') ?></span>
        </a>
      </div>
    </div>

    <div class="px-4 pt-3 border-t border-outline-variant/10">
      <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-outline"><?= __('nav.interface_language') ?></p>
      <div class="flex gap-1.5 overflow-x-auto -mx-1 px-1 pb-1 [scrollbar-width:none]">
        <?php foreach ($navUiLinks as $l => $href): $isCur = $l === $navUiLang; ?>
          <a href="<?= $isCur ? '#' : htmlspecialchars($href) ?>" lang="<?= $l ?>" <?= $isCur ? 'aria-current="true"' : '' ?>
            class="shrink-0 rounded-full border px-2.5 py-1.5 text-xs transition-colors <?= $isCur ? 'border-primary/50 bg-primary/15 text-primary font-semibold' : 'border-outline-variant/20 text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50' ?>">
            <?= htmlspecialchars(\App\Src\Language::nativeName($l)) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!(isset($auth) && $auth->isLoggedIn())): ?>
      <div class="px-4 py-3 border-t border-outline-variant/10 space-y-2">
        <a href="?page=login"
          class="flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl border border-primary/30 text-primary hover:bg-primary/10 transition-all font-semibold text-sm">
          <span class="material-symbols-outlined text-[18px]">login</span>
          <?= __('nav.login') ?>
        </a>
        <a href="?page=register"
          class="flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary/80 text-on-primary hover:opacity-90 shadow-md shadow-primary/20 transition-all font-semibold text-sm">
          <span class="material-symbols-outlined text-[18px]">person_add</span>
          <?= __('nav.register') ?>
        </a>
      </div>
    <?php else: ?>
      <div class="px-4 py-3 border-t border-outline-variant/10 space-y-2">
        <a href="?page=logout"
          class="flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl border border-error/30 text-error hover:bg-error/10 transition-all font-semibold text-sm">
          <span class="material-symbols-outlined text-[18px]">logout</span>
          <?= __('nav.logout') ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>