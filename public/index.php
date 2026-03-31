<?php

declare(strict_types=1);

use Game\Api\Kernel;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

try {
    Kernel::boot($root)->run();
} catch (\JsonException) {
    \header('Content-Type: application/json; charset=utf-8', true, 500);
    echo '{"ok":false,"error":{"code":"server_error","message":"JSON encoding failed."}}';
}
