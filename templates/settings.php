<?php
$base = base_path();
$nginxLocation = $base === '' ? '/' : $base . '/';
$nginxFallback = ($base === '' ? '' : $base) . '/index.php?$query_string';
$nginxDeny = <<<'NGINX'
# Keep these. They stop visitors downloading the password file,
# the database, and the program code.
location ~ ^/(app|templates|data|examples|tests)/ { deny all; }
location = /config.php { deny all; }
location = /router.php { deny all; }
location ~* \.(sqlite3?|db)(-wal|-shm)?$ { deny all; }
NGINX;
$nginxSnippet = <<<NGINX
# Inside server { }, in this order.
# Add the first block. Leave the PHP block you already have after it.

# 1. New block. Put this FIRST.
location {$nginxLocation} {
    try_files \$uri \$uri/ {$nginxFallback};
}

# 2. PHP block. This one is already in your file. It stays AFTER the block above.
location ~ \.php\$ {
    # do not change the lines already in here
}
NGINX;
$apacheBase = $base === '' ? '/' : $base . '/';
$apacheSnippet = <<<APACHE
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase {$apacheBase}
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
APACHE;
$normalExample = short_url('abc123', false);
$friendlyExample = short_url('abc123', true);
?>
<div class="page-head">
  <div>
    <h1>Settings</h1>
    <p class="quiet">Homepage, short-link format, and the site options below are saved here. The sign-in password still lives in the config file.</p>
  </div>
</div>

<section class="card">
  <h2>Homepage</h2>
  <p class="quiet">Links marked private never appear here. Turn this off to hide every link from the homepage, public ones included.</p>
  <form method="post" action="<?= e(page_url(['page' => 'settings'])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <label class="check"><input type="checkbox" name="show_public_links" value="1"<?= chk(!empty($showPublic)) ?>> Show public links on the homepage</label>
    <p class="settings-save"><button class="btn btn-primary" type="submit">Save</button></p>
  </form>
</section>

<section class="card">
  <h2>Short link format</h2>
  <p class="quiet">This changes the address SprigLink shows and copies. Both forms still open a link once the server is set up. Friendly addresses 404 until nginx or Apache rewrites them.</p>
  <form method="post" action="<?= e(page_url(['page' => 'settings'])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_link_style">
    <fieldset class="choice-list">
      <legend class="sr-only">How short links are written</legend>
      <label class="choice">
        <input type="radio" name="link_style" value="normal"<?= chk(empty($friendly)) ?>>
        <span>
          <strong>Normal</strong>
          <code><?= e($normalExample) ?></code>
          <em>Works on a plain PHP site. No rewrite.</em>
        </span>
      </label>
      <label class="choice">
        <input type="radio" name="link_style" value="friendly"<?= chk(!empty($friendly)) ?>>
        <span>
          <strong>Friendly</strong>
          <code><?= e($friendlyExample) ?></code>
          <em>Rewritten by nginx or Apache. Add the rules below first, then save this.</em>
        </span>
      </label>
    </fieldset>
    <p class="settings-save"><button class="btn btn-primary" type="submit">Save format</button></p>
  </form>
</section>

<section class="card">
  <h2>Site</h2>
  <p class="quiet">These start out as the values in the config file. Once you save this form, what you enter here is what SprigLink uses.</p>
  <form method="post" action="<?= e(page_url(['page' => 'settings'])) ?>" class="settings-fields">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_site">
    <div class="field">
      <label for="base_url">Public address</label>
      <input id="base_url" name="base_url" type="text" inputmode="url" maxlength="200" spellcheck="false" placeholder="https://links.example.com" value="<?= e((string) cfg('base_url', '')) ?>">
      <p class="hint">The address of this site, with no slash at the end. Leave it blank and SprigLink uses the address you are visiting now. Fill it in only when that guess is wrong, for example the site is https but the server thinks it is http, or copied links should use a different host name. In use now: <code><?= e(base_url()) ?></code></p>
    </div>
    <div class="field">
      <label for="timezone">Timezone</label>
      <select id="timezone" name="timezone">
        <?php
        $tzNow = (string) cfg('timezone', 'UTC');
        $tzGroups = timezone_groups();
        $seen = false;
        foreach ($tzGroups as $group => $zones) {
            echo '<optgroup label="' . e($group) . '">';
            foreach ($zones as $zone) {
                if ($zone === $tzNow) {
                    $seen = true;
                }
                echo '<option value="' . e($zone) . '"' . sel($tzNow, $zone) . '>' . e($zone) . '</option>';
            }
            echo '</optgroup>';
        }
        if (!$seen && $tzNow !== '') {
            echo '<option value="' . e($tzNow) . '" selected>' . e($tzNow) . '</option>';
        }
        ?>
      </select>
      <p class="hint">Dates you type, expiry times, and the way visit times are shown all use this timezone. Pick the city closest to you.</p>
    </div>
    <div class="field">
      <label for="code_length">Generated code length</label>
      <input id="code_length" name="code_length" type="number" min="3" max="16" step="1" inputmode="numeric" required value="<?= e((string) code_length()) ?>">
      <p class="hint">How many characters a new random code gets. 6 is a good default. Codes you already created stay as they are. You can still type a custom code of a different length.</p>
    </div>
    <label class="check"><input type="checkbox" name="geo_lookup" value="1"<?= chk((bool) cfg('geo_lookup', true)) ?>> Look up visitor location</label>
    <p class="hint">When this is on, opening the dashboard or a stats page asks ip-api.com where public IP addresses are (country, region, and city). No account is required. Private addresses stay labeled “Private network”. Browser, device, and referrer are stored either way. Turn it off if you do not want those lookups.</p>
    <label class="check"><input type="checkbox" name="hide_referrer" value="1"<?= chk((bool) cfg('hide_referrer', false)) ?>> Hide the short link from destination sites</label>
    <p class="hint">When someone opens a short link, the next website can usually see that short address. Turn this on so the next site is not told which short link sent the visitor. SprigLink still records the visit.</p>
    <div class="field">
      <label for="api_token">API token <span class="opt">blank = off</span></label>
      <input id="api_token" name="api_token" type="text" maxlength="128" spellcheck="false" autocomplete="off" autocapitalize="off" value="<?= e((string) cfg('api_token', '')) ?>">
      <p class="hint">A secret that lets another program create links by posting JSON to <code>index.php?page=api</code>. Leave it blank to keep that off. Anyone who has the token can add links, so make it long and do not share it. The README has a copy-paste example.</p>
    </div>
    <p class="settings-save"><button class="btn btn-primary" type="submit">Save site settings</button></p>
  </form>
</section>

<section class="card settings-list">
  <dl>
    <div><dt>Config file</dt><dd><code><?= e(config_file()) ?></code></dd></div>
    <div><dt>Database</dt><dd><code><?= e((string) cfg('db_path')) ?></code></dd></div>
    <div><dt>Move either file</dt><dd>Edit <code>$spriglinkConfigFile</code> and <code>$spriglinkDatabaseFile</code> at the top of <code>index.php</code>. On shared hosting, point them outside the website folder when you can.</dd></div>
    <div><dt>Short link format</dt><dd><?= !empty($friendly) ? 'Friendly paths. Copied links look like /code and need the rewrite below.' : 'Normal query links. Copied links look like /?c=code and need no rewrite.' ?></dd></div>
    <div><dt>Trust proxy headers</dt><dd><?= cfg('trust_proxy', false) ? 'On' : 'Off. Leave this off unless a proxy you control sets the forwarded headers. It is still set in the config file.' ?></dd></div>
    <div><dt>Admin user</dt><dd><?= e((string) cfg('admin_username', 'admin')) ?></dd></div>
    <div><dt>Password</dt><dd><?= using_default_password() ? 'Still the default. Change it in config.php.' : 'Custom value set in config.php. It is not shown here.' ?></dd></div>
  </dl>
</section>

<section class="card server-help">
  <h2>Friendly URLs on the server</h2>
  <p>The website folder is the one that contains <code>index.php</code>. There is no separate public folder. Normal links need none of the rewrite below. A request for <code>/?c=abc123</code> is already served because <code>index.php</code> is the directory index.</p>
  <p>Choose <strong>Friendly</strong> above only after one of the blocks below is in place. Real files, including <code>index.php</code>, keep working. Query links keep working too.</p>
  <?php if ($base !== ''): ?>
    <p class="quiet">This install is in <code><?= e($base) ?></code>. The samples use that path.</p>
  <?php else: ?>
    <p class="quiet">This install is at the site root. Example friendly link: <code><?= e($friendlyExample) ?></code></p>
  <?php endif ?>

  <div class="server-block">
    <h3>NGINX</h3>
    <ol class="steps">
      <li>Set <code>root</code> to the folder that contains <code>index.php</code>, and keep <code>index index.php;</code>.</li>
      <li>Visitors must not be able to download <code>config.php</code> or the database. Inside <code>server { }</code>, keep the blocks below. A copy is in <code>examples/nginx.conf.example</code>. They can sit anywhere in that section. Do not delete the PHP block.</li>
    </ol>
    <pre><?= e($nginxDeny) ?></pre>
    <ol class="steps" start="3">
      <li>Friendly links only. Find the block that starts with <code>location ~ \.php$</code>. That is the PHP block. Leave every line inside it as it is.</li>
      <li>Paste the new block <strong>above</strong> that PHP block, still inside <code>server { }</code>. The new block comes first. The PHP block stays after it.</li>
      <li>Save the file. Check it, then reload: <code>sudo nginx -t && sudo systemctl reload nginx</code></li>
      <li>Come back here and choose Friendly.</li>
    </ol>
    <pre><?= e($nginxSnippet) ?></pre>
    <p>If the visitor asks for a real file, nginx serves that file. If they ask for a short path such as <code>/abc123</code>, and no file has that name, nginx hands the request to SprigLink.</p>
    <p class="quiet">You do not need the second block for normal <code>/?c=</code> links. Keep the deny blocks either way.</p>
  </div>

  <div class="server-block">
    <h3>Apache</h3>
    <ol class="steps">
      <li>On shared hosting, upload the app so <code>index.php</code> is directly in the web folder (often <code>public_html</code>). You do not use a <code>public</code> subfolder.</li>
      <li>The <code>.htaccess</code> next to <code>index.php</code> already refuses <code>config.php</code>, the database, and the program folders. Leave those lines in place.</li>
      <li>To move the password file or the database out of the web folder, change the two path lines at the top of <code>index.php</code>.</li>
      <li>Friendly links only. Turn on rewrite with <code>sudo a2enmod rewrite</code>, then reload Apache. The site directory needs <code>AllowOverride FileInfo</code> (or <code>All</code>), or Apache ignores <code>.htaccess</code>.</li>
      <li>Add the block below to that same <code>.htaccess</code>, <strong>under</strong> the deny rules. Do not delete the deny rules. The same text is in <code>examples/apache-htaccess.txt</code>.</li>
      <li>Reload Apache, then come back here and choose Friendly.</li>
    </ol>
    <pre><?= e($apacheSnippet) ?></pre>
    <p class="quiet">If you cannot use <code>.htaccess</code>, paste the <code>RewriteEngine</code>, <code>RewriteBase</code>, <code>RewriteCond</code>, and <code>RewriteRule</code> lines inside the virtual host instead, and also deny the private paths there. The nginx example shows which paths to close.</p>
  </div>
</section>

<?php require __DIR__ . '/clear-stats.php'; ?>
