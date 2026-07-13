<?php $pageTitle = htmlspecialchars($post['title']) . ' – AiTut'; ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto p-xl">
  <div class="max-w-2xl mx-auto">
    <a href="?page=blog" class="text-label-md text-outline hover:text-on-surface transition mb-6 inline-flex items-center gap-1">
      <span class="material-symbols-outlined text-[16px]">arrow_back</span>
      <?= __('blog.back_to_blog') ?>
    </a>

    <article class="mt-4">
      <h1 class="font-headline-md text-headline-md text-on-surface mb-3"><?= htmlspecialchars($post['title']) ?></h1>
      <div class="text-label-md text-outline mb-8"><?= date('M j, Y', strtotime($post['created_at'])) ?></div>
      <div class="text-body-md text-on-surface-variant leading-relaxed space-y-4">
        <?= nl2br(htmlspecialchars($post['content'])) ?>
      </div>
    </article>
  </div>
</main>

<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
