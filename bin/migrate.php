<?php

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

require $root . '/vendor/autoload.php';

// phinx.php also calls safeLoad(); repeated call is harmless.
Dotenv\Dotenv::createImmutable($root)->safeLoad();

$phinx = escapeshellarg($root . '/vendor/bin/phinx');
$php = escapeshellarg(PHP_BINARY);
passthru("{$php} {$phinx} migrate", $exitCode);

exit((int) $exitCode);
