<?php
declare(strict_types=1);

function handle_request(): void
{
    send_security_headers();
    start_session();
    db();
    seed_demo_if_empty();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD, POST');
        exit;
    }

    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if ($page === '') {
        $trim = trim(request_path(), '/');
        if ($trim === 'login') {
            $page = 'login';
        } elseif ($trim === 'logout') {
            $page = 'logout';
        } elseif ($trim === 'admin') {
            $page = 'admin';
        } elseif ($trim === 'admin/links') {
            $page = 'links';
        } elseif ($trim === 'admin/settings') {
            $page = 'settings';
        } elseif (preg_match('#^admin/links/(\d+)/edit$#', $trim, $m)) {
            $page = 'edit';
            $_GET['id'] = $m[1];
        } elseif (preg_match('#^admin/links/(\d+)/stats$#', $trim, $m)) {
            $page = 'stats';
            $_GET['id'] = $m[1];
        } elseif ($trim !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', rawurldecode($trim))) {
            $_GET['c'] = rawurldecode($trim);
        } elseif ($trim !== '') {
            render('stopped', [
                'title' => 'Not found',
                'heading' => 'Nothing lives here',
                'message' => 'That address is not a short link or an admin page.',
            ], 'public', 404);
        }
    }

    if ($page === 'api') {
        handle_api();
        return;
    }
    if ($page === 'logout') {
        handle_logout($method);
        return;
    }
    if ($page === 'login') {
        handle_login($method);
        return;
    }
    if ($page === 'export') {
        handle_export();
        return;
    }
    if (in_array($page, ['admin', 'links', 'edit', 'stats', 'settings'], true)) {
        handle_admin($page, $method);
        return;
    }
    if ($page !== '') {
        render('stopped', [
            'title' => 'Not found',
            'heading' => 'That page does not exist',
            'message' => 'Check the address, or go back home.',
        ], 'public', 404);
    }

    $code = trim((string) ($_GET['c'] ?? ''));
    if ($code !== '') {
        handle_redirect($code, $method);
        return;
    }

    $directoryOn = show_public_links();
    render('home', [
        'title' => 'SprigLink',
        'directoryOn' => $directoryOn,
        'publicLinks' => $directoryOn ? public_links() : [],
    ]);
}

function handle_login(string $method): void
{
    if (logged_in()) {
        redirect_to(page_url(['page' => 'admin']));
    }
    header('Cache-Control: no-store');
    $error = '';
    if ($method === 'POST') {
        $ip = client_ip();
        if (!csrf_ok()) {
            $error = 'The form expired. Try again.';
        } elseif (attempt_locked($ip, 'admin')) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            $userOk = hash_equals((string) cfg('admin_username', 'admin'), (string) ($_POST['username'] ?? ''));
            $passOk = configured_password_matches((string) ($_POST['password'] ?? ''));
            $ok = $userOk && $passOk;
            record_attempt($ip, $ok, 'admin');
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['auth'] = true;
                $_SESSION['last'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                redirect_to(page_url(['page' => 'admin']));
            }
            $error = 'That username and password do not match.';
        }
    }
    render('login', ['title' => 'Sign in', 'error' => $error], 'public');
}

function handle_logout(string $method): void
{
    if ($method === 'POST' && csrf_ok()) {
        $_SESSION = [];
        session_destroy();
        start_session();
        flash('ok', 'Signed out.');
    }
    redirect_to(page_url(['page' => 'login']));
}

function handle_admin(string $page, string $method): void
{
    require_admin();
    header('Cache-Control: no-store');
    if ($method === 'POST') {
        if (!csrf_ok()) {
            flash('error', 'The form expired. Try again.');
            redirect_to(page_url(['page' => $page]));
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            do_create();
            return;
        }
        if ($action === 'save') {
            do_save();
            return;
        }
        if ($action === 'delete') {
            delete_link((int) ($_POST['id'] ?? 0));
            flash('ok', 'Link deleted.');
            redirect_to(page_url(['page' => 'links']));
        }
        if ($action === 'toggle') {
            $link = find_link_by_id((int) ($_POST['id'] ?? 0));
            if ($link) {
                $next = (int) $link['is_active'] !== 1;
                set_link_active((int) $link['id'], $next);
                flash('ok', $next ? 'Link turned on.' : 'Link turned off.');
            }
            redirect_to(list_return_url());
        }
        if ($action === 'purge_samples') {
            $n = purge_sample_links();
            flash('ok', $n === 1 ? 'Removed 1 sample link.' : 'Removed ' . $n . ' sample links.');
            redirect_to(page_url(['page' => 'admin']));
        }
        if ($action === 'clear_visits') {
            $n = clear_visit_details();
            flash('ok', $n === 1
                ? 'Cleared 1 visit record. Click counts were not changed.'
                : 'Cleared ' . number_format($n) . ' visit records. Click counts were not changed.');
            redirect_to(page_url(['page' => 'settings']) . '#clear-statistics');
        }
        if ($action === 'clear_counts') {
            $n = clear_click_counts();
            flash('ok', $n === 1
                ? 'Reset the click count on 1 link. Visit details were not changed.'
                : 'Reset click counts on ' . number_format($n) . ' links. Visit details were not changed.');
            redirect_to(page_url(['page' => 'settings']) . '#clear-statistics');
        }
        if ($action === 'save_settings') {
            $on = !empty($_POST['show_public_links']);
            set_show_public_links($on);
            flash('ok', $on ? 'Public links will show on the homepage.' : 'The homepage will not list links.');
            redirect_to(page_url(['page' => 'settings']));
        }
        if ($action === 'save_site') {
            $result = save_site_settings($_POST);
            flash($result['ok'] ? 'ok' : 'error', $result['ok'] ? 'Site settings saved.' : (string) $result['error']);
            redirect_to(page_url(['page' => 'settings']));
        }
        if ($action === 'save_link_style') {
            $friendly = (string) ($_POST['link_style'] ?? '') === 'friendly';
            set_friendly_urls($friendly);
            flash('ok', $friendly
                ? 'Short links will be shown as friendly paths. Nginx or Apache has to rewrite them or they will 404.'
                : 'Short links will be shown as normal ?c= addresses.');
            redirect_to(page_url(['page' => 'settings']));
        }
        flash('error', 'Unknown action.');
        redirect_to(page_url(['page' => $page]));
    }

    if ($page === 'admin' || $page === 'stats') {
        geo_backfill(4);
    }
    if ($page === 'admin') {
        render('dashboard', dashboard_data(), 'admin');
        return;
    }
    if ($page === 'links') {
        render('links', links_data(), 'admin');
        return;
    }
    if ($page === 'edit') {
        $link = find_link_by_id((int) ($_GET['id'] ?? 0));
        if (!$link) {
            render('stopped', [
                'title' => 'Not found',
                'heading' => 'Link not found',
                'message' => 'It may have been deleted.',
            ], 'admin', 404);
        }
        render('edit', [
            'title' => 'Edit ' . $link['code'],
            'current' => 'links',
            'link' => $link,
            'values' => form_values_from_link($link),
            'errors' => [],
        ], 'admin');
    }
    if ($page === 'stats') {
        render_stats_page();
        return;
    }
    render('settings', [
        'title' => 'Settings',
        'current' => 'settings',
        'showPublic' => show_public_links(),
        'friendly' => friendly_urls(),
    ], 'admin');
}

function do_create(): void
{
    $result = save_link(null, $_POST);
    if (!$result['ok']) {
        render('dashboard', dashboard_data($result['errors'], $_POST), 'admin');
        return;
    }
    $message = 'Short link created.';
    if (!empty($result['duplicates'])) {
        $message .= ' You already point other codes at that URL: ' . implode(', ', $result['duplicates']) . '.';
    }
    flash('ok', $message);
    redirect_to(page_url(['page' => 'stats', 'id' => $result['id']]));
}

function do_save(): void
{
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $link = find_link_by_id($id);
    if (!$link) {
        flash('error', 'That link no longer exists.');
        redirect_to(page_url(['page' => 'links']));
    }
    $result = save_link($id, $_POST);
    if (!$result['ok']) {
        render('edit', [
            'title' => 'Edit ' . $link['code'],
            'current' => 'links',
            'link' => $link,
            'values' => form_values_from_post($_POST, $link),
            'errors' => $result['errors'],
        ], 'admin');
        return;
    }
    flash('ok', 'Saved.');
    redirect_to(page_url(['page' => 'edit', 'id' => $id]));
}

function list_return_url(): string
{
    $query = ['page' => 'links'];
    $status = (string) ($_POST['status'] ?? 'all');
    $sort = (string) ($_POST['sort'] ?? 'new');
    $q = trim((string) ($_POST['q'] ?? ''));
    $p = (int) ($_POST['p'] ?? 1);
    if ($q !== '') {
        $query['q'] = $q;
    }
    if (in_array($status, ['live', 'expired', 'disabled', 'limited'], true)) {
        $query['status'] = $status;
    }
    if (in_array($sort, ['clicks', 'code'], true)) {
        $query['sort'] = $sort;
    }
    if ($p > 1) {
        $query['p'] = $p;
    }
    return page_url($query);
}

function form_values_from_link(array $link): array
{
    return [
        'url' => (string) $link['url'],
        'title' => (string) $link['title'],
        'notes' => (string) $link['notes'],
        'code' => (string) $link['code'],
        'expires_preset' => empty($link['expires_at']) ? 'never' : 'custom',
        'expires_at' => local_input($link['expires_at'] ?? null),
        'click_limit' => $link['click_limit'] === null ? '' : (string) $link['click_limit'],
        'password' => '',
        'redirect_mode' => (string) $link['redirect_mode'],
        'status_code' => (string) $link['status_code'],
        'is_active' => (int) $link['is_active'] === 1,
        'is_private' => (int) ($link['is_private'] ?? 0) === 1,
        'has_password' => !empty($link['password_hash']),
        'keep_alias' => true,
        'remove_password' => false,
    ];
}

function form_values_from_post(array $post, ?array $link = null): array
{
    return [
        'url' => (string) ($post['url'] ?? ''),
        'title' => (string) ($post['title'] ?? ''),
        'notes' => (string) ($post['notes'] ?? ''),
        'code' => (string) ($post['code'] ?? ''),
        'expires_preset' => (string) ($post['expires_preset'] ?? 'never'),
        'expires_at' => (string) ($post['expires_at'] ?? ''),
        'click_limit' => (string) ($post['click_limit'] ?? ''),
        'password' => '',
        'redirect_mode' => (string) ($post['redirect_mode'] ?? 'instant'),
        'status_code' => (string) ($post['status_code'] ?? '302'),
        'is_active' => isset($post['is_active']),
        'is_private' => !empty($post['is_private']),
        'has_password' => $link ? !empty($link['password_hash']) : false,
        'keep_alias' => isset($post['keep_alias']),
        'remove_password' => isset($post['remove_password']),
    ];
}

function blank_form_values(): array
{
    return form_values_from_post([
        'expires_preset' => 'never',
        'redirect_mode' => 'instant',
        'status_code' => '302',
    ]);
}

function dashboard_data(array $errors = [], ?array $post = null): array
{
    $since7 = utc_start_of_local_day(6);
    $since30 = utc_start_of_local_day(29);
    return [
        'title' => 'Dashboard',
        'current' => 'admin',
        'errors' => $errors,
        'values' => $post ? form_values_from_post($post) : blank_form_values(),
        'today' => count_clicks(null, utc_start_of_local_day(0), false),
        'week' => count_clicks(null, $since7, false),
        'all' => count_clicks(null, null, false),
        'bots' => count_clicks(null, null, true) - count_clicks(null, null, false),
        'live' => live_link_count(),
        'total_links' => (int) db()->query('SELECT COUNT(*) FROM links')->fetchColumn(),
        'series' => daily_counts(null, 14, false),
        'top' => top_links($since30, 5),
        'recent' => recent_clicks(8),
        'samples' => sample_link_count(),
    ];
}

function links_data(): array
{
    [, , , $status, $sort, $q] = link_filter();
    $list = list_links();
    return [
        'title' => 'Links',
        'current' => 'links',
        'rows' => $list['rows'],
        'total' => $list['total'],
        'page' => $list['page'],
        'pages' => $list['pages'],
        'status' => $status,
        'sort' => $sort,
        'q' => $q,
    ];
}

function render_stats_page(): void
{
    $link = find_link_by_id((int) ($_GET['id'] ?? 0));
    if (!$link) {
        render('stopped', [
            'title' => 'Not found',
            'heading' => 'Link not found',
            'message' => 'It may have been deleted.',
        ], 'admin', 404);
    }
    $bots = isset($_GET['bots']) && (string) $_GET['bots'] === '1';
    $id = (int) $link['id'];
    $since30 = utc_start_of_local_day(29);
    render('stats', [
        'title' => $link['code'],
        'current' => 'links',
        'link' => $link,
        'state' => link_state($link),
        'includeBots' => $bots,
        'human' => count_clicks($id, null, false),
        'botCount' => count_clicks($id, null, true) - count_clicks($id, null, false),
        'unique' => count_unique_ips($id, false),
        'week' => count_clicks($id, utc_start_of_local_day(6), $bots),
        'series' => daily_counts($id, 30, $bots),
        'browsers' => grouped_counts('browser', $id, null, $bots),
        'oses' => grouped_counts('os', $id, null, $bots),
        'devices' => grouped_counts('device', $id, null, $bots),
        'countries' => grouped_counts('country', $id, null, $bots),
        'referrers' => grouped_counts('referrer_host', $id, null, $bots),
        'recent' => recent_clicks(25, $id),
        'sinceLabel' => fmt_time($since30, 'M j'),
        'useQr' => true,
    ], 'admin');
}

function handle_export(): void
{
    require_admin();
    $id = (int) ($_GET['id'] ?? 0);
    $link = null;
    if ($id > 0) {
        $link = find_link_by_id($id);
        if (!$link) {
            render('stopped', [
                'title' => 'Not found',
                'heading' => 'Link not found',
                'message' => 'It may have been deleted.',
            ], 'admin', 404);
        }
    }
    $filename = $link ? 'spriglink-' . preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $link['code']) . '.csv' : 'spriglink-clicks.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fputcsv($out, [
        'clicked_at_utc', 'code', 'ip', 'country', 'region', 'city', 'browser', 'browser_version',
        'os', 'device', 'language', 'referrer', 'referrer_host', 'is_bot', 'user_agent',
    ]);
    $sql = 'SELECT c.*, l.code AS link_code FROM clicks c INNER JOIN links l ON l.id = c.link_id';
    $params = [];
    if ($link) {
        $sql .= ' WHERE c.link_id = ?';
        $params[] = (int) $link['id'];
    }
    $sql .= ' ORDER BY c.id DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    while ($row = $st->fetch()) {
        fputcsv($out, array_map('csv_safe', [
            $row['clicked_at'], $row['link_code'], $row['ip'], $row['country'], $row['region'], $row['city'],
            $row['browser'], $row['browser_version'], $row['os'], $row['device'], $row['language'],
            $row['referrer'], $row['referrer_host'], $row['is_bot'], $row['user_agent'],
        ]));
    }
    fclose($out);
    exit;
}

function handle_redirect(string $code, string $method): void
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code)) {
        render('stopped', [
            'title' => 'Not found',
            'heading' => 'Link not found',
            'message' => 'This short link does not exist.',
        ], 'public', 404);
    }
    $link = find_link_by_code($code);
    if (!$link) {
        render('stopped', [
            'title' => 'Not found',
            'heading' => 'Link not found',
            'message' => 'This short link does not exist.',
        ], 'public', 404);
    }
    $state = link_state($link);
    if ($state === 'disabled') {
        render('stopped', [
            'title' => 'Link off',
            'heading' => 'This link is turned off',
            'message' => 'The owner disabled it. The destination is not available from here.',
        ], 'public', 410);
    }
    if ($state === 'expired') {
        render('stopped', [
            'title' => 'Link expired',
            'heading' => 'This link has expired',
            'message' => 'It was set to stop working' . (!empty($link['expires_at']) ? ' after ' . fmt_time((string) $link['expires_at']) . '.' : '.'),
        ], 'public', 410);
    }
    if ($state === 'limited') {
        render('stopped', [
            'title' => 'Link limit',
            'heading' => 'This link reached its click limit',
            'message' => 'It is no longer accepting visits.',
        ], 'public', 410);
    }

    if (!empty($link['password_hash'])) {
        if ($method === 'POST' && (string) ($_POST['action'] ?? '') === 'unlock') {
            $ip = client_ip();
            if (!csrf_ok()) {
                render('password', [
                    'title' => 'Locked link',
                    'link' => $link,
                    'error' => 'The form expired. Try again.',
                ], 'public');
            }
            if (attempt_locked($ip, 'unlock')) {
                render('password', [
                    'title' => 'Locked link',
                    'link' => $link,
                    'error' => 'Too many attempts. Wait 15 minutes.',
                ], 'public');
            }
            $ok = password_verify((string) ($_POST['password'] ?? ''), (string) $link['password_hash']);
            record_attempt($ip, $ok, 'unlock');
            if (!$ok) {
                render('password', [
                    'title' => 'Locked link',
                    'link' => $link,
                    'error' => 'Wrong password.',
                ], 'public');
            }
            set_gate_cookie((string) $link['code']);
            redirect_to(page_url(['c' => $link['code']]));
        }
        if (!gate_ok((string) $link['code'])) {
            render('password', [
                'title' => 'Locked link',
                'link' => $link,
                'error' => '',
            ], 'public');
        }
    }

    $preview = ($link['redirect_mode'] === 'preview') && ((string) ($_GET['go'] ?? '') !== '1');
    if ($preview && $method !== 'POST') {
        render('preview', [
            'title' => 'Continue',
            'link' => $link,
        ], 'public');
    }

    if ($method === 'GET' && !is_prefetch()) {
        record_click($link);
    }
    $status = (int) $link['status_code'] === 301 ? 301 : 302;
    header('Cache-Control: no-store');
    if (cfg('hide_referrer', false)) {
        header('Referrer-Policy: no-referrer');
    }
    header('Location: ' . $link['url'], true, $status);
    exit;
}

function handle_api(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $token = (string) cfg('api_token', '');
    if ($token === '') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'API is disabled. Set the API token in Settings.']);
        exit;
    }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['ok' => false, 'error' => 'POST a JSON object.']);
        exit;
    }
    $ip = client_ip();
    if (attempt_locked($ip, 'api', 60)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Too many API requests. Wait a few minutes.']);
        exit;
    }
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $given = (string) ($body['token'] ?? '');
    if ($given === '' || !hash_equals($token, $given)) {
        record_attempt($ip, false, 'api');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }
    $expiresIn = trim((string) ($body['expires_in'] ?? ''));
    $preset = 'never';
    $custom = '';
    if (in_array($expiresIn, ['', 'never'], true)) {
        $preset = 'never';
    } elseif (in_array($expiresIn, ['1h', '1d', '7d', '30d', '90d'], true)) {
        $preset = $expiresIn;
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $expiresIn)) {
        $preset = 'custom';
        $custom = substr($expiresIn, 0, 16);
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresIn)) {
        $preset = 'custom';
        $custom = $expiresIn . 'T23:59';
    } else {
        http_response_code(422);
        echo json_encode(['ok' => false, 'errors' => ['expires_in must be never, 1h, 1d, 7d, 30d, 90d, or a date.']]);
        exit;
    }
    $result = save_link(null, [
        'url' => (string) ($body['url'] ?? ''),
        'title' => (string) ($body['title'] ?? ''),
        'notes' => (string) ($body['notes'] ?? ''),
        'code' => (string) ($body['code'] ?? ''),
        'expires_preset' => $preset,
        'expires_at' => $custom,
        'click_limit' => (string) ($body['click_limit'] ?? ''),
        'password' => (string) ($body['password'] ?? ''),
        'redirect_mode' => (string) ($body['redirect_mode'] ?? 'instant'),
        'status_code' => (string) ($body['status_code'] ?? '302'),
        'is_private' => !empty($body['private']) ? '1' : '',
    ]);
    if (!$result['ok']) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'errors' => $result['errors']]);
        exit;
    }
    $link = find_link_by_id((int) $result['id']);
    echo json_encode([
        'ok' => true,
        'id' => $result['id'],
        'code' => $result['code'],
        'short_url' => short_url((string) $result['code']),
        'short_url_query' => short_url((string) $result['code'], false),
        'short_url_pretty' => short_url((string) $result['code'], true),
        'expires_at' => $link['expires_at'] ?? null,
        'private' => (int) ($link['is_private'] ?? 0) === 1,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function links_page_url(array $extra = []): string
{
    [, , , $status, $sort, $q] = link_filter();
    $query = ['page' => 'links'];
    if ($q !== '') {
        $query['q'] = $q;
    }
    if ($status !== 'all') {
        $query['status'] = $status;
    }
    if ($sort !== 'new') {
        $query['sort'] = $sort;
    }
    foreach ($extra as $key => $value) {
        $query[$key] = $value;
    }
    return page_url($query);
}
