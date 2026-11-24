<?php
declare(strict_types=1);

/**
 * Copy to the path in $spriglinkConfigFile at the top of index.php.
 * The default is config.php next to index.php.
 * Then set the password. To put this file outside the website, change that variable.
 */
return [
    'admin_username' => 'admin',
    'admin_password' => 'change-this-password',
    'base_url' => '',
    'pretty_urls' => false,
    'show_public_links' => true,
    'timezone' => 'UTC',
    'code_length' => 6,
    'geo_lookup' => true,
    'trust_proxy' => false,
    'hide_referrer' => false,
    'api_token' => '',
];
