<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Game\Api\Validation\ApiValidation;
use Game\I18n\ApiTranslator;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$projectRoot = dirname(__DIR__);
$apiTranslator = ApiTranslator::createForProject($projectRoot);
$locale = $_ENV['APP_LOCALE'] ?? \getenv('APP_LOCALE');
$apiTranslator->setLocale(\is_string($locale) && $locale !== '' ? $locale : 'en');
ApiValidation::configure($apiTranslator->symfonyTranslator(), 'api');
