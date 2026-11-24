<h1><?= e($heading) ?></h1>
<p class="lede"><?= e($message) ?></p>
<p class="home-actions"><a class="btn btn-ghost" href="<?= e($layout === 'admin' ? page_url(['page' => 'links']) : home_url()) ?>"><?= $layout === 'admin' ? 'Back to links' : 'Home' ?></a></p>
