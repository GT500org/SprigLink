<?php
declare(strict_types=1);

/**
 * SprigLink settings.
 *
 * The password lives here. Where this file sits, and where the database sits,
 * are the two path variables at the top of index.php. On shared hosting, point
 * both outside the website folder when the host allows it.
 */
return [
    // Sign-in for index.php?page=admin
    'admin_username' => 'admin',

    // A plain password, or a password_hash() string (starts with $2y$ or $argon2).
    //   php -r "echo password_hash('a strong password', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_password' => 'change-this-password',

    // Defaults until you save Settings. After that, Settings wins.
    // Public URL of this install, without a trailing slash.
    // Leave '' to detect it. Works on a dedicated host or in a subfolder.
    'base_url' => '',

    // Default shape of copied links, until you change it under Settings.
    // false: links look like https://example.com/?c=abc123
    //        and work on a normal PHP site. No rewrite rule.
    // true:  links look like https://example.com/abc123
    //        Nginx or Apache must send unknown paths to index.php.
    //        The steps are on the Settings page.
    'pretty_urls' => false,

    // Public links (not marked private) are listed on the homepage.
    // Settings can turn that list off later. That choice is stored in the database.
    'show_public_links' => true,

    // Expiry entry and the way dates are shown.
    // Examples: UTC, America/New_York, America/Chicago, Europe/London
    'timezone' => 'UTC',

    // Length of generated codes.
    'code_length' => 6,

    // Resolve country, region, and city for public IPs when you open stats.
    // Uses ip-api.com (no API key). Set false to keep IP, browser, and referrer only.
    // A Cloudflare CF-IPCountry header is stored either way when it is present.
    'geo_lookup' => true,

    // Enable only if nginx (or another proxy you control) is the sole entry
    // and sets X-Forwarded-For plus X-Forwarded-Proto.
    'trust_proxy' => false,

    // Do not tell the destination site which short link sent the visitor.
    'hide_referrer' => false,

    // Optional API. Leave '' to disable it.
    // POST JSON to index.php?page=api — see README.
    'api_token' => '',
];
