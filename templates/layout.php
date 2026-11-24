<?php
$flash = flash();
$brand = '<span class="mark" aria-hidden="true"></span>SprigLink';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <title><?= e($title) ?><?= $title === 'SprigLink' ? '' : ' · SprigLink' ?></title>
  <link rel="icon" href="<?= e(asset_url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="<?= $layout === 'admin' ? 'is-admin' : 'is-public' ?>">
<?php if ($layout === 'admin'): ?>
  <div class="shell">
    <aside class="rail">
      <a class="brand" href="<?= e(page_url(['page' => 'admin'])) ?>"><?= $brand ?></a>
      <nav class="rail-nav" aria-label="Admin">
        <a class="nav-link<?= $current === 'admin' ? ' is-active' : '' ?>" href="<?= e(page_url(['page' => 'admin'])) ?>"<?= $current === 'admin' ? ' aria-current="page"' : '' ?>>Dashboard</a>
        <a class="nav-link<?= $current === 'links' ? ' is-active' : '' ?>" href="<?= e(page_url(['page' => 'links'])) ?>"<?= $current === 'links' ? ' aria-current="page"' : '' ?>>Links</a>
        <a class="nav-link<?= $current === 'settings' ? ' is-active' : '' ?>" href="<?= e(page_url(['page' => 'settings'])) ?>"<?= $current === 'settings' ? ' aria-current="page"' : '' ?>>Settings</a>
      </nav>
      <form class="rail-foot" method="post" action="<?= e(page_url(['page' => 'logout'])) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-block" type="submit">Sign out</button>
      </form>
    </aside>
    <div class="main">
      <header class="topbar">
        <a class="brand brand-sm" href="<?= e(page_url(['page' => 'admin'])) ?>"><?= $brand ?></a>
        <nav class="top-nav" aria-label="Admin">
          <a class="nav-link<?= $current === 'admin' ? ' is-active' : '' ?>" href="<?= e(page_url(['page' => 'admin'])) ?>">Dashboard</a>
          <a class="nav-link<?= $current === 'links' ? ' is-active' : '' ?>" href="<?= e(page_url(['page' => 'links'])) ?>">Links</a>
          <a class="nav-link<?= $current === 'settings' ? ' is-active' : '' ?>" href="<?= e(page_url(['page' => 'settings'])) ?>">Settings</a>
        </nav>
        <form method="post" action="<?= e(page_url(['page' => 'logout'])) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-ghost" type="submit">Sign out</button>
        </form>
      </header>
      <div class="wrap">
        <?php require __DIR__ . '/flash.php'; ?>
        <?= $content ?>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="public-wrap">
    <header class="public-head">
      <a class="brand" href="<?= e(home_url()) ?>"><?= $brand ?></a>
    </header>
    <main class="public-main">
      <?php require __DIR__ . '/flash.php'; ?>
      <?= $content ?>
    </main>
    <footer class="public-foot">
      <span>Self-hosted shortener</span>
      <a href="<?= e(page_url(['page' => logged_in() ? 'admin' : 'login'])) ?>"><?= logged_in() ? 'Open admin' : 'Admin' ?></a>
    </footer>
  </div>
<?php endif ?>
<?php if (!empty($useQr)): ?>
  <script src="<?= e(asset_url('assets/qrcode.min.js')) ?>" defer></script>
<?php endif ?>
<script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
</body>
</html>
