<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Game\Api\Validation\ApiValidation;
use Game\I18n\ApiTranslator;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$projectRoot = dirname(__DIR__);
$apiTranslator = ApiTranslator::createForProject($projectRoot);
$lang = $_ENV['APP_LANG'] ?? \getenv('APP_LANG');
$apiTranslator->setLocale(\is_string($lang) && $lang !== '' ? $lang : 'en');
ApiValidation::configure($apiTranslator->symfonyTranslator(), 'api');
