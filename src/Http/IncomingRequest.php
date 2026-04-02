<?php

declare(strict_types=1);

namespace Game\Http;

final class IncomingRequest
{
    /**
     * @param array<string, string> $headers Lowercased header names
     * @param array<string, mixed>  $query   Query string (?locale=ru)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly array $query = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $pathPart = \parse_url($uri, PHP_URL_PATH);
        if (!\is_string($pathPart) || $pathPart === '') {
            $path = '/';
        } else {
            $path = $pathPart;
        }

        $headers = [];
        if (\function_exists('getallheaders')) {
            $raw = \getallheaders();
            if (!\is_array($raw)) {
                $raw = [];
            }
            foreach ($raw as $name => $value) {
                $headers[\strtolower((string) $name)] = (string) $value;
            }
        }

        // Built-in server / some SAPIs omit headers from getallheaders(); align with CGI `HTTP_*` entries.
        foreach (
            [
                'authorization' => 'HTTP_AUTHORIZATION',
                'x-session-token' => 'HTTP_X_SESSION_TOKEN',
                'accept-language' => 'HTTP_ACCEPT_LANGUAGE',
            ] as $lower => $serverKey
        ) {
            if (!isset($headers[$lower]) && isset($_SERVER[$serverKey])
                && \is_string($_SERVER[$serverKey]) && $_SERVER[$serverKey] !== '') {
                $headers[$lower] = $_SERVER[$serverKey];
            }
        }

        $body = \file_get_contents('php://input');

        $query = [];
        $qs = \parse_url($uri, PHP_URL_QUERY);
        if (!\is_string($qs) || $qs === '') {
            $qs = '';
            foreach (['QUERY_STRING', 'REDIRECT_QUERY_STRING'] as $serverQueryKey) {
                if (isset($_SERVER[$serverQueryKey]) && \is_string($_SERVER[$serverQueryKey])
                    && $_SERVER[$serverQueryKey] !== '') {
                    $qs = $_SERVER[$serverQueryKey];
                    break;
                }
            }
        }
        if ($qs !== '') {
            \parse_str($qs, $parsed);
            if (\is_array($parsed)) {
                /** @var array<string, mixed> $query */
                $query = $parsed;
            }
        }

        // Some front controllers strip the query from REQUEST_URI but PHP still fills $_GET.
        foreach (['locale', 'lang'] as $qk) {
            if (!\array_key_exists($qk, $query) && isset($_GET[$qk]) && \is_string($_GET[$qk]) && $_GET[$qk] !== '') {
                $query[$qk] = $_GET[$qk];
            }
        }

        return new self($method, $path, $headers, \is_string($body) ? $body : '', $query);
    }

    public function header(string $name): ?string
    {
        $key = \strtolower($name);

        return $this->headers[$key] ?? null;
    }
}
