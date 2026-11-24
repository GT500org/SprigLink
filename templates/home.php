<div class="home-hero">
  <h1>Short links, kept on <em>your server</em>.</h1>
  <p class="lede">SprigLink turns a long address into a short one.<?php if ($directoryOn): ?> Links you leave public are listed below, with the short address and the full one.<?php else: ?> Visitors only land somewhere when you give them a link.<?php endif ?></p>
  <p class="home-actions"><a class="btn btn-primary" href="<?= e(page_url(['page' => logged_in() ? 'admin' : 'login'])) ?>"><?= logged_in() ? 'Open admin' : 'Admin sign in' ?></a></p>
</div>

<?php if ($directoryOn && $publicLinks !== []): ?>
  <section class="directory" aria-label="Public links">
    <div class="section-head">
      <h2>Public links</h2>
      <p class="quiet"><?= number_format(count($publicLinks)) ?> <?= count($publicLinks) === 1 ? 'link' : 'links' ?></p>
    </div>
    <ul class="directory-list">
      <?php foreach ($publicLinks as $link): ?>
        <?php
          $state = link_state($link);
          $short = short_url((string) $link['code']);
          $label = trim((string) $link['title']) !== '' ? (string) $link['title'] : url_host((string) $link['url']);
        ?>
        <li class="card dir-item">
          <div class="dir-top">
            <h3><?= e($label) ?></h3>
            <div class="dir-meta">
              <?= status_pill($state) ?>
              <?php if (!empty($link['has_password'])): ?><span class="mini">password</span><?php endif ?>
            </div>
          </div>
          <div class="dir-row">
            <span>Short</span>
            <a class="dir-url" href="<?= e($short) ?>"><code><?= e($short) ?></code></a>
            <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($short) ?>"><span class="btn-label">Copy</span></button>
          </div>
          <div class="dir-row">
            <span>Full</span>
            <a class="dir-url" href="<?= e($link['url']) ?>" rel="noreferrer" target="_blank"><?= e($link['url']) ?></a>
          </div>
        </li>
      <?php endforeach ?>
    </ul>
  </section>
<?php elseif ($directoryOn): ?>
  <p class="quiet home-note">No public links yet. New links show here unless you mark them private.</p>
<?php endif ?>
