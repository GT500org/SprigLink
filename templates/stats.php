<?php
$primary = short_url((string) $link['code']);
$queryUrl = short_url((string) $link['code'], false);
$prettyUrl = short_url((string) $link['code'], true);
$other = $primary === $queryUrl ? $prettyUrl : $queryUrl;
$otherLabel = $primary === $queryUrl ? 'Friendly form. Needs the nginx or Apache rewrite in Settings.' : 'Normal form. Works with no rewrite rule.';
?>
<div class="page-head">
  <div>
    <p class="eyebrow">Stats</p>
    <h1><code class="display-code"><?= e($link['code']) ?></code> <?= status_pill($state) ?><?php if ((int) ($link['is_private'] ?? 0) === 1): ?> <span class="mini">private</span><?php endif ?></h1>
    <?php if ($link['title'] !== ''): ?><p class="quiet"><?= e($link['title']) ?></p><?php endif ?>
  </div>
  <div class="row-actions">
    <a class="btn btn-ghost" href="<?= e(page_url(['page' => 'edit', 'id' => $link['id']])) ?>">Edit</a>
    <a class="btn btn-ghost" href="<?= e(page_url(['page' => 'export', 'id' => $link['id']])) ?>">Export CSV</a>
  </div>
</div>

<section class="card link-hero">
  <div>
    <span class="kicker">Short link</span>
    <p class="short-url"><?= e($primary) ?></p>
    <p class="quiet"><?= e($otherLabel) ?>: <?= e($other) ?></p>
  </div>
  <div class="row-actions">
    <button class="btn btn-primary" type="button" data-copy="<?= e($primary) ?>"><span class="btn-label">Copy</span></button>
    <button class="btn btn-ghost" type="button" data-qr-open="<?= e($primary) ?>">QR code</button>
    <a class="btn btn-ghost" href="<?= e(page_url(['c' => $link['code']])) ?>">Open</a>
  </div>
</section>

<p class="dest-line">Destination <a href="<?= e($link['url']) ?>" target="_blank" rel="noreferrer"><?= e($link['url']) ?></a></p>

<section class="stats" aria-label="Link totals">
  <article class="card stat"><span>Human visits</span><strong><?= number_format($human) ?></strong><em><?= number_format($botCount) ?> bot hits hidden</em></article>
  <article class="card stat"><span>Unique IPs</span><strong><?= number_format($unique) ?></strong><em>humans only</em></article>
  <article class="card stat"><span>Last 7 days</span><strong><?= number_format($week) ?></strong><em><?= $includeBots ? 'including bots' : 'humans' ?></em></article>
  <article class="card stat"><span>Limit</span><strong><?= $link['click_limit'] === null ? '—' : number_format((int) $link['click_limit']) ?></strong><em><?= $link['expires_at'] ? 'Expires ' . e(fmt_time((string) $link['expires_at'])) : 'No expiry' ?></em></article>
</section>

<p class="quiet bot-toggle">
  <?php if ($includeBots): ?>
    Bots are included. <a href="<?= e(page_url(['page' => 'stats', 'id' => $link['id']])) ?>">Hide bots</a>
  <?php else: ?>
    Known crawlers and link previews are stored but left out of the charts. <a href="<?= e(page_url(['page' => 'stats', 'id' => $link['id'], 'bots' => '1'])) ?>">Include bots</a>
  <?php endif ?>
</p>

<section class="card">
  <h2>Last 30 days</h2>
  <?= chart_svg($series) ?>
</section>

<section class="breakdowns">
  <article class="card"><h2>Browsers</h2><?= bars_html($browsers) ?></article>
  <article class="card"><h2>Systems</h2><?= bars_html($oses) ?></article>
  <article class="card"><h2>Devices</h2><?= bars_html($devices) ?></article>
  <article class="card"><h2>Places</h2><?= bars_html($countries) ?></article>
  <article class="card"><h2>Referrers</h2><?= bars_html($referrers) ?></article>
</section>

<section class="card">
  <h2>Latest visits</h2>
  <?php $clicks = $recent; $showCode = false; require __DIR__ . '/click-table.php'; ?>
</section>

<dialog id="qr-dialog">
  <h2>QR code</h2>
  <div id="qr-box"></div>
  <p class="url-break"><?= e($primary) ?></p>
  <button class="btn btn-ghost" type="button" data-qr-close>Close</button>
</dialog>
