<?php
// views/partials/footer.php – site footer with navigation links
?>
<footer class="bg-surface-container-low/80 border-t border-outline-variant/20 py-4 mt-8">
  <div
    class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center text-sm text-on-surface-variant">
    <div class="mb-2 md:mb-0">
      <?= sprintf(__('footer.copyright'), date('Y')) ?>
    </div>
    <nav class="flex flex-wrap justify-center md:justify-start gap-x-5 gap-y-0 md:gap-4">
      <a href="?page=privacy-policy" class="hover:underline py-2 md:py-0"><?= __('footer.privacy') ?></a>
      <a href="?page=terms-and-conditions" class="hover:underline py-2 md:py-0"><?= __('footer.terms') ?></a>
      <a href="?page=refund-policy" class="hover:underline py-2 md:py-0"><?= __('footer.refund') ?></a>
      <a href="?page=cookie-policy" class="hover:underline py-2 md:py-0"><?= __('footer.cookie') ?></a>
      <a href="?page=pricing" class="hover:underline py-2 md:py-0"><?= __('footer.pricing') ?></a>
      <a href="?page=about" class="hover:underline py-2 md:py-0"><?= __('footer.about') ?></a>
      <a href="?page=contact" class="hover:underline py-2 md:py-0"><?= __('footer.contact') ?></a>
      <a href="?page=faq" class="hover:underline py-2 md:py-0"><?= __('footer.faq') ?></a>
    </nav>
    <?php
    // Interface-language picker, reachable on every screen size.
    $footUiLang = \App\Src\Language::currentLang();
    $footUiLangs = \App\Src\Language::listed('ui', $footUiLang);
    if (count($footUiLangs) > 1): ?>
    <label class="mt-2 md:mt-0 flex items-center gap-1.5">
      <span class="material-symbols-outlined text-[18px]" aria-hidden="true">translate</span>
      <span class="sr-only"><?= __('nav.interface_language') ?></span>
      <select aria-label="<?= htmlspecialchars(__('nav.interface_language')) ?>"
        class="bg-transparent border border-outline-variant/30 rounded-lg py-1 pl-2 pr-7 text-sm text-on-surface-variant focus:outline-none focus:border-primary"
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
