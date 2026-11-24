<p class="eyebrow">Check this link</p>
<h1><?= e($link['title'] !== '' ? $link['title'] : url_host((string) $link['url'])) ?></h1>
<p class="lede">You are about to leave this shortener and open the address below.</p>
<p class="url-break"><?= e((string) $link['url']) ?></p>
<p class="home-actions">
  <a class="btn btn-primary" href="<?= e(page_url(['c' => $link['code'], 'go' => '1'])) ?>">Continue</a>
  <a class="btn btn-ghost" href="<?= e(home_url()) ?>">Stay here</a>
</p>
