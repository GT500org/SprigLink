<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in server only:
 *   php -S 0.0.0.0:8080 -t . router.php
 * nginx and Apache should use index.php. This file is not required there.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';
if ($uri === '' || $uri[0] !== '/') {
    $uri = '/' . ltrim($uri, '/');
}

$blocked = $uri !== '/' && (
    (bool) preg_match('#^/(?:app|templates|data|examples|tests)(?:/|$)#i', $uri)
    || (bool) preg_match('#^/(?:config\.php|router\.php)$#i', $uri)
    || (bool) preg_match('#(?:^|/)\.#', $uri)
    || (bool) preg_match('#\.(?:sqlite3?|db)(?:-wal|-shm)?$#i', $uri)
);
if ($blocked || str_contains($uri, "\0")) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo "Forbidden\n";
    return true;
}

$root = realpath(__DIR__);
$full = realpath(__DIR__ . $uri);
$inside = $root !== false
    && $full !== false
    && ($full === $root || str_starts_with($full, $root . DIRECTORY_SEPARATOR));
$rel = ($inside && $full !== $root) ? substr($full, strlen($root)) : '';
$privateFile = $rel !== '' && (
    (bool) preg_match('#^/(?:app|templates|data|examples|tests)(?:/|$)#i', $rel)
    || (bool) preg_match('#^/(?:config\.php|router\.php)$#i', $rel)
    || (bool) preg_match('#\.(?:sqlite3?|db)(?:-wal|-shm)?$#i', $rel)
);
if ($blocked || $privateFile || str_contains($uri, "\0")) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo "Forbidden\n";
    return true;
}

if ($inside && $uri !== '/' && is_file($full)) {
    return false;
}

require __DIR__ . '/index.php';
