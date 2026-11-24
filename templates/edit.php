<div class="page-head">
  <div>
    <p class="eyebrow">Edit</p>
    <h1><code class="display-code"><?= e($link['code']) ?></code></h1>
    <p class="quiet"><?= number_format((int) $link['clicks']) ?> recorded visits · created <?= e(fmt_time((string) $link['created_at'])) ?></p>
  </div>
  <a class="btn btn-ghost" href="<?= e(page_url(['page' => 'stats', 'id' => $link['id']])) ?>">View stats</a>
</div>

<section class="card">
  <?php if (!empty($errors)): ?>
    <div class="flash flash-error" role="alert">
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul>
    </div>
  <?php endif ?>
  <form class="form-grid" method="post" action="<?= e(page_url(['page' => 'edit', 'id' => $link['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
    <?php $mode = 'edit'; require __DIR__ . '/link-form.php'; ?>
    <div class="span-2">
      <button class="btn btn-primary" type="submit">Save changes</button>
    </div>
  </form>
</section>

<section class="card danger-zone">
  <h2>Delete</h2>
  <p class="quiet">Removes the short code, its aliases, and every recorded visit. This cannot be undone.</p>
  <form method="post" action="<?= e(page_url(['page' => 'edit', 'id' => $link['id']])) ?>" data-confirm="Delete this short link and its stats?">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
    <button class="btn btn-danger" type="submit">Delete link</button>
  </form>
</section>
