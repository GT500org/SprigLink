<p class="eyebrow">Protected link</p>
<h1><?= e($link['title'] !== '' ? $link['title'] : 'This link is locked') ?></h1>
<p class="lede">Enter the password to continue to <?= e(url_host((string) $link['url'])) ?>.</p>
<?php if ($error !== ''): ?>
  <div class="flash flash-error" role="alert"><?= e($error) ?></div>
<?php endif ?>
<form class="stack-form" method="post" action="<?= e(page_url(['c' => $link['code']])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="unlock">
  <label class="field">
    <span>Password</span>
    <input name="password" type="password" autocomplete="current-password" required autofocus>
  </label>
  <button class="btn btn-primary" type="submit">Continue</button>
</form>
