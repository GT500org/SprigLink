<div class="page-head">
  <div>
    <h1>Links</h1>
    <p class="quiet"><?= number_format($total) ?> <?= $total === 1 ? 'link' : 'links' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?>.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(page_url(['page' => 'admin'])) ?>">New link</a>
</div>

<form class="filters" method="get" action="index.php">
  <input type="hidden" name="page" value="links">
  <label class="grow">
    <span class="sr-only">Search</span>
    <input name="q" value="<?= e($q) ?>" placeholder="Search code, URL, title, notes">
  </label>
  <label>
    <span class="sr-only">Status</span>
    <select name="status">
      <?php foreach (['all' => 'All statuses', 'live' => 'Live', 'expired' => 'Expired', 'limited' => 'Limit reached', 'disabled' => 'Off'] as $val => $label): ?>
        <option value="<?= e($val) ?>"<?= sel($status, $val) ?>><?= e($label) ?></option>
      <?php endforeach ?>
    </select>
  </label>
  <label>
    <span class="sr-only">Sort</span>
    <select name="sort">
      <option value="new"<?= sel($sort, 'new') ?>>Newest</option>
      <option value="clicks"<?= sel($sort, 'clicks') ?>>Most clicks</option>
      <option value="code"<?= sel($sort, 'code') ?>>Code</option>
    </select>
  </label>
  <button class="btn btn-ghost" type="submit">Filter</button>
</form>

<?php if ($rows === []): ?>
  <div class="card empty">
    <p><?= ($q !== '' || $status !== 'all') ? 'Nothing matches that filter.' : 'No links yet.' ?></p>
    <a class="btn btn-primary" href="<?= e(page_url(['page' => 'admin'])) ?>">Create the first one</a>
  </div>
<?php else: ?>
  <div class="table-scroll">
    <table class="data links">
      <thead>
        <tr>
          <th>Code</th>
          <th>Destination</th>
          <th>Status</th>
          <th>Clicks</th>
          <th>Last visit</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $link): ?>
          <?php $state = link_state($link); $short = short_url((string) $link['code']); ?>
          <tr>
            <td data-label="Code">
              <a class="code-link" href="<?= e(page_url(['page' => 'stats', 'id' => $link['id']])) ?>"><code><?= e($link['code']) ?></code></a>
              <?php if ((int) ($link['is_private'] ?? 0) === 1): ?><span class="mini">private</span><?php endif ?>
              <?php if (!empty($link['password_hash'])): ?><span class="mini" title="Password protected">locked</span><?php endif ?>
              <?php if ($link['redirect_mode'] === 'preview'): ?><span class="mini">preview</span><?php endif ?>
              <?php if ($link['title'] !== ''): ?><div class="sub"><?= e($link['title']) ?></div><?php endif ?>
            </td>
            <td data-label="Destination"><a class="dest" href="<?= e($link['url']) ?>" rel="noreferrer" target="_blank"><?= e(clip((string) $link['url'], 64)) ?></a></td>
            <td data-label="Status"><?= status_pill($state) ?></td>
            <td data-label="Clicks" class="num"><?= number_format((int) $link['clicks']) ?></td>
            <td data-label="Last visit"><?= e(fmt_time($link['last_click_at'] ?? null)) ?></td>
            <td data-label="Actions">
              <div class="row-actions">
                <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($short) ?>"><span class="btn-label">Copy</span></button>
                <a class="btn btn-ghost btn-sm" href="<?= e(page_url(['page' => 'edit', 'id' => $link['id']])) ?>">Edit</a>
                <form method="post" action="<?= e(page_url(['page' => 'links'])) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $link['id'] ?>">
                  <input type="hidden" name="q" value="<?= e($q) ?>">
                  <input type="hidden" name="status" value="<?= e($status) ?>">
                  <input type="hidden" name="sort" value="<?= e($sort) ?>">
                  <input type="hidden" name="p" value="<?= (int) $page ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $link['is_active'] === 1 ? 'Turn off' : 'Turn on' ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php if ($page > 1): ?><a href="<?= e(links_page_url(['p' => $page - 1])) ?>">Previous</a><?php endif ?>
      <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
      <?php if ($page < $pages): ?><a href="<?= e(links_page_url(['p' => $page + 1])) ?>">Next</a><?php endif ?>
    </nav>
  <?php endif ?>
<?php endif ?>
