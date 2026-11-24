<?php
declare(strict_types=1);

function normalize_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 4000) {
        return null;
    }
    if (preg_match('/[\x00-\x20\x7F]/', $url)) {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
        return null;
    }
    if (!in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    return $url;
}

function reserved_code(string $code): bool
{
    return in_array(strtolower($code), [
        'admin', 'login', 'logout', 'index', 'index.php', 'router', 'router.php',
        'assets', 'favicon.ico', 'robots.txt', 'api', 'export', 'settings',
        'links', 'stats', 'edit', 'health', 'public', 'page', 'config',
        'app', 'templates', 'data', 'examples', 'tests',
    ], true);
}

function code_taken(string $code, ?int $exceptLinkId = null): bool
{
    $st = db()->prepare('SELECT id FROM links WHERE code = ?');
    $st->execute([$code]);
    $id = $st->fetchColumn();
    if ($id !== false && (int) $id !== (int) $exceptLinkId) {
        return true;
    }
    $st = db()->prepare('SELECT link_id FROM aliases WHERE code = ?');
    $st->execute([$code]);
    $id = $st->fetchColumn();
    return $id !== false && (int) $id !== (int) $exceptLinkId;
}

function code_length(): int
{
    $len = (int) cfg('code_length', 6);
    return max(3, min(16, $len));
}

function random_code(?int $length = null): string
{
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $length ??= code_length();
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function unique_random_code(): string
{
    for ($i = 0; $i < 12; $i++) {
        $code = random_code();
        if (!reserved_code($code) && !code_taken($code)) {
            return $code;
        }
    }
    throw new RuntimeException('Could not generate a unique code.');
}

function find_link_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM links WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function find_link_by_code(string $code): ?array
{
    $st = db()->prepare('SELECT * FROM links WHERE code = ?');
    $st->execute([$code]);
    $row = $st->fetch();
    if ($row) {
        return $row;
    }
    $st = db()->prepare('SELECT l.* FROM links l INNER JOIN aliases a ON a.link_id = l.id WHERE a.code = ?');
    $st->execute([$code]);
    $row = $st->fetch();
    return $row ?: null;
}

function link_state(array $link): string
{
    if ((int) $link['is_active'] !== 1) {
        return 'disabled';
    }
    if (!empty($link['expires_at']) && (string) $link['expires_at'] <= now_utc()) {
        return 'expired';
    }
    if ($link['click_limit'] !== null && (int) $link['clicks'] >= (int) $link['click_limit']) {
        return 'limited';
    }
    return 'live';
}

function expiry_from_input(string $preset, string $customLocal): ?string
{
    if ($preset === '' || $preset === 'never') {
        return null;
    }
    $tz = app_tz();
    $now = new DateTimeImmutable('now', $tz);
    if ($preset === 'custom') {
        $customLocal = trim($customLocal);
        if ($customLocal === '') {
            throw new InvalidArgumentException('Pick a date and time, or choose Never.');
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $customLocal, $tz);
        if (!$dt instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('That expiry date is not valid.');
        }
    } else {
        $dt = match ($preset) {
            '1h' => $now->modify('+1 hour'),
            '1d' => $now->modify('+1 day'),
            '7d' => $now->modify('+7 days'),
            '30d' => $now->modify('+30 days'),
            '90d' => $now->modify('+90 days'),
            default => throw new InvalidArgumentException('Choose a valid expiry.'),
        };
    }
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function validate_link_fields(array $in, ?array $existing): array
{
    $errors = [];
    $url = normalize_url((string) ($in['url'] ?? ''));
    if ($url === null) {
        $errors[] = 'Enter a full http or https URL.';
    }
    $title = trim((string) ($in['title'] ?? ''));
    if (mb_strlen($title) > 200) {
        $errors[] = 'Title must be 200 characters or fewer.';
    }
    $notes = trim((string) ($in['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) {
        $errors[] = 'Notes must be 2,000 characters or fewer.';
    }
    $code = trim((string) ($in['code'] ?? ''));
    if ($existing !== null && $code === '') {
        $errors[] = 'A short code is required when editing.';
    }
    if ($code !== '' && !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code)) {
        $errors[] = 'Codes use letters, numbers, hyphens, and underscores, up to 64 characters.';
    } elseif ($code !== '' && reserved_code($code)) {
        $errors[] = 'That code is reserved.';
    } elseif ($code !== '' && code_taken($code, $existing ? (int) $existing['id'] : null)) {
        $errors[] = 'That code is already in use.';
    }

    $expires = null;
    try {
        $expires = expiry_from_input((string) ($in['expires_preset'] ?? 'never'), (string) ($in['expires_at'] ?? ''));
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }

    $limitRaw = trim((string) ($in['click_limit'] ?? ''));
    $limit = null;
    if ($limitRaw !== '') {
        if (!preg_match('/^[0-9]+$/', $limitRaw) || (int) $limitRaw < 1 || (int) $limitRaw > 100000000) {
            $errors[] = 'Click limit must be a positive number, or left blank for no limit.';
        } else {
            $limit = (int) $limitRaw;
        }
    }

    $mode = (string) ($in['redirect_mode'] ?? 'instant');
    if (!in_array($mode, ['instant', 'preview'], true)) {
        $mode = 'instant';
    }
    $status = (int) ($in['status_code'] ?? 302);
    if (!in_array($status, [301, 302], true)) {
        $status = 302;
    }

    $password = (string) ($in['password'] ?? '');
    if (strlen($password) > 200) {
        $errors[] = 'Password must be 200 characters or fewer.';
    }
    $passwordHash = $existing['password_hash'] ?? null;
    if (!empty($in['remove_password'])) {
        $passwordHash = null;
    }
    if ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    $isActive = 1;
    if ($existing !== null) {
        $isActive = !empty($in['is_active']) ? 1 : 0;
    }
    $isPrivate = !empty($in['is_private']) ? 1 : 0;

    return [
        'errors' => $errors,
        'data' => [
            'url' => $url ?? '',
            'title' => $title,
            'notes' => $notes,
            'code' => $code,
            'expires_at' => $expires,
            'click_limit' => $limit,
            'password_hash' => $passwordHash,
            'redirect_mode' => $mode,
            'status_code' => $status,
            'is_active' => $isActive,
            'is_private' => $isPrivate,
        ],
    ];
}

function save_link(?int $id, array $in): array
{
    $existing = null;
    if ($id !== null) {
        $existing = find_link_by_id($id);
        if ($existing === null) {
            return ['ok' => false, 'errors' => ['That link no longer exists.']];
        }
    }
    $parsed = validate_link_fields($in, $existing);
    if ($parsed['errors'] !== []) {
        return ['ok' => false, 'errors' => $parsed['errors']];
    }
    $data = $parsed['data'];
    if ($data['code'] === '') {
        $data['code'] = unique_random_code();
    }
    $now = now_utc();
    $pdo = db();

    if ($id === null) {
        $dup = $pdo->prepare('SELECT code FROM links WHERE url = ? ORDER BY id DESC LIMIT 5');
        $dup->execute([$data['url']]);
        $duplicates = array_column($dup->fetchAll(), 'code');
        try {
            $pdo->prepare(
                'INSERT INTO links (code, url, title, notes, created_at, updated_at, expires_at, is_active, click_limit, password_hash, redirect_mode, status_code, clicks, is_private)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 0, ?)'
            )->execute([
                $data['code'], $data['url'], $data['title'], $data['notes'], $now, $now,
                $data['expires_at'], $data['click_limit'], $data['password_hash'],
                $data['redirect_mode'], $data['status_code'], $data['is_private'],
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return ['ok' => false, 'errors' => ['That code is already in use.']];
            }
            throw $e;
        }
        return [
            'ok' => true,
            'id' => (int) $pdo->lastInsertId(),
            'code' => $data['code'],
            'duplicates' => $duplicates,
        ];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM aliases WHERE code = ? AND link_id = ?')->execute([$data['code'], $id]);
        $pdo->prepare(
            'UPDATE links
             SET code = ?, url = ?, title = ?, notes = ?, updated_at = ?, expires_at = ?, is_active = ?,
                 click_limit = ?, password_hash = ?, redirect_mode = ?, status_code = ?, is_private = ?
             WHERE id = ?'
        )->execute([
            $data['code'], $data['url'], $data['title'], $data['notes'], $now, $data['expires_at'],
            $data['is_active'], $data['click_limit'], $data['password_hash'], $data['redirect_mode'],
            $data['status_code'], $data['is_private'], $id,
        ]);
        if (!empty($in['keep_alias']) && $existing['code'] !== $data['code']) {
            $pdo->prepare('INSERT OR IGNORE INTO aliases (code, link_id) VALUES (?, ?)')->execute([$existing['code'], $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && str_contains($e->getMessage(), 'UNIQUE')) {
            return ['ok' => false, 'errors' => ['That code is already in use.']];
        }
        throw $e;
    }

    return ['ok' => true, 'id' => $id, 'code' => $data['code'], 'duplicates' => []];
}

function delete_link(int $id): void
{
    $st = db()->prepare('DELETE FROM links WHERE id = ?');
    $st->execute([$id]);
}

function set_link_active(int $id, bool $active): void
{
    $st = db()->prepare('UPDATE links SET is_active = ?, updated_at = ? WHERE id = ?');
    $st->execute([$active ? 1 : 0, now_utc(), $id]);
}

function purge_sample_links(): int
{
    $st = db()->prepare("DELETE FROM links WHERE notes LIKE 'Sample · %'");
    $st->execute();
    return $st->rowCount();
}

function clear_visit_details(): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM clicks')->fetchColumn();
        $pdo->exec('DELETE FROM clicks');
        $pdo->exec('UPDATE links SET last_click_at = NULL');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $count;
}

function clear_click_counts(): int
{
    $pdo = db();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM links WHERE clicks != 0')->fetchColumn();
    $pdo->exec('UPDATE links SET clicks = 0');
    return $count;
}

function sample_link_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM links WHERE notes LIKE 'Sample · %'")->fetchColumn();
}

function link_filter(): array
{
    $status = (string) ($_GET['status'] ?? 'all');
    if (!in_array($status, ['all', 'live', 'expired', 'disabled', 'limited'], true)) {
        $status = 'all';
    }
    $sort = (string) ($_GET['sort'] ?? 'new');
    if (!in_array($sort, ['new', 'clicks', 'code'], true)) {
        $sort = 'new';
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = [];
    $params = [];
    $now = now_utc();
    if ($status === 'live') {
        $where[] = 'is_active = 1 AND (expires_at IS NULL OR expires_at > :now) AND (click_limit IS NULL OR clicks < click_limit)';
        $params['now'] = $now;
    } elseif ($status === 'expired') {
        $where[] = 'is_active = 1 AND expires_at IS NOT NULL AND expires_at <= :now';
        $params['now'] = $now;
    } elseif ($status === 'limited') {
        $where[] = 'is_active = 1 AND (expires_at IS NULL OR expires_at > :now) AND click_limit IS NOT NULL AND clicks >= click_limit';
        $params['now'] = $now;
    } elseif ($status === 'disabled') {
        $where[] = 'is_active = 0';
    }
    if ($q !== '') {
        $where[] = "(code LIKE :q ESCAPE '\\' OR url LIKE :q ESCAPE '\\' OR title LIKE :q ESCAPE '\\' OR notes LIKE :q ESCAPE '\\')";
        $params['q'] = '%' . addcslashes($q, "%_\\") . '%';
    }
    $sql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
    $order = match ($sort) {
        'clicks' => 'clicks DESC, id DESC',
        'code' => 'code COLLATE NOCASE ASC',
        default => 'id DESC',
    };
    return [$sql, $params, $order, $status, $sort, $q];
}

function public_links(): array
{
    $rows = db()->query(
        'SELECT code, url, title, is_active, expires_at, click_limit, clicks, password_hash
         FROM links WHERE is_private = 0 ORDER BY id DESC'
    )->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $row['has_password'] = !empty($row['password_hash']);
        unset($row['password_hash']);
        $out[] = $row;
    }
    return $out;
}

function list_links(): array
{
    [$where, $params, $order] = link_filter();
    $per = 20;
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $count = db()->prepare('SELECT COUNT(*) FROM links ' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $per));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $per;
    $st = db()->prepare('SELECT * FROM links ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . $per . ' OFFSET ' . $offset);
    $st->execute($params);
    return [
        'rows' => $st->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per' => $per,
    ];
}

function ua_is_bot(string $ua): bool
{
    $lower = strtolower($ua);
    $needles = [
        'spider', 'crawler', 'crawl', 'slurp', 'facebookexternalhit', 'telegrambot',
        'slackbot', 'discordbot', 'embedly', 'skypeuripreview', 'curl/', 'wget/',
        'python-requests', 'go-http-client', 'headless', 'phantomjs', 'semrush',
        'ahrefs', 'petalbot', 'bytespider', 'gptbot', 'claudebot', 'applebot',
        'bingbot', 'googlebot', 'duckduckbot', 'yandexbot', 'baiduspider', 'sogou',
        'exabot', 'ia_archiver',
    ];
    foreach ($needles as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }
    return (bool) preg_match('/(^|[^a-z])bot([^a-z]|$)/', $lower);
}

function parse_user_agent(string $ua): array
{
    $browser = 'Unknown';
    $version = '';
    $os = 'Unknown';
    $device = 'Desktop';
    $isBot = ua_is_bot($ua);

    if (!$isBot && $ua !== '') {
        $matchers = [
            ['Edge', '#Edg(?:A|iOS)?/([0-9.]+)#'],
            ['Opera', '#OPR/([0-9.]+)#'],
            ['Samsung Internet', '#SamsungBrowser/([0-9.]+)#'],
            ['Chrome', '#(?:CriOS|Chrome)/([0-9.]+)#'],
            ['Firefox', '#(?:FxiOS|Firefox)/([0-9.]+)#'],
            ['Safari', '#Version/([0-9.]+).*Safari/#'],
        ];
        foreach ($matchers as [$name, $pattern]) {
            if (preg_match($pattern, $ua, $m)) {
                $browser = $name;
                $version = explode('.', $m[1])[0];
                break;
            }
        }
    } elseif ($isBot) {
        $browser = 'Bot';
        $device = 'Bot';
    }

    if (preg_match('#Android(?:[ /]([0-9.]+))?#', $ua, $m)) {
        $os = 'Android' . (!empty($m[1]) ? ' ' . explode('.', $m[1])[0] : '');
        if (!$isBot) {
            $device = stripos($ua, 'Tablet') !== false ? 'Tablet' : 'Mobile';
        }
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPod') !== false) {
        $os = 'iOS';
        if (!$isBot) {
            $device = 'Mobile';
        }
    } elseif (stripos($ua, 'iPad') !== false || (stripos($ua, 'Macintosh') !== false && stripos($ua, 'Mobile') !== false)) {
        $os = 'iPadOS';
        if (!$isBot) {
            $device = 'Tablet';
        }
    } elseif (preg_match('/Windows NT ([0-9.]+)/', $ua, $m)) {
        $map = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.2' => 'Windows 8', '6.1' => 'Windows 7'];
        $os = $map[$m[1]] ?? 'Windows';
    } elseif (stripos($ua, 'Mac OS X') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'CrOS') !== false) {
        $os = 'ChromeOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    return [
        'browser' => $browser,
        'browser_version' => $version,
        'os' => $os,
        'device' => $device,
        'is_bot' => $isBot ? 1 : 0,
    ];
}

function primary_language(string $header): string
{
    $first = trim(explode(',', $header)[0] ?? '');
    $tag = trim(explode(';', $first)[0] ?? '');
    return substr($tag, 0, 35);
}

function is_prefetch(): bool
{
    $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''));
    if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')) {
        return true;
    }
    $extra = strtolower((string) ($_SERVER['HTTP_X_PURPOSE'] ?? $_SERVER['HTTP_X_MOZ'] ?? ''));
    return str_contains($extra, 'prefetch') || str_contains($extra, 'preview');
}

function country_name(string $code): string
{
    static $map = [
        'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
        'DE' => 'Germany', 'FR' => 'France', 'NL' => 'Netherlands', 'SE' => 'Sweden', 'NO' => 'Norway',
        'DK' => 'Denmark', 'FI' => 'Finland', 'IE' => 'Ireland', 'ES' => 'Spain', 'IT' => 'Italy',
        'PT' => 'Portugal', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria', 'PL' => 'Poland',
        'CZ' => 'Czechia', 'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China', 'IN' => 'India',
        'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina', 'NZ' => 'New Zealand', 'SG' => 'Singapore',
        'ZA' => 'South Africa', 'RU' => 'Russia', 'UA' => 'Ukraine', 'TR' => 'Turkey', 'IL' => 'Israel',
        'AE' => 'United Arab Emirates', 'ID' => 'Indonesia', 'PH' => 'Philippines', 'TH' => 'Thailand',
        'VN' => 'Vietnam', 'MY' => 'Malaysia', 'TW' => 'Taiwan', 'HK' => 'Hong Kong', 'RO' => 'Romania',
        'HU' => 'Hungary', 'GR' => 'Greece', 'CL' => 'Chile', 'CO' => 'Colombia', 'PE' => 'Peru',
    ];
    $code = strtoupper($code);
    return $map[$code] ?? $code;
}

function record_click(array $link): void
{
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $parsed = parse_user_agent($ua);
    $ip = client_ip();
    $ref = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    $host = '';
    if ($ref !== '') {
        $parsedHost = parse_url($ref, PHP_URL_HOST);
        $host = is_string($parsedHost) ? strtolower($parsedHost) : '';
    }
    $country = null;
    $region = null;
    $city = null;
    if (!is_public_ip($ip)) {
        $country = 'Private network';
    } else {
        $cf = strtoupper(substr(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')), 0, 2));
        if (preg_match('/^[A-Z]{2}$/', $cf) && !in_array($cf, ['XX', 'T1'], true)) {
            $country = country_name($cf);
        }
    }
    $now = now_utc();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO clicks (
                link_id, clicked_at, ip, country, region, city, browser, browser_version, os, device,
                referrer, referrer_host, language, is_bot, user_agent
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $link['id'], $now, $ip, $country, $region, $city,
            $parsed['browser'], $parsed['browser_version'], $parsed['os'], $parsed['device'],
            $ref, $host, primary_language((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')),
            $parsed['is_bot'], $ua,
        ]);
        $pdo->prepare('UPDATE links SET clicks = clicks + 1, last_click_at = ? WHERE id = ?')->execute([$now, (int) $link['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function lookup_ip(string $ip): array
{
    $failed = ['status' => 'failed', 'country' => '', 'region' => '', 'city' => ''];
    if (!function_exists('curl_init')) {
        return $failed;
    }
    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,regionName,city';
    $ch = curl_init($url);
    if ($ch === false) {
        return $failed;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_USERAGENT => 'SprigLink/1.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    $data = json_decode(is_string($body) ? $body : '', true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return $failed;
    }
    return [
        'status' => 'ok',
        'country' => substr((string) ($data['country'] ?? ''), 0, 80),
        'region' => substr((string) ($data['regionName'] ?? ''), 0, 80),
        'city' => substr((string) ($data['city'] ?? ''), 0, 80),
    ];
}

function geo_backfill(int $limit = 4): void
{
    if (!cfg('geo_lookup', true)) {
        return;
    }
    $st = db()->prepare('SELECT DISTINCT ip FROM clicks WHERE country IS NULL AND ip != "" LIMIT ' . max(1, $limit));
    $st->execute();
    $ips = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ips as $ip) {
        if (!is_string($ip) || !is_public_ip($ip)) {
            db()->prepare('UPDATE clicks SET country = ? WHERE ip = ? AND country IS NULL')->execute(['Private network', $ip]);
            continue;
        }
        $cached = db()->prepare('SELECT * FROM ip_geo WHERE ip = ?');
        $cached->execute([$ip]);
        $row = $cached->fetch();
        $fresh = false;
        if ($row) {
            $age = time() - (int) strtotime($row['looked_up_at'] . ' UTC');
            $fresh = $row['status'] === 'ok' ? $age < 86400 * 30 : $age < 60 * 60 * 6;
        }
        if (!$row || !$fresh) {
            $look = lookup_ip($ip);
            db()->prepare(
                'INSERT INTO ip_geo (ip, country, region, city, status, looked_up_at)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON CONFLICT(ip) DO UPDATE SET country = excluded.country, region = excluded.region, city = excluded.city, status = excluded.status, looked_up_at = excluded.looked_up_at'
            )->execute([$ip, $look['country'], $look['region'], $look['city'], $look['status'], now_utc()]);
            $row = $look + ['status' => $look['status']];
        }
        if (($row['status'] ?? '') === 'ok' && ($row['country'] ?? '') !== '') {
            db()->prepare('UPDATE clicks SET country = ?, region = ?, city = ? WHERE ip = ? AND country IS NULL')
                ->execute([$row['country'], $row['region'] ?? '', $row['city'] ?? '', $ip]);
        }
    }
}

function count_clicks(?int $linkId, ?string $sinceUtc, bool $includeBots): int
{
    $where = [];
    $params = [];
    if (!$includeBots) {
        $where[] = 'is_bot = 0';
    }
    if ($linkId !== null) {
        $where[] = 'link_id = :id';
        $params['id'] = $linkId;
    }
    if ($sinceUtc !== null) {
        $where[] = 'clicked_at >= :since';
        $params['since'] = $sinceUtc;
    }
    $sql = 'SELECT COUNT(*) FROM clicks' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn();
}

function count_unique_ips(?int $linkId, bool $includeBots): int
{
    $where = ['ip != ""'];
    $params = [];
    if (!$includeBots) {
        $where[] = 'is_bot = 0';
    }
    if ($linkId !== null) {
        $where[] = 'link_id = :id';
        $params['id'] = $linkId;
    }
    $st = db()->prepare('SELECT COUNT(DISTINCT ip) FROM clicks WHERE ' . implode(' AND ', $where));
    $st->execute($params);
    return (int) $st->fetchColumn();
}

function live_link_count(): int
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM links
         WHERE is_active = 1
           AND (expires_at IS NULL OR expires_at > ?)
           AND (click_limit IS NULL OR clicks < click_limit)'
    );
    $st->execute([now_utc()]);
    return (int) $st->fetchColumn();
}

function daily_counts(?int $linkId, int $days, bool $includeBots): array
{
    $startLocal = (new DateTimeImmutable('today', app_tz()))->modify('-' . ($days - 1) . ' days');
    $startUtc = $startLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $where = ['clicked_at >= :since'];
    $params = ['since' => $startUtc];
    if (!$includeBots) {
        $where[] = 'is_bot = 0';
    }
    if ($linkId !== null) {
        $where[] = 'link_id = :id';
        $params['id'] = $linkId;
    }
    $st = db()->prepare('SELECT clicked_at FROM clicks WHERE ' . implode(' AND ', $where));
    $st->execute($params);
    $buckets = [];
    $tz = app_tz();
    while ($row = $st->fetch()) {
        try {
            $dt = new DateTimeImmutable((string) $row['clicked_at'], new DateTimeZone('UTC'));
        } catch (Exception) {
            continue;
        }
        $key = $dt->setTimezone($tz)->format('Y-m-d');
        $buckets[$key] = ($buckets[$key] ?? 0) + 1;
    }
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $startLocal->modify('+' . $i . ' days');
        $key = $day->format('Y-m-d');
        $out[] = ['date' => $key, 'label' => $day->format('M j'), 'count' => $buckets[$key] ?? 0];
    }
    return $out;
}

function grouped_counts(string $column, ?int $linkId, ?string $sinceUtc, bool $includeBots): array
{
    $allowed = ['browser' => 'browser', 'os' => 'os', 'device' => 'device', 'country' => 'country', 'referrer_host' => 'referrer_host'];
    if (!isset($allowed[$column])) {
        return [];
    }
    $expr = match ($column) {
        'referrer_host' => "CASE WHEN referrer_host = '' THEN 'Direct' ELSE referrer_host END",
        'country' => "CASE WHEN country IS NULL OR country = '' THEN 'Unknown' ELSE country END",
        default => "CASE WHEN {$column} = '' THEN 'Unknown' ELSE {$column} END",
    };
    $where = [];
    $params = [];
    if (!$includeBots) {
        $where[] = 'is_bot = 0';
    }
    if ($linkId !== null) {
        $where[] = 'link_id = :id';
        $params['id'] = $linkId;
    }
    if ($sinceUtc !== null) {
        $where[] = 'clicked_at >= :since';
        $params['since'] = $sinceUtc;
    }
    $sql = 'SELECT ' . $expr . ' AS label, COUNT(*) AS count FROM clicks';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY label ORDER BY count DESC LIMIT 8';
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function top_links(string $sinceUtc, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $st = db()->prepare(
        'SELECT l.id, l.code, l.title, l.url, l.clicks,
            (SELECT COUNT(*) FROM clicks c WHERE c.link_id = l.id AND c.is_bot = 0 AND c.clicked_at >= ?) AS recent
         FROM links l
         ORDER BY recent DESC, l.clicks DESC
         LIMIT ' . $limit
    );
    $st->execute([$sinceUtc]);
    return $st->fetchAll();
}

function recent_clicks(int $limit, ?int $linkId = null): array
{
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT c.*, l.code AS link_code, l.title AS link_title FROM clicks c INNER JOIN links l ON l.id = c.link_id';
    $params = [];
    if ($linkId !== null) {
        $sql .= ' WHERE c.link_id = ?';
        $params[] = $linkId;
    }
    $sql .= ' ORDER BY c.id DESC LIMIT ' . $limit;
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function place_label(?string $country, ?string $region, ?string $city): string
{
    if ($country === null) {
        return 'Pending';
    }
    if ($country === '' || $country === 'Unknown') {
        return 'Unknown';
    }
    if ($country === 'Private network') {
        return 'Private network';
    }
    $parts = [];
    if ($city) {
        $parts[] = $city;
    }
    if ($region && $region !== $city) {
        $parts[] = $region;
    }
    $parts[] = $country;
    return implode(', ', $parts);
}

function bars_html(array $rows): string
{
    if ($rows === []) {
        return '<p class="quiet">No visits yet.</p>';
    }
    $max = 1;
    foreach ($rows as $row) {
        $max = max($max, (int) $row['count']);
    }
    $html = '<ul class="bars">';
    foreach ($rows as $row) {
        $count = (int) $row['count'];
        $pct = max(6, (int) round($count / $max * 100));
        $html .= '<li><div class="bar-meta"><span>' . e((string) $row['label']) . '</span><span class="num">' . number_format($count) . '</span></div>';
        $html .= '<div class="bar-track"><span style="--w: ' . $pct . '%"></span></div></li>';
    }
    return $html . '</ul>';
}

function chart_svg(array $series): string
{
    $width = 640;
    $height = 168;
    $padX = 8;
    $padY = 16;
    $max = 1;
    foreach ($series as $point) {
        $max = max($max, (int) $point['count']);
    }
    $count = count($series);
    $coords = [];
    foreach ($series as $i => $point) {
        $x = $count <= 1 ? $padX : $padX + ($i * ($width - 2 * $padX) / ($count - 1));
        $y = $height - $padY - (((int) $point['count'] / $max) * ($height - 2 * $padY));
        $coords[] = [round($x, 1), round($y, 1)];
    }
    if ($coords === []) {
        return '';
    }
    $line = implode(' ', array_map(static fn (array $p): string => $p[0] . ',' . $p[1], $coords));
    $area = $coords[0][0] . ',' . ($height - $padY) . ' ' . $line . ' ' . $coords[count($coords) - 1][0] . ',' . ($height - $padY);
    $last = $coords[count($coords) - 1];
    $dots = '';
    $step = (int) max(1, floor($count / 6));
    foreach ($coords as $i => $p) {
        if ($i % $step !== 0 && $i !== $count - 1) {
            continue;
        }
        $dots .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="2.4" fill="#d6ee7a"/>';
    }
    $total = 0;
    $caption = [];
    foreach ($series as $point) {
        $total += (int) $point['count'];
        $caption[] = $point['label'] . ': ' . (int) $point['count'];
    }
    return '<svg class="chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Visits by day. ' . e((string) $total) . ' in this window.">'
        . '<polygon points="' . $area . '" fill="rgba(214,238,122,0.14)"></polygon>'
        . '<polyline points="' . $line . '" fill="none" stroke="#d6ee7a" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></polyline>'
        . $dots
        . '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="3.5" fill="#121512" stroke="#d6ee7a" stroke-width="2"/>'
        . '</svg><ul class="sr-only">' . implode('', array_map(static fn (string $c): string => '<li>' . e($c) . '</li>', $caption)) . '</ul>';
}

function attempt_locked(string $ip, string $kind, int $max = 8): bool
{
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND kind = ? AND success = 0 AND attempted_at >= ?');
    $st->execute([$ip, $kind, $since]);
    return (int) $st->fetchColumn() >= $max;
}

function record_attempt(string $ip, bool $success, string $kind): void
{
    db()->prepare('INSERT INTO login_attempts (ip, attempted_at, success, kind) VALUES (?, ?, ?, ?)')
        ->execute([$ip, now_utc(), $success ? 1 : 0, $kind]);
    if (random_int(1, 20) === 1) {
        db()->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-2 days')");
    }
}

function gate_cookie_name(string $code): string
{
    return 'spriglink_gate_' . substr(hash('sha256', $code), 0, 16);
}

function gate_sign(string $code, int $exp): string
{
    return $exp . '.' . hash_hmac('sha256', $code . '|' . $exp, app_key());
}

function set_gate_cookie(string $code): void
{
    $exp = time() + 60 * 60 * 12;
    $name = gate_cookie_name($code);
    setcookie($name, gate_sign($code, $exp), [
        'expires' => $exp,
        'path' => base_path() === '' ? '/' : base_path() . '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = gate_sign($code, $exp);
}

function gate_ok(string $code): bool
{
    $raw = (string) ($_COOKIE[gate_cookie_name($code)] ?? '');
    $parts = explode('.', $raw, 2);
    if (count($parts) !== 2) {
        return false;
    }
    $exp = (int) $parts[0];
    if ($exp < time()) {
        return false;
    }
    $expect = hash_hmac('sha256', $code . '|' . $exp, app_key());
    return hash_equals($expect, $parts[1]);
}

function csv_safe(mixed $value): string
{
    $text = (string) $value;
    if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text)) {
        return "'" . $text;
    }
    return $text;
}

function weighted_pick(array $pairs): string
{
    $total = 0;
    foreach ($pairs as $pair) {
        $total += (int) $pair[1];
    }
    $roll = mt_rand(1, max(1, $total));
    foreach ($pairs as $pair) {
        $roll -= (int) $pair[1];
        if ($roll <= 0) {
            return (string) $pair[0];
        }
    }
    return (string) $pairs[0][0];
}

function seed_demo_if_empty(): void
{
    if (getenv('SHORTENER_DEMO') !== '1') {
        return;
    }
    if ((int) db()->query('SELECT COUNT(*) FROM links')->fetchColumn() > 0) {
        return;
    }
    $now = time();
    $hash = password_hash('sprig', PASSWORD_DEFAULT);
    $defs = [
        ['docs', 'https://www.php.net/docs.php', 'PHP manual', 'Sample · Reference link with a long click history.', 0, null, 1, 'instant', 302, null, 72],
        ['notes', 'https://example.com/server-notes', 'Server notes', 'Sample · A normal short link.', 0, null, 1, 'instant', 302, null, 36],
        ['map', 'https://www.openstreetmap.org/', 'Map', 'Sample · OpenStreetMap.', 0, null, 1, 'instant', 302, null, 24],
        ['forum', 'https://example.com/forum/thread/1284', 'Forum thread', 'Sample · Shared from a few referrers.', 0, null, 1, 'instant', 302, null, 18],
        ['week', 'https://example.com/this-week', 'This week only', 'Sample · Expires in five days.', 5, null, 1, 'instant', 302, null, 14],
        ['archive', 'https://example.com/old-campaign', 'Old campaign', 'Sample · Already expired.', -12, null, 1, 'instant', 302, null, 16],
        ['pause', 'https://example.com/paused-page', 'Paused page', 'Sample · Turned off without deleting stats.', 0, null, 0, 'instant', 302, null, 6],
        ['secret', 'https://example.com/quiet-notes', 'Quiet notes', 'Sample · Password is sprig.', 0, null, 1, 'instant', 302, $hash, 8],
        ['check', 'https://example.org/outside', 'Outside link', 'Sample · Shows a preview before redirecting.', 0, null, 1, 'preview', 302, null, 9],
        ['capped', 'https://example.com/limited-drop', 'Limited drop', 'Sample · Stops after the click limit.', 0, 12, 1, 'instant', 302, null, 12],
    ];
    $pdo = db();
    $pdo->beginTransaction();
    $insertLink = $pdo->prepare(
        'INSERT INTO links (code, url, title, notes, created_at, updated_at, expires_at, is_active, click_limit, password_hash, redirect_mode, status_code, clicks, last_click_at, is_private)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertClick = $pdo->prepare(
        'INSERT INTO clicks (link_id, clicked_at, ip, country, region, city, browser, browser_version, os, device, referrer, referrer_host, language, is_bot, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $places = [
        ['United States', 'Indiana', 'Fishers'],
        ['United States', 'California', 'San Jose'],
        ['United States', 'Texas', 'Austin'],
        ['United Kingdom', 'England', 'London'],
        ['Germany', 'Berlin', 'Berlin'],
        ['Canada', 'Ontario', 'Toronto'],
        ['Japan', 'Tokyo', 'Tokyo'],
        ['Australia', 'Victoria', 'Melbourne'],
        ['Netherlands', 'North Holland', 'Amsterdam'],
        ['France', 'Île-de-France', 'Paris'],
        ['Brazil', 'São Paulo', 'São Paulo'],
        ['India', 'Karnataka', 'Bengaluru'],
    ];
    $profiles = [
        ['Chrome', '131', 'Windows 10/11', 'Desktop', 30],
        ['Chrome', '130', 'Android 14', 'Mobile', 18],
        ['Safari', '18', 'iOS', 'Mobile', 14],
        ['Safari', '18', 'macOS', 'Desktop', 10],
        ['Firefox', '133', 'Linux', 'Desktop', 8],
        ['Edge', '131', 'Windows 10/11', 'Desktop', 9],
        ['Samsung Internet', '27', 'Android 14', 'Mobile', 4],
        ['Safari', '18', 'iPadOS', 'Tablet', 3],
        ['Firefox', '133', 'Windows 10/11', 'Desktop', 4],
    ];
    $refs = [
        ['', '', 40],
        ['https://www.google.com/', 'www.google.com', 18],
        ['https://news.ycombinator.com/', 'news.ycombinator.com', 8],
        ['https://www.reddit.com/', 'www.reddit.com', 8],
        ['https://x.com/', 'x.com', 6],
        ['https://example.com/newsletter', 'example.com', 5],
    ];
    $langs = [['en-US', 50], ['en-GB', 12], ['de-DE', 8], ['fr-FR', 6], ['ja-JP', 5], ['pt-BR', 4]];
    mt_srand(20261124);
    foreach ($defs as $def) {
        [$code, $url, $title, $notes, $expireDays, $limit, $active, $mode, $status, $pass, $clicks] = $def;
        $created = gmdate('Y-m-d H:i:s', $now - mt_rand(8, 50) * 86400);
        $expires = null;
        if ($expireDays !== 0 || $code === 'archive' || $code === 'week') {
            $expires = gmdate('Y-m-d H:i:s', $now + $expireDays * 86400);
        }
        if ($code !== 'archive' && $code !== 'week') {
            $expires = null;
        }
        $insertLink->execute([
            $code, $url, $title, $notes, $created, $created, $expires, $active, $limit, $pass, $mode, $status, $clicks, null,
            $code === 'secret' ? 1 : 0,
        ]);
        $linkId = (int) $pdo->lastInsertId();
        $last = null;
        for ($i = 0; $i < $clicks; $i++) {
            $age = (int) ((mt_rand() / mt_getrandmax()) ** 2 * 28 * 86400) + mt_rand(0, 4000);
            $clicked = gmdate('Y-m-d H:i:s', $now - $age);
            if ($last === null || $clicked > $last) {
                $last = $clicked;
            }
            $isBot = mt_rand(1, 100) <= 8;
            if ($isBot) {
                $browser = 'Bot';
                $ver = '';
                $os = 'Unknown';
                $device = 'Bot';
                $ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
            } else {
                $profile = null;
                $roll = mt_rand(1, 100);
                $cursor = 0;
                foreach ($profiles as $item) {
                    $cursor += $item[4];
                    if ($roll <= $cursor) {
                        $profile = $item;
                        break;
                    }
                }
                $profile ??= $profiles[0];
                [$browser, $ver, $os, $device] = $profile;
                $ua = 'Mozilla/5.0 ' . $browser . '/' . $ver;
            }
            $place = $places[mt_rand(0, count($places) - 1)];
            $ref = $refs[0];
            $roll = mt_rand(1, 85);
            $cursor = 0;
            foreach ($refs as $item) {
                $cursor += $item[2];
                if ($roll <= $cursor) {
                    $ref = $item;
                    break;
                }
            }
            $octet = mt_rand(10, 200);
            $ip = '203.0.113.' . $octet;
            $lang = weighted_pick($langs);
            $insertClick->execute([
                $linkId, $clicked, $ip, $place[0], $place[1], $place[2],
                $browser, $ver, $os, $device, $ref[0], $ref[1], $lang, $isBot ? 1 : 0, $ua,
            ]);
        }
        $pdo->prepare('UPDATE links SET last_click_at = ? WHERE id = ?')->execute([$last, $linkId]);
    }
    meta_set('demo_seeded', '1');
    $pdo->commit();
}
