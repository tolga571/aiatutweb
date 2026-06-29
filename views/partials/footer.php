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
<?php
?>
