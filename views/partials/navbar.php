<nav
  class="relative w-full bg-surface-container-low/80 backdrop-blur-md border-b border-outline-variant/10 px-lg h-14 grid grid-cols-[1fr_auto_1fr] items-center z-50 shrink-0">
  <link rel="stylesheet" href="/css/navbar.css">
  <script src="/js/navbar.js" defer></script>
  <div class="flex items-center min-w-0">
    <a href="?page=home" class="flex flex-col shrink-0">
      <h1 class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</h1>
      <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
    </a>
    <?php if (isset($auth) && $auth->isLoggedIn()):
      $planBadge = $auth->currentUser()['plan_status'] ?? 'inactive';
      $badgeLabel = match($planBadge) {
        'trial' => 'Trial',
        'starter' => 'Starter',
        'pro' => 'Pro',
        'active' => 'Active',
        default => 'Free',
      };
      $badgeClass = match($planBadge) {
        'trial' => 'bg-warning/20 text-warning border-warning/30',
        'starter', 'pro', 'active' => 'bg-primary/20 text-primary border-primary/30',
        default => 'bg-surface-variant/50 text-on-surface-variant border-outline-variant/20',
      }; ?>
      <span class="ml-3 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border <?= $badgeClass ?>"><?= $badgeLabel ?></span>
    <?php endif; ?>
  </div>

  <div class="hidden lg:flex items-center justify-center gap-base shrink-0">
    <a href="?page=home"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">home</span>
      <span><?= __('nav.home') ?></span>
    </a>
    <a href="?page=chat"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">forum</span>
      <span><?= __('nav.chat') ?></span>
    </a>
    <div class="relative inline-block">
      <button type="button" id="pagesBtn"
        class="nav-link pages-btn flex items-center gap-xs text-on-surface-variant px-lg py-2 hover:text-primary hover:bg-surface-variant/40 transition-colors rounded-full border border-outline-variant/20 bg-surface-container-high/60 shadow-sm"
        aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-outlined text-[18px]">web</span>
        <span class="font-semibold"><?= __('nav.pages') ?></span>
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
      <span class="material-symbols-outlined text-[18px]">style</span>
      <span><?= __('nav.flashcards') ?></span>
    </a>
    <a href="?page=pricing"
      class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">payments</span>
      <span><?= __('nav.pricing') ?></span>
    </a>
  </div>

  <div class="flex items-center justify-end gap-md min-w-0 justify-self-end">
    <?php if (isset($auth) && $auth->isLoggedIn()):
      $currUser = $auth->currentUser();
      $plan = $currUser['plan_status'] ?? 'inactive';
      $navTargetLang = strtolower($currUser['target_lang'] ?? 'en');
      $navNativeLang = strtolower($currUser['native_lang'] ?? 'en');
      $navLangMap = ['en' => 'us', 'de' => 'de', 'fr' => 'fr', 'es' => 'es', 'zh' => 'cn', 'ja' => 'jp', 'ar' => 'sa', 'tr' => 'tr'];
      $navTargetCountry = $navLangMap[$navTargetLang] ?? 'us';
      ?>
      <!-- Language Switcher -->
      <div class="relative inline-block text-left group" id="nav-lang-switcher" title="<?= __('nav.learning_language') ?? 'Learning Language' ?>">
        <button
          class="flex items-center gap-1.5 p-1.5 pr-2.5 rounded-full hover:bg-surface-variant/50 transition-colors border border-outline-variant/20 cursor-pointer">
          <img src="https://flagcdn.com/<?= $navTargetCountry ?>.svg"
            class="w-5 h-3.5 rounded-[2px] object-cover shrink-0" />
          <span class="material-symbols-outlined text-[14px] text-on-surface-variant">expand_more</span>
        </button>
        <div id="nav-lang-dropdown" class="hidden absolute right-0 pt-2 w-40 z-50">
          <div class="rounded-xl shadow-lg border border-outline-variant/20 bg-surface-container-high overflow-hidden">
            <?php foreach (['en' => 'us', 'de' => 'de', 'fr' => 'fr', 'es' => 'es', 'zh' => 'cn', 'ja' => 'jp', 'ar' => 'sa', 'tr' => 'tr'] as $l => $c):
              if ($l === $navTargetLang)
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

      <div class="relative inline-block text-left group">
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
        <div class="origin-top-right absolute right-0 pt-2 w-48 z-50 hidden group-hover:block">
          <div
            class="rounded-md shadow-lg border border-outline-variant/20 bg-surface-container-high ring-1 ring-black ring-opacity-5">
            <div class="py-1" role="menu" aria-orientation="vertical">
              <a href="?page=dashboard" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.dashboard') ?></a>
              <a href="?page=chat" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.chat') ?></a>
              <a href="?page=flashcards" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
                role="menuitem"><?= __('nav.flashcards') ?></a>
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
      aria-label="Toggle menu">
      <span class="material-symbols-outlined text-[24px]">menu</span>
    </button>
  </div>
</nav>

<!-- Mobile Menu Overlay -->
<div id="mobileMenu" class="fixed inset-0 z-[60] hidden lg:hidden">
  <div class="absolute inset-0 bg-surface-dim/80 backdrop-blur-md" id="mobileMenuBackdrop"></div>
  <div
    class="absolute right-0 top-0 h-full w-72 max-w-[85vw] bg-surface-container-high border-l border-outline-variant/20 shadow-2xl flex flex-col overflow-y-auto">
    <div class="flex items-center justify-between p-4 border-b border-outline-variant/10">
      <a href="?page=home" class="flex flex-col">
        <span class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</span>
        <span class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</span>
      </a>
      <button id="hamburgerCloseBtn"
        class="flex items-center justify-center w-10 h-10 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors"
        aria-label="Close menu">
        <span class="material-symbols-outlined text-[24px]">close</span>
      </button>
    </div>

    <div class="flex-1 p-4 space-y-1">
      <a href="?page=home"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">home</span>
        <?= __('nav.home') ?>
      </a>
      <a href="?page=chat"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">forum</span>
        <?= __('nav.chat') ?>
      </a>
      <a href="?page=flashcards"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">style</span>
        <?= __('nav.flashcards') ?>
      </a>
      <a href="?page=pricing"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">payments</span>
        <?= __('nav.pricing') ?>
      </a>
      <a href="?page=chat-tips"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">menu_book</span>
        <?= __('nav.instructions') ?>
      </a>
      <a href="?page=blog"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">article</span>
        <?= __('nav.blog') ?>
      </a>
      <a href="?page=dashboard"
        class="mobile-nav-link flex items-center gap-3 px-3 py-3 rounded-xl text-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/50 transition-colors">
        <span class="material-symbols-outlined text-[20px]">dashboard</span>
        <?= __('nav.dashboard') ?>
      </a>
    </div>

    <?php if (!(isset($auth) && $auth->isLoggedIn())): ?>
      <div class="p-4 border-t border-outline-variant/10 space-y-2">
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
      <div class="p-4 border-t border-outline-variant/10 space-y-2">
        <a href="?page=logout"
          class="flex items-center justify-center gap-2 w-full px-5 py-2.5 rounded-xl border border-error/30 text-error hover:bg-error/10 transition-all font-semibold text-sm">
          <span class="material-symbols-outlined text-[18px]">logout</span>
          <?= __('nav.logout') ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>