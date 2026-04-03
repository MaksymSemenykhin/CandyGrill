<?php

declare(strict_types=1);

// php -S: some setups omit Authorization in $_SERVER['HTTP_AUTHORIZATION'] even when getallheaders() exposes it.
if (
    PHP_SAPI === 'cli-server'
    && function_exists('getallheaders')
) {
    $hasServerAuth = (isset($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] !== '')
        || (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] !== '');
    if (!$hasServerAuth) {
        foreach (getallheaders() ?: [] as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') !== 0 || !is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $_SERVER['HTTP_AUTHORIZATION'] = $value;
            break;
        }
    }
}

use Game\Api\Kernel;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

try {
    Kernel::boot($root)->run();
} catch (\JsonException) {
    \header('Content-Type: application/json; charset=utf-8', true, 500);
    echo '{"ok":false,"error":{"code":"server_error","message":"JSON encoding failed."}}';
}
