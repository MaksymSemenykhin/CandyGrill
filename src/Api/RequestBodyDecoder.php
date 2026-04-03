<?php

declare(strict_types=1);

namespace Game\Api;

use Game\Http\IncomingRequest;
use JsonException;

/**
 * POST body: JSON (default) or `application/x-www-form-urlencoded` (form fields).
 */
final class RequestBodyDecoder
{
    /**
     * @return array<string, mixed>
     *
     * @throws JsonException when JSON body is invalid
     */
    public static function decode(IncomingRequest $req): array
    {
        $raw = $req->rawBody;
        $ct = \strtolower($req->header('Content-Type') ?? '');

        if (\str_contains($ct, 'application/x-www-form-urlencoded')) {
            if ($raw === '') {
                return [];
            }
            $parsed = [];
            \parse_str($raw, $parsed);

            return self::coerceFormValues($parsed);
        }

        if ($raw === '') {
            return [];
        }

        $decoded = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new JsonException('JSON root must be an object');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function coerceFormValues(array $fields): array
    {
        if (isset($fields['user_id']) && \is_string($fields['user_id'])
            && \preg_match('/^\d+$/', $fields['user_id']) === 1) {
            $fields['user_id'] = (int) $fields['user_id'];
        }

        return $fields;
    }
}
