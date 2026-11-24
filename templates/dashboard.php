<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="quiet">Create a link, then share the short address. Stats update when someone opens it.</p>
  </div>
</div>

<?php if ($samples > 0): ?>
  <div class="banner">
    <p>Sample links are loaded so you can click through the stats. They are marked “Sample” in the notes.</p>
    <form method="post" action="<?= e(page_url(['page' => 'admin'])) ?>" data-confirm="Delete every link whose notes start with “Sample ·”? Your other links stay.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="purge_samples">
      <button class="btn btn-ghost" type="submit">Remove sample links</button>
    </form>
  </div>
<?php endif ?>

<section class="card">
  <h2>New link</h2>
  <?php if (!empty($errors)): ?>
    <div class="flash flash-error" role="alert">
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul>
    </div>
  <?php endif ?>
  <form class="form-grid" method="post" action="<?= e(page_url(['page' => 'admin'])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <?php $mode = 'create'; require __DIR__ . '/link-form.php'; ?>
    <div class="span-2">
      <button class="btn btn-primary" type="submit">Create short link</button>
    </div>
  </form>
</section>

<section class="stats" aria-label="Totals">
  <article class="card stat">
    <span>Today</span>
    <strong><?= number_format($today) ?></strong>
    <em>human visits</em>
  </article>
  <article class="card stat">
    <span>Last 7 days</span>
    <strong><?= number_format($week) ?></strong>
    <em>human visits</em>
  </article>
  <article class="card stat">
    <span>Live links</span>
    <strong><?= number_format($live) ?></strong>
    <em><?= number_format($total_links) ?> total</em>
  </article>
  <article class="card stat">
    <span>All human visits</span>
    <strong><?= number_format($all) ?></strong>
    <em><?= number_format($bots) ?> bot hits stored separately</em>
  </article>
</section>

<section class="split">
  <article class="card">
    <h2>Last 14 days</h2>
    <?= chart_svg($series) ?>
  </article>
  <article class="card">
    <h2>Top links, 30 days</h2>
    <?php if ($top === []): ?>
      <p class="quiet">No visits yet.</p>
    <?php else: ?>
      <ul class="rank">
        <?php foreach ($top as $item): ?>
          <li>
            <a href="<?= e(page_url(['page' => 'stats', 'id' => $item['id']])) ?>"><code><?= e($item['code']) ?></code></a>
            <span><?= e($item['title'] !== '' ? $item['title'] : clip((string) $item['url'], 42)) ?></span>
            <b class="num"><?= number_format((int) $item['recent']) ?></b>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </article>
</section>

<section class="card">
  <div class="section-head">
    <h2>Recent visits</h2>
    <a class="text-link" href="<?= e(page_url(['page' => 'export'])) ?>">Export CSV</a>
  </div>
  <?php $clicks = $recent; $showCode = true; require __DIR__ . '/click-table.php'; ?>
</section>
