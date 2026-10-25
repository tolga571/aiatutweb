<?php
// views/partials/footer.php – site footer: brand, Learn / Company / Legal
// links (every page the Pages menu has), interface language, cookie banner.
?>
<link rel="stylesheet" href="/css/footer.css?v=1">
<?php $footGuest = !(isset($auth) && $auth->isLoggedIn()); ?>
<footer class="jl-footer">
  <div class="jl-footer-grid">
    <div class="jl-footer-brand">
      <a href="?page=home" class="jl-footer-logo">
        <img src="/brand/emblem.svg?v=3" alt="" width="44" height="48" class="jl-emblem" loading="lazy" decoding="async">
        <span>Jumplearner</span>
      </a>
      <p><?= __('footer.tagline') ?></p>
      <?php if ($footGuest): ?>
        <a href="?page=register" class="jl-footer-cta"><?= __('home.get_started') ?></a>
        <span class="jl-footer-note"><?= __('home.free_note') ?></span>
      <?php endif; ?>
    </div>
    <nav aria-labelledby="jl-f-learn">
      <h2 id="jl-f-learn"><?= __('footer.col_learn') ?></h2>
      <a href="?page=chat"><?= __('nav.chat') ?></a>
      <a href="?page=flashcards"><?= __('nav.flashcards') ?></a>
      <a href="/stories"><?= __('stories.heading') ?></a>
      <a href="?page=mistakes"><?= __('nav.mistakes') ?></a>
      <a href="?page=alphabet"><?= __('nav.alphabet') ?></a>
      <a href="?page=dashboard"><?= __('nav.dashboard') ?></a>
      <a href="?page=chat-tips"><?= __('nav.instructions') ?></a>
    </nav>
    <nav aria-labelledby="jl-f-company">
      <h2 id="jl-f-company"><?= __('footer.col_company') ?></h2>
      <a href="?page=about"><?= __('footer.about') ?></a>
      <a href="?page=pricing"><?= __('footer.pricing') ?></a>
      <a href="?page=blog"><?= __('nav.blog') ?></a>
      <a href="?page=faq"><?= __('footer.faq') ?></a>
      <a href="?page=contact"><?= __('footer.contact') ?></a>
    </nav>
    <nav aria-labelledby="jl-f-legal">
      <h2 id="jl-f-legal"><?= __('footer.col_legal') ?></h2>
      <a href="?page=privacy-policy"><?= __('footer.privacy') ?></a>
      <a href="?page=terms-and-conditions"><?= __('footer.terms') ?></a>
      <a href="?page=refund-policy"><?= __('footer.refund') ?></a>
      <a href="?page=cookie-policy"><?= __('footer.cookie') ?></a>
      <a href="?page=license-agreement"><?= __('nav.license_agreement') ?></a>
      <a href="?page=delete-account"><?= __('footer.delete_account') ?></a>
    </nav>
  </div>
  <div class="jl-footer-bottom">
    <span><?= sprintf(__('footer.copyright'), date('Y')) ?></span>
    <?php
    // Interface-language picker, reachable on every screen size.
    $footUiLang = \App\Src\Language::currentLang();
    $footUiLangs = \App\Src\Language::listed('ui', $footUiLang);
    if (count($footUiLangs) > 1): ?>
    <label class="jl-footer-lang">
      <span class="material-symbols-outlined" aria-hidden="true">translate</span>
      <span class="sr-only"><?= __('nav.interface_language') ?></span>
      <select aria-label="<?= htmlspecialchars(__('nav.interface_language')) ?>"
        onchange="var q=new URLSearchParams(location.search);q.set('ui_lang',this.value);location.search=q.toString();">
        <?php foreach ($footUiLangs as $l): ?>
          <option value="<?= $l ?>" <?= $l === $footUiLang ? 'selected' : '' ?>><?= htmlspecialchars(\App\Src\Language::nativeName($l)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
  </div>
</footer>
<!-- Cookie Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-surface-container-high border-t border-outline-variant/20 p-3 sm:p-4 z-50 transform translate-y-full transition-transform duration-300">
  <div class="container mx-auto flex flex-row items-center justify-between gap-3 sm:gap-4">
    <div class="text-xs sm:text-sm text-on-surface-variant flex-1">
      <?= __('footer.cookie_banner_text') ?>
      <a href="?page=cookie-policy" class="text-primary hover:underline ml-1"><?= __('footer.cookie_learn_more') ?></a>
    </div>
    <div class="flex gap-2">
      <button onclick="acceptCookies()" class="bg-primary text-on-primary px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold hover:opacity-90 transition shrink-0"><?= __('footer.cookie_accept') ?></button>
    </div>
  </div>
</div>
<script>
function acceptCookies() {
  document.cookie = "cookie_consent=1; max-age=" + (60*60*24*365) + "; path=/";
  document.getElementById('cookie-banner').classList.add('translate-y-full');
}
if (document.cookie.indexOf("cookie_consent=1") === -1) {
  setTimeout(() => {
    document.getElementById('cookie-banner').classList.remove('translate-y-full');
  }, 1000);
}
</script>
