<?php if (empty($clicks)): ?>
  <p class="quiet">No visits yet.</p>
<?php else: ?>
  <div class="table-scroll">
    <table class="data">
      <thead>
        <tr>
          <?php if (!empty($showCode)): ?><th>Link</th><?php endif ?>
          <th>When</th>
          <th>Where</th>
          <th>Client</th>
          <th>From</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clicks as $hit): ?>
          <tr>
            <?php if (!empty($showCode)): ?>
              <td data-label="Link"><a href="<?= e(page_url(['page' => 'stats', 'id' => $hit['link_id']])) ?>"><code><?= e($hit['link_code']) ?></code></a></td>
            <?php endif ?>
            <td data-label="When"><?= e(fmt_time((string) $hit['clicked_at'])) ?><?php if ((int) $hit['is_bot'] === 1): ?> <span class="pill pill-disabled">Bot</span><?php endif ?></td>
            <td data-label="Where"><?= e(place_label($hit['country'], $hit['region'], $hit['city'])) ?></td>
            <td data-label="Client"><?= e(trim($hit['browser'] . ' ' . $hit['browser_version'])) ?> · <?= e((string) $hit['os']) ?> · <?= e((string) $hit['device']) ?></td>
            <td data-label="From"><?= e($hit['referrer_host'] !== '' ? $hit['referrer_host'] : 'Direct') ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
