<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = db_file();
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrate($pdo);
    return $pdo;
}

function db_file(): string
{
    static $path = null;
    if (is_string($path)) {
        return $path;
    }
    $configured = (string) cfg('db_path');
    $dir = dirname($configured);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create the database directory at ' . $dir);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('The database directory is not writable: ' . $dir);
    }
    if (is_file($configured)) {
        $path = $configured;
        return $path;
    }
    $legacy = $dir . '/sprig.sqlite';
    if ($legacy !== $configured && is_file($legacy)) {
        if (@rename($legacy, $configured)) {
            foreach (['-wal', '-shm'] as $suffix) {
                if (is_file($legacy . $suffix)) {
                    @rename($legacy . $suffix, $configured . $suffix);
                }
            }
            $path = $configured;
            return $path;
        }
        $path = $legacy;
        return $path;
    }
    foreach (['-wal', '-shm'] as $suffix) {
        if (is_file($configured . $suffix)) {
            @unlink($configured . $suffix);
        }
    }
    $path = $configured;
    return $path;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS meta (
    k TEXT PRIMARY KEY,
    v TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    url TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    expires_at TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    is_private INTEGER NOT NULL DEFAULT 0,
    click_limit INTEGER,
    password_hash TEXT,
    redirect_mode TEXT NOT NULL DEFAULT 'instant',
    status_code INTEGER NOT NULL DEFAULT 302,
    clicks INTEGER NOT NULL DEFAULT 0,
    last_click_at TEXT
);
CREATE TABLE IF NOT EXISTS aliases (
    code TEXT PRIMARY KEY,
    link_id INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    link_id INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
    clicked_at TEXT NOT NULL,
    ip TEXT NOT NULL DEFAULT '',
    country TEXT,
    region TEXT,
    city TEXT,
    browser TEXT NOT NULL DEFAULT '',
    browser_version TEXT NOT NULL DEFAULT '',
    os TEXT NOT NULL DEFAULT '',
    device TEXT NOT NULL DEFAULT '',
    referrer TEXT NOT NULL DEFAULT '',
    referrer_host TEXT NOT NULL DEFAULT '',
    language TEXT NOT NULL DEFAULT '',
    is_bot INTEGER NOT NULL DEFAULT 0,
    user_agent TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_clicks_link_time ON clicks (link_id, clicked_at);
CREATE INDEX IF NOT EXISTS idx_clicks_time ON clicks (clicked_at);
CREATE TABLE IF NOT EXISTS ip_geo (
    ip TEXT PRIMARY KEY,
    country TEXT NOT NULL DEFAULT '',
    region TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL,
    looked_up_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    attempted_at TEXT NOT NULL,
    success INTEGER NOT NULL,
    kind TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts (ip, kind, attempted_at);
SQL);
    ensure_column($pdo, 'links', 'is_private', 'INTEGER NOT NULL DEFAULT 0');
    app_key();
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
        throw new InvalidArgumentException('Invalid table or column name.');
    }
    $cols = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($cols as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function meta_get(string $key): ?string
{
    $st = db()->prepare('SELECT v FROM meta WHERE k = ?');
    $st->execute([$key]);
    $value = $st->fetchColumn();
    return $value === false ? null : (string) $value;
}

function meta_set(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO meta (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    $st->execute([$key, $value]);
}

function &site_settings_state(): array
{
    static $state = ['loaded' => false, 'values' => []];
    return $state;
}

function site_settings_reset(): void
{
    $state = &site_settings_state();
    $state = ['loaded' => false, 'values' => []];
}

function site_setting_keys(): array
{
    return ['base_url', 'timezone', 'code_length', 'geo_lookup', 'hide_referrer', 'api_token'];
}

function site_setting(string $key, mixed $fallback): mixed
{
    if (!in_array($key, site_setting_keys(), true)) {
        return $fallback;
    }
    $state = &site_settings_state();
    if (!$state['loaded']) {
        $state['loaded'] = true;
        $state['values'] = [];
        $keys = array_map(static fn (string $name): string => 'opt_' . $name, site_setting_keys());
        $marks = implode(',', array_fill(0, count($keys), '?'));
        $st = db()->prepare('SELECT k, v FROM meta WHERE k IN (' . $marks . ')');
        $st->execute($keys);
        foreach ($st as $row) {
            $name = substr((string) $row['k'], 4);
            if (!in_array($name, site_setting_keys(), true)) {
                continue;
            }
            $state['values'][$name] = decode_site_setting($name, (string) $row['v']);
        }
    }
    return array_key_exists($key, $state['values']) ? $state['values'][$key] : $fallback;
}

function decode_site_setting(string $key, string $raw): mixed
{
    if ($key === 'geo_lookup' || $key === 'hide_referrer') {
        return $raw === '1';
    }
    if ($key === 'code_length') {
        return (int) $raw;
    }
    return $raw;
}

function save_site_settings(array $in): array
{
    $base = trim((string) ($in['base_url'] ?? ''));
    if ($base !== '') {
        $parts = parse_url($base);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if (
            strlen($base) > 200
            || preg_match('/[\s\x00-\x1f]/', $base)
            || !preg_match('#^https?://#i', $base)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return ['ok' => false, 'error' => 'Public address must be a normal http or https URL, or be left blank.'];
        }
        $base = rtrim($base, '/');
    }

    $tz = trim((string) ($in['timezone'] ?? ''));
    try {
        new DateTimeZone($tz);
    } catch (Exception) {
        return ['ok' => false, 'error' => 'Choose a timezone from the list.'];
    }

    $lenRaw = trim((string) ($in['code_length'] ?? ''));
    if (!preg_match('/^\d+$/', $lenRaw)) {
        return ['ok' => false, 'error' => 'Code length must be a whole number from 3 to 16.'];
    }
    $len = (int) $lenRaw;
    if ($len < 3 || $len > 16) {
        return ['ok' => false, 'error' => 'Code length must be from 3 to 16.'];
    }

    $token = trim((string) ($in['api_token'] ?? ''));
    if (strlen($token) > 128 || preg_match('/[\x00-\x20]/', $token)) {
        return ['ok' => false, 'error' => 'API token must be one line, with no spaces, and 128 characters or fewer.'];
    }

    meta_set('opt_base_url', $base);
    meta_set('opt_timezone', $tz);
    meta_set('opt_code_length', (string) $len);
    meta_set('opt_geo_lookup', empty($in['geo_lookup']) ? '0' : '1');
    meta_set('opt_hide_referrer', empty($in['hide_referrer']) ? '0' : '1');
    meta_set('opt_api_token', $token);
    site_settings_reset();
    return ['ok' => true];
}

function timezone_groups(): array
{
    $groups = [];
    foreach (timezone_identifiers_list() as $id) {
        $slash = strpos($id, '/');
        $group = $slash === false ? 'Other' : substr($id, 0, $slash);
        $groups[$group][] = $id;
    }
    ksort($groups);
    return $groups;
}

function app_key(): string
{
    static $key = null;
    if (is_string($key)) {
        return $key;
    }
    $existing = meta_get('app_key');
    if ($existing === null || $existing === '') {
        $existing = bin2hex(random_bytes(32));
        meta_set('app_key', $existing);
    }
    $key = $existing;
    return $key;
}

function show_public_links(): bool
{
    $stored = meta_get('show_public_links');
    if ($stored === null || $stored === '') {
        return (bool) cfg('show_public_links', true);
    }
    return $stored === '1';
}

function set_show_public_links(bool $on): void
{
    meta_set('show_public_links', $on ? '1' : '0');
}

function friendly_urls(): bool
{
    $stored = meta_get('friendly_urls');
    if ($stored === null || $stored === '') {
        return (bool) cfg('pretty_urls', false);
    }
    return $stored === '1';
}

function set_friendly_urls(bool $on): void
{
    meta_set('friendly_urls', $on ? '1' : '0');
}
