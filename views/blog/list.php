<?php $pageTitle = 'Blog – AiTut'; ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto p-xl">
  <div class="max-w-3xl mx-auto">
    <h1 class="font-headline-md text-headline-md text-on-surface mb-2">Blog</h1>
    <p class="text-body-md text-on-surface-variant mb-8">Language learning tips, guides, and updates.</p>

    <?php if (empty($posts)): ?>
      <div class="text-center text-outline py-20">No posts yet.</div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($posts as $post): ?>
          <a href="?page=post&slug=<?= urlencode($post['slug']) ?>"
             class="block bg-surface-container border border-outline-variant/20 hover:border-outline-variant rounded-2xl p-6 transition">
            <h2 class="font-headline-sm text-headline-sm text-on-surface mb-1"><?= htmlspecialchars($post['title']) ?></h2>
            <div class="text-label-md text-outline"><?= date('M j, Y', strtotime($post['created_at'])) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

</body>
</html>
