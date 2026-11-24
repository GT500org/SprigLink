<?php
declare(strict_types=1);

$fail = 0;
$n = 0;
function check(bool $ok, string $message): void
{
    global $fail, $n;
    $n++;
    if ($ok) {
        echo "ok $n $message\n";
        return;
    }
    $fail++;
    echo "FAIL $n $message\n";
}

$db = sys_get_temp_dir() . '/spriglink-test-' . getmypid() . '.sqlite';
@unlink($db);
putenv('SHORTENER_DB=' . $db);
$_SERVER['HTTP_HOST'] = 'links.example';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.8';

require dirname(__DIR__) . '/app/bootstrap.php';

check(!is_file($db), 'sqlite file is absent before first use');
db();
check(is_file($db) && filesize($db) > 0, 'creates the sqlite file from scratch');
$columns = array_column(db()->query('PRAGMA table_info(links)')->fetchAll(), 'name');
check(in_array('is_private', $columns, true), 'fresh database has an is_private column');

check(normalize_url('https://example.com/a') === 'https://example.com/a', 'accepts https urls');
check(normalize_url('javascript:alert(1)') === null, 'rejects javascript urls');
check(normalize_url('http://example.com/a b') === null, 'rejects spaces');
check(normalize_url('https://user:pass@example.com/x') === 'https://user:pass@example.com/x', 'keeps userinfo');

check(short_url('ab_1', false) === 'https://links.example/?c=ab_1', 'query short link needs no rewrite');
check(short_url('ab_1', true) === 'https://links.example/ab_1', 'pretty short link');

$edge = parse_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0');
check($edge['browser'] === 'Edge' && $edge['os'] === 'Windows 10/11' && $edge['is_bot'] === 0, 'parses Edge on Windows');
$bot = parse_user_agent('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
check($bot['is_bot'] === 1 && $bot['device'] === 'Bot', 'flags Googlebot');
$phone = parse_user_agent('Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1');
check($phone['browser'] === 'Safari' && $phone['device'] === 'Mobile' && $phone['os'] === 'iOS', 'parses iPhone Safari');
$android = parse_user_agent('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36');
check($android['browser'] === 'Chrome' && $android['device'] === 'Mobile' && str_starts_with($android['os'], 'Android'), 'parses Android Chrome');

$bad = save_link(null, ['url' => 'notaurl', 'code' => '']);
check($bad['ok'] === false, 'rejects a bad destination');

$reserved = save_link(null, ['url' => 'https://example.com', 'code' => 'admin']);
check($reserved['ok'] === false, 'rejects reserved codes');

$created = save_link(null, [
    'url' => 'https://example.com/docs',
    'title' => 'Docs',
    'code' => '',
    'expires_preset' => '7d',
    'redirect_mode' => 'instant',
]);
check($created['ok'] === true && preg_match('/^[A-Za-z0-9_-]{6}$/', $created['code']) === 1, 'generates a 6 character code');

$custom = save_link(null, [
    'url' => 'https://example.com/custom',
    'code' => 'my-link',
    'expires_preset' => 'never',
    'password' => 'secret',
    'click_limit' => '2',
    'redirect_mode' => 'preview',
]);
check($custom['ok'] === true, 'stores a custom code');
$link = find_link_by_code('my-link');
check($link !== null && link_state($link) === 'live', 'custom link is live');
check(password_verify('secret', (string) $link['password_hash']), 'link password hashes');
check($link['redirect_mode'] === 'preview' && (int) $link['click_limit'] === 2, 'preview mode and click limit stored');

$dupe = save_link(null, ['url' => 'https://example.com/other', 'code' => 'my-link']);
check($dupe['ok'] === false, 'rejects a duplicate code');

$renamed = save_link((int) $link['id'], [
    'url' => 'https://example.com/custom',
    'code' => 'renamed',
    'title' => 'Docs',
    'expires_preset' => 'never',
    'keep_alias' => '1',
    'is_active' => '1',
    'click_limit' => '2',
    'redirect_mode' => 'instant',
    'status_code' => '302',
]);
check($renamed['ok'] === true, 'renames a code');
check(find_link_by_code('my-link')['code'] === 'renamed', 'old code stays as an alias');

$expired = save_link(null, [
    'url' => 'https://example.com/old',
    'code' => 'old',
    'expires_preset' => 'custom',
    'expires_at' => '2020-01-01T00:00',
]);
check($expired['ok'] === true && link_state(find_link_by_code('old')) === 'expired', 'past custom date expires the link');

db()->prepare('UPDATE links SET clicks = 2 WHERE code = ?')->execute(['renamed']);
$limited = find_link_by_code('renamed');
check(link_state($limited) === 'limited', 'click limit stops the link');

record_click(find_link_by_code('old'));
$after = find_link_by_id((int) find_link_by_code('old')['id']);
check((int) $after['clicks'] === 1, 'a visit increments the counter');
$hit = db()->query('SELECT * FROM clicks ORDER BY id DESC LIMIT 1')->fetch();
check($hit['browser'] === 'Chrome' && $hit['country'] === 'Private network', 'visit stores browser and private-network place');

$off = save_link((int) $after['id'], [
    'url' => 'https://example.com/old',
    'code' => 'old',
    'expires_preset' => 'never',
    'is_active' => '',
]);
check($off['ok'] === true && link_state(find_link_by_code('old')) === 'disabled', 'turning a link off wins over expiry');

$listed = array_column(public_links(), 'code');
check(in_array('renamed', $listed, true), 'unmarked links are public');
$hidden = save_link(null, [
    'url' => 'https://example.com/hidden',
    'code' => 'hideme',
    'is_private' => '1',
    'expires_preset' => 'never',
]);
check($hidden['ok'] === true && (int) find_link_by_code('hideme')['is_private'] === 1, 'stores a private link');
check(!in_array('hideme', array_column(public_links(), 'code'), true), 'private links stay off the public list');
$opened = save_link((int) find_link_by_code('hideme')['id'], [
    'url' => 'https://example.com/hidden',
    'code' => 'hideme',
    'expires_preset' => 'never',
    'is_active' => '1',
]);
check($opened['ok'] === true && (int) find_link_by_code('hideme')['is_private'] === 0, 'clearing private publishes the link');
check(in_array('hideme', array_column(public_links(), 'code'), true), 'a published link returns to the homepage list');
check(show_public_links() === true, 'homepage listing defaults to on');
set_show_public_links(false);
check(show_public_links() === false, 'settings can hide the homepage list');
set_show_public_links(true);
check(friendly_urls() === false, 'short links default to the normal form');
set_friendly_urls(true);
check(short_url('ab_1') === 'https://links.example/ab_1', 'friendly setting is what gets copied');
check(short_url('ab_1', false) === 'https://links.example/?c=ab_1', 'normal form can still be built explicitly');
set_friendly_urls(false);
check(short_url('ab_1') === 'https://links.example/?c=ab_1', 'normal setting restores query links');

$wipe = save_link(null, [
    'url' => 'https://example.com/stats-wipe',
    'code' => 'wipe',
    'expires_preset' => 'never',
    'click_limit' => '1',
]);
check($wipe['ok'] === true, 'creates a link to clear stats for');
record_click(find_link_by_code('wipe'));
check(link_state(find_link_by_code('wipe')) === 'limited', 'one visit reaches the click limit');
$beforeCount = (int) find_link_by_code('wipe')['clicks'];
$removed = clear_visit_details();
check($removed >= 1, 'visit clear reports how many records were removed');
check((int) db()->query('SELECT COUNT(*) FROM clicks')->fetchColumn() === 0, 'the visit log is empty');
$wiped = find_link_by_code('wipe');
check($wiped !== null && (int) $wiped['clicks'] === $beforeCount && $wiped['last_click_at'] === null, 'click count stays when visit details are cleared');
check(link_state($wiped) === 'limited', 'a click limit still uses the remaining count');

$kept = save_link(null, [
    'url' => 'https://example.com/keep-log',
    'code' => 'keeplog',
    'expires_preset' => 'never',
]);
check($kept['ok'] === true, 'creates a link whose visit log should survive a count reset');
record_click(find_link_by_code('keeplog'));
$reset = clear_click_counts();
check($reset >= 1, 'count clear reports how many links were reset');
$keptRow = find_link_by_code('keeplog');
check($keptRow !== null && (int) $keptRow['clicks'] === 0 && $keptRow['last_click_at'] !== null, 'click count resets and the last visit time stays');
$keptHits = (int) db()->query("SELECT COUNT(*) FROM clicks WHERE link_id = " . (int) $keptRow['id'])->fetchColumn();
check($keptHits === 1, 'visit details stay when click counts are cleared');
$wiped = find_link_by_code('wipe');
check(link_state($wiped) === 'live', 'a click limit starts over after counts are cleared');

check(!str_contains((string) file_get_contents(dirname(__DIR__) . '/config.php'), "'db_path'"), 'config.php does not set a database path');
check(cfg('db_path') === $db, 'the database path comes from the environment, not config.php');
$site = save_site_settings([
    'base_url' => 'https://links.example.com/',
    'timezone' => 'America/Indiana/Indianapolis',
    'code_length' => '8',
    'geo_lookup' => '1',
    'hide_referrer' => '1',
    'api_token' => 'secret-token',
]);
check($site['ok'] === true, 'site settings save');
check(cfg('base_url') === 'https://links.example.com' && base_url() === 'https://links.example.com', 'public address drops the trailing slash and is used');
check(cfg('timezone') === 'America/Indiana/Indianapolis' && app_tz()->getName() === 'America/Indiana/Indianapolis', 'timezone setting is used');
check(code_length() === 8 && cfg('geo_lookup') === true && cfg('hide_referrer') === true, 'code length, location lookup, and referrer hiding save');
check(cfg('api_token') === 'secret-token', 'api token saves');
$rejected = save_site_settings([
    'base_url' => 'javascript:alert(1)',
    'timezone' => 'America/Indiana/Indianapolis',
    'code_length' => '8',
    'api_token' => 'secret-token',
]);
check($rejected['ok'] === false && cfg('base_url') === 'https://links.example.com', 'a bad public address is refused and the old one stays');
$cleared = save_site_settings([
    'base_url' => '',
    'timezone' => 'UTC',
    'code_length' => '2',
    'api_token' => "has space",
]);
check($cleared['ok'] === false && cfg('api_token') === 'secret-token', 'a bad token is refused');
$cleared = save_site_settings([
    'base_url' => '',
    'timezone' => 'UTC',
    'code_length' => '6',
    'api_token' => '',
]);
check($cleared['ok'] === true && cfg('base_url') === '' && cfg('geo_lookup') === false && cfg('hide_referrer') === false && cfg('api_token') === '', 'blank address and token turn those features off');
check(code_length() === 6, 'code length can be set back');

if ($fail > 0) {
    echo "$fail failed\n";
    exit(1);
}
echo "ALL $n PASSED\n";
@unlink($db);
