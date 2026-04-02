<?php

declare(strict_types=1);

namespace Game\I18n;

use Game\Http\IncomingRequest;

/**
 * Приоритет: тело POST (**`locale`** / **`lang`**) → query **`locale`** / **`lang`** → **Accept-Language** → **APP_LOCALE**.
 */
final class LocaleResolver
{
    /**
     * @param array<string, mixed>|null $body Decoded JSON or form fields (null до разбора тела).
     */
    public static function resolve(?array $body, IncomingRequest $request): string
    {
        if ($body !== null) {
            $fromBody = self::firstString($body, ['locale', 'lang']);
            $n = self::normalizeLocale($fromBody);
            if ($n !== null) {
                return $n;
            }
        }

        $fromQuery = self::firstString($request->query, ['locale', 'lang']);
        $n = self::normalizeLocale($fromQuery);
        if ($n !== null) {
            return $n;
        }

        $al = $request->header('accept-language');
        if ($al !== null && $al !== '' && \preg_match('/\bru\b/i', $al) === 1) {
            return 'ru';
        }

        $fallback = $_ENV['APP_LOCALE'] ?? \getenv('APP_LOCALE');

        return (\is_string($fallback) && $fallback === 'ru') ? 'ru' : 'en';
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string>         $keys
     */
    private static function firstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $source)) {
                continue;
            }
            $v = $source[$key];
            if (\is_string($v)) {
                return $v;
            }
        }

        return null;
    }

    private static function normalizeLocale(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = \strtolower(\str_replace('_', '-', \trim($raw)));
        if ($s === '') {
            return null;
        }

        if ($s === 'ru' || \str_starts_with($s, 'ru-')) {
            return 'ru';
        }

        if ($s === 'en' || \str_starts_with($s, 'en-')) {
            return 'en';
        }

        return null;
    }
}
