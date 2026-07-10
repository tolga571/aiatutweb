<?php
// views/partials/footer.php – site footer with navigation links
?>
<footer class="bg-surface-container-low/80 border-t border-outline-variant/20 py-4 mt-8">
  <div
    class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center text-sm text-on-surface-variant">
    <div class="mb-2 md:mb-0">
      <?= sprintf(__('footer.copyright'), date('Y')) ?>
    </div>
    <nav class="flex flex-wrap gap-4">
      <a href="?page=privacy-policy" class="hover:underline"><?= __('footer.privacy') ?></a>
      <a href="?page=terms-and-conditions" class="hover:underline"><?= __('footer.terms') ?></a>
      <a href="?page=refund-policy" class="hover:underline"><?= __('footer.refund') ?></a>
      <a href="?page=cookie-policy" class="hover:underline"><?= __('footer.cookie') ?></a>
      <a href="?page=pricing" class="hover:underline"><?= __('footer.pricing') ?></a>
      <a href="?page=about" class="hover:underline"><?= __('footer.about') ?></a>
      <a href="?page=contact" class="hover:underline"><?= __('footer.contact') ?></a>
      <a href="?page=faq" class="hover:underline"><?= __('footer.faq') ?></a>
    </nav>
  </div>
</footer>
<!-- Cookie Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-surface-container-high border-t border-outline-variant/20 p-4 z-50 transform translate-y-full transition-transform duration-300">
  <div class="container mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="text-sm text-on-surface-variant flex-1">
      We use cookies to improve your experience. By continuing to visit this site you agree to our use of cookies.
      <a href="?page=cookie-policy" class="text-primary hover:underline ml-1">Learn more</a>
    </div>
    <div class="flex gap-2">
      <button onclick="acceptCookies()" class="bg-primary text-on-primary px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 transition">Accept</button>
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
