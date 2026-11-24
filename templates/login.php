<h1>Sign in</h1>
<p class="lede">Links are created in the admin panel, not on the public page.</p>
<?php if ($error !== ''): ?>
  <div class="flash flash-error" role="alert"><?= e($error) ?></div>
<?php endif ?>
<?php if (using_default_password()): ?>
  <div class="flash flash-info">The password in <code>config.php</code> is still the shipped default. Sign in with <strong>admin</strong> / <strong>change-this-password</strong>, then change it before this host is public.</div>
<?php endif ?>
<form class="stack-form" method="post" action="<?= e(page_url(['page' => 'login'])) ?>">
  <?= csrf_field() ?>
  <label class="field">
    <span>Username</span>
    <input name="username" autocomplete="username" required autofocus>
  </label>
  <label class="field">
    <span>Password</span>
    <input name="password" type="password" autocomplete="current-password" required>
  </label>
  <button class="btn btn-primary" type="submit">Sign in</button>
</form>
