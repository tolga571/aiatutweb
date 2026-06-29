<nav class="relative w-full bg-surface-container-low/80 backdrop-blur-md border-b border-outline-variant/10 px-lg h-14 grid grid-cols-[1fr_auto_1fr] items-center z-50 shrink-0">
<link rel="stylesheet" href="/css/navbar.css">
<script src="/js/navbar.js" defer></script>
  <div class="flex items-center min-w-0">
    <a href="?page=home" class="flex flex-col shrink-0">
      <h1 class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</h1>
      <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
    </a>
  </div>

  <div class="hidden lg:flex items-center justify-center gap-base shrink-0">
    <a href="?page=home" class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">home</span>
      <span><?= __('nav.home') ?></span>
    </a>
    <a href="?page=chat" class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">forum</span>
      <span><?= __('nav.chat') ?></span>
    </a>
    <div class="relative inline-block">
      <button type="button" id="pagesBtn" class="nav-link pages-btn flex items-center gap-xs text-on-surface-variant px-lg py-2 hover:text-primary hover:bg-surface-variant/40 transition-colors rounded-full border border-outline-variant/20 bg-surface-container-high/60 shadow-sm" aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-outlined text-[18px]">web</span>
        <span class="font-semibold"><?= __('nav.pages') ?></span>
        <span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
      </button>
      <div id="pagesMenu" class="hidden absolute left-1/2 -translate-x-1/2 top-full mt-2 w-[min(36rem,calc(100vw-2rem))] bg-surface-container-high border border-outline-variant/20 rounded-xl shadow-xl overflow-hidden">
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
    <a href="?page=flashcards" class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">style</span>
      <span><?= __('nav.flashcards') ?></span>
    </a>
    <a href="?page=pricing" class="nav-link flex items-center gap-xs text-on-surface-variant px-md py-1.5 hover:text-primary transition-colors rounded-full">
      <span class="material-symbols-outlined text-[18px]">payments</span>
      <span><?= __('nav.pricing') ?></span>
    </a>
  </div>

  <div class="flex items-center justify-end gap-md min-w-0 justify-self-end">
    <?php if (isset($auth) && $auth->isLoggedIn()): ?>
        <?php 
            $currUser = $auth->currentUser();
            $plan = $currUser['plan_status'] ?? 'inactive';
        ?>
        
        <div class="relative inline-block text-left group">
            <button class="flex items-center gap-2 hover:bg-surface-variant/50 p-1 pr-3 rounded-full transition-colors focus:outline-none border border-outline-variant/20 cursor-default">
                <div class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center text-sm font-bold shadow-sm">
                    <?= strtoupper(substr($currUser['name'] ?? $currUser['email'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="text-sm font-medium text-on-surface hidden sm:block">
                    <?= htmlspecialchars($currUser['name'] ?? explode('@', $currUser['email'] ?? 'User')[0]) ?>
                </span>
                <span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
            </button>
            <div class="origin-top-right absolute right-0 pt-2 w-48 z-50 hidden group-hover:block">
                <div class="rounded-md shadow-lg border border-outline-variant/20 bg-surface-container-high ring-1 ring-black ring-opacity-5">
                    <div class="py-1" role="menu" aria-orientation="vertical">
                        <a href="?page=dashboard" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant" role="menuitem"><?= __('nav.profile') ?></a>
                        <a href="?page=chat" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant" role="menuitem"><?= __('nav.chat') ?></a>
                        <a href="?page=flashcards" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant" role="menuitem"><?= __('nav.flashcards') ?></a>
                        <a href="?page=chat-tips" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant" role="menuitem"><?= __('nav.instructions') ?></a>
                        <a href="?page=logout" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant text-error" role="menuitem"><?= __('nav.logout') ?></a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <a href="?page=login" class="flex items-center gap-2 px-5 py-2 rounded-full border border-primary/30 text-primary hover:bg-primary/10 transition-all font-semibold text-sm">
            <span class="material-symbols-outlined text-[18px]">login</span>
            <?= __('nav.login') ?>
        </a>
        <a href="?page=register" class="flex items-center gap-2 px-5 py-2 rounded-full bg-gradient-to-r from-primary to-primary/80 text-on-primary hover:opacity-90 shadow-md shadow-primary/20 transition-all font-semibold text-sm">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <?= __('nav.register') ?>
        </a>
    <?php endif; ?>
  </div>
</nav>
