<?php if (!empty($flash)): ?>
  <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>" role="status"><?= e((string) ($flash['text'] ?? '')) ?></div>
<?php endif ?>
