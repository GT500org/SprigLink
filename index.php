<?php
declare(strict_types=1);

// Change these two lines to move the files. Use a full path that starts with /,
// for example '/home/you/private/spriglink-config.php'.
// The database file and its folder are created if they are missing. The config file is not.
$spriglinkConfigFile = __DIR__ . '/config.php';
$spriglinkDatabaseFile = __DIR__ . '/data/spriglink.sqlite';

if (!defined('SPRIGLINK_CONFIG_FILE')) {
    define('SPRIGLINK_CONFIG_FILE', $spriglinkConfigFile);
}
if (!defined('SPRIGLINK_DATABASE_FILE')) {
    define('SPRIGLINK_DATABASE_FILE', $spriglinkDatabaseFile);
}

try {
    require __DIR__ . '/app/bootstrap.php';
    handle_request();
} catch (Throwable $e) {
    $logDir = dirname((string) SPRIGLINK_DATABASE_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    if (!is_dir($logDir) || !is_writable($logDir)) {
        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
    }
    $logFile = $logDir . '/error.log';
    $written = @file_put_contents(
        $logFile,
        gmdate('c') . ' ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n",
        FILE_APPEND
    ) !== false;
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header_remove('X-Powered-By');
    }
    $safe = static function (string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $where = $written
        ? 'A copy was saved to <code>' . $safe($logFile) . '</code>.'
        : 'The error log could not be written. The web server needs permission to write the database folder.';
    echo '<!doctype html><meta charset="utf-8"><title>SprigLink</title><body style="font-family:sans-serif;background:#121512;color:#e7eee4;padding:48px;max-width:40rem">'
        . '<h1>Something went wrong</h1>'
        . '<p>' . $safe($e->getMessage()) . '</p>'
        . '<p style="color:#9aa89a"><code>' . $safe($e->getFile() . ':' . $e->getLine()) . '</code></p>'
        . '<p style="color:#9aa89a">' . $where . '</p>'
        . '</body>';
}
