<section class="card danger-zone" id="clear-statistics">
  <h2>Clear visit details</h2>
  <p>This deletes the visit log for <strong>every link</strong>: times, browsers, devices, places, referrers, and the charts built from them. Last-visit times are cleared too. <strong>Click counts stay</strong>, and click limits are not affected. The short links themselves are not deleted. This cannot be undone.</p>
  <form method="post" action="<?= e(page_url(['page' => 'settings'])) ?>" data-confirm="Delete visit details for all links? Times, browsers, devices, places, and referrers will be removed. Click counts will stay. This cannot be undone.">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear_visits">
    <button class="btn btn-danger" type="submit">Clear visit details</button>
  </form>
</section>

<section class="card danger-zone">
  <h2>Clear click counts</h2>
  <p>This sets the click count on <strong>every link</strong> back to zero. Click limits start over. Visit details stay: times, browsers, devices, places, and referrers are not removed. The short links themselves are not deleted. This cannot be undone.</p>
  <form method="post" action="<?= e(page_url(['page' => 'settings'])) ?>" data-confirm="Reset click counts for all links to zero? Click limits will start over. Visit details will stay. This cannot be undone.">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear_counts">
    <button class="btn btn-danger" type="submit">Clear click counts</button>
  </form>
</section>
