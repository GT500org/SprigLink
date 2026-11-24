<?php
declare(strict_types=1);

const SPRIG_ROOT = __DIR__ . '/..';

date_default_timezone_set('UTC');

function config_file(): string
{
    if (defined('SPRIGLINK_CONFIG_FILE')) {
        $path = (string) constant('SPRIGLINK_CONFIG_FILE');
        if ($path !== '') {
            return assert_local_path($path, 'Config');
        }
    }
    return SPRIG_ROOT . '/config.php';
}

function config_all(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = config_file();
    if (!is_file($path)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Missing config file.\nLooked for: {$path}\nSet \$spriglinkConfigFile at the top of index.php to the full path of that file. A path that does not start with / is not relative to the website folder.\n");
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Config file must return an array.\n");
    }
    unset($loaded['db_path']);
    $dbPath = SPRIG_ROOT . '/data/spriglink.sqlite';
    if (defined('SPRIGLINK_DATABASE_FILE') && (string) constant('SPRIGLINK_DATABASE_FILE') !== '') {
        $dbPath = (string) constant('SPRIGLINK_DATABASE_FILE');
    }
    $envDb = getenv('SHORTENER_DB');
    if (is_string($envDb) && $envDb !== '') {
        $dbPath = $envDb;
    }
    $loaded['db_path'] = assert_local_path($dbPath, 'Database');
    $cfg = $loaded;
    return $cfg;
}

function assert_local_path(string $path, string $label): string
{
    if ($path === '' || str_contains($path, "\0")) {
        throw new RuntimeException($label . ' path is empty or invalid.');
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
        throw new RuntimeException($label . ' path must be a file path, not a URL.');
    }
    return $path;
}

function cfg(string $key, mixed $default = null): mixed
{
    $all = config_all();
    $value = array_key_exists($key, $all) ? $all[$key] : $default;
    if ($key === 'db_path' || !function_exists('site_setting')) {
        return $value;
    }
    return site_setting($key, $value);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_tz(): DateTimeZone
{
    static $tz = null;
    static $name = '';
    $wanted = (string) cfg('timezone', 'UTC');
    if ($tz instanceof DateTimeZone && $name === $wanted) {
        return $tz;
    }
    try {
        $tz = new DateTimeZone($wanted);
        $name = $wanted;
    } catch (Exception) {
        $tz = new DateTimeZone('UTC');
        $name = 'UTC';
    }
    return $tz;
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function fmt_time(?string $utc, string $pattern = 'M j, Y · H:i'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    } catch (Exception) {
        return $utc;
    }
    return $dt->setTimezone(app_tz())->format($pattern);
}

function local_input(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    } catch (Exception) {
        return '';
    }
    return $dt->setTimezone(app_tz())->format('Y-m-d\TH:i');
}

function utc_start_of_local_day(int $daysAgo = 0): string
{
    $day = (new DateTimeImmutable('today', app_tz()))->modify('-' . $daysAgo . ' days');
    return $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (cfg('trust_proxy', false)) {
        $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($proto === 'https') {
            return true;
        }
    }
    return false;
}

function request_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (cfg('trust_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    }
    return $host;
}

function base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $script = preg_replace('#/router\.php$#', '/index.php', $script) ?? $script;
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        return '';
    }
    return rtrim($dir, '/');
}

function base_url(): string
{
    $configured = trim((string) cfg('base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $scheme = request_is_https() ? 'https' : 'http';
    return $scheme . '://' . request_host() . base_path();
}

function page_url(array $query = []): string
{
    // Directory-relative so links keep working in a subfolder and behind a
    // path-prefixed preview. Do not prefix with "/".
    if ($query === []) {
        return 'index.php';
    }
    return 'index.php?' . http_build_query($query);
}

function home_url(): string
{
    return 'index.php';
}

function asset_url(string $path): string
{
    return ltrim($path, '/');
}

function short_url(string $code, ?bool $pretty = null): string
{
    $pretty ??= friendly_urls();
    if ($pretty) {
        return base_url() . '/' . rawurlencode($code);
    }
    return base_url() . '/?' . http_build_query(['c' => $code]);
}

function request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    if (str_starts_with($path, '/index.php')) {
        $rest = substr($path, strlen('/index.php'));
        $path = ($rest === '' || $rest === false) ? '/' : $rest;
    }
    if ($path === '' || ($path[0] ?? '') !== '/') {
        $path = '/' . ltrim($path, '/');
    }
    return $path;
}

function client_ip(): string
{
    if (cfg('trust_proxy', false)) {
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function is_public_ip(string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    return (bool) filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    header('X-Robots-Tag: noindex, nofollow');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = base_path();
    session_name('spriglink_session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ($path === '' ? '/' : $path . '/'),
        'domain' => '',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    $now = time();
    if (!empty($_SESSION['auth'])) {
        $last = (int) ($_SESSION['last'] ?? 0);
        if ($last !== 0 && $now - $last > 60 * 60 * 12) {
            $_SESSION = [];
            session_regenerate_id(true);
        } else {
            $_SESSION['last'] = $now;
        }
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $token = (string) ($_SESSION['csrf'] ?? '');
    return $token !== '' && hash_equals($token, $sent);
}

function flash(?string $type = null, ?string $text = null): ?array
{
    if ($type !== null) {
        $_SESSION['flash'] = ['type' => $type, 'text' => $text ?? ''];
        return null;
    }
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $saved = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $saved;
}

function redirect_to(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

function logged_in(): bool
{
    return !empty($_SESSION['auth']);
}

function require_admin(): void
{
    if (!logged_in()) {
        flash('error', 'Sign in to continue.');
        redirect_to(page_url(['page' => 'login']));
    }
}

function configured_password_matches(string $input): bool
{
    $stored = (string) cfg('admin_password', '');
    if ($stored === '') {
        return false;
    }
    $info = password_get_info($stored);
    if (!empty($info['algo'])) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

function using_default_password(): bool
{
    return hash_equals('change-this-password', (string) cfg('admin_password', ''));
}

function clip(string $text, int $length): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function url_host(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? $host : $url;
}

function sel(string $current, string $value): string
{
    return $current === $value ? ' selected' : '';
}

function chk(bool $on): string
{
    return $on ? ' checked' : '';
}

function icon(string $name): string
{
    $paths = [
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="m7 7 1 13h8l1-13"/>',
        'external' => '<path d="M14 5h5v5"/><path d="M19 5 10 14"/><path d="M17 13v5H5V7h5"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'logout' => '<path d="M10 7V5H4v14h6v-2"/><path d="M10 12h10"/><path d="m16 8 4 4-4 4"/>',
        'eye' => '<path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
    ];
    $body = $paths[$name] ?? '';
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

function status_pill(string $state): string
{
    $labels = [
        'live' => 'Live',
        'expired' => 'Expired',
        'disabled' => 'Off',
        'limited' => 'Limit reached',
    ];
    $label = $labels[$state] ?? $state;
    return '<span class="pill pill-' . e($state) . '">' . e($label) . '</span>';
}

function render(string $view, array $data = [], string $layout = 'public', int $status = 200): void
{
    http_response_code($status);
    $data['layout'] = $layout;
    if (!isset($data['title'])) {
        $data['title'] = 'SprigLink';
    }
    if (!isset($data['current'])) {
        $data['current'] = '';
    }
    if (!isset($data['useQr'])) {
        $data['useQr'] = false;
    }
    extract($data, EXTR_OVERWRITE);
    ob_start();
    require SPRIG_ROOT . '/templates/' . $view . '.php';
    $content = ob_get_clean();
    require SPRIG_ROOT . '/templates/layout.php';
    exit;
}

require __DIR__ . '/db.php';
require __DIR__ . '/domain.php';
require __DIR__ . '/handlers.php';
