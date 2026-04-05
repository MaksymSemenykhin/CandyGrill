<?php

declare(strict_types=1);

namespace Game\I18n;

use Game\Http\IncomingRequest;

/**
 * Priority: POST body **`lang`** → query **`lang`** → **Accept-Language** → **APP_LANG**.
 */
final class LocaleResolver
{
    /**
     * @param array<string, mixed>|null $body Decoded JSON or form fields (null before body is parsed).
     */
    public static function resolve(?array $body, IncomingRequest $request): string
    {
        if ($body !== null) {
            $fromBody = self::firstString($body, ['lang']);
            $n = self::normalizeLocale($fromBody);
            if ($n !== null) {
                return $n;
            }
        }

        $fromQuery = self::firstString($request->query, ['lang']);
        $n = self::normalizeLocale($fromQuery);
        if ($n !== null) {
            return $n;
        }

        $fromAl = self::localeFromAcceptLanguageHeader($request->header('accept-language'));
        if ($fromAl !== null) {
            return $fromAl;
        }

        $fallback = $_ENV['APP_LANG'] ?? \getenv('APP_LANG');

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

    /**
     * First supported language in the header wins (RFC 9110-style comma list; ignores q-values).
     */
    private static function localeFromAcceptLanguageHeader(?string $al): ?string
    {
        if ($al === null || $al === '') {
            return null;
        }
        foreach (\preg_split('/\s*,\s*/', $al) ?: [] as $part) {
            $part = \trim((string) (\explode(';', $part, 2)[0] ?? ''));
            if ($part === '') {
                continue;
            }
            $n = self::normalizeLocale($part);
            if ($n !== null) {
                return $n;
            }
        }

        return null;
    }
}
