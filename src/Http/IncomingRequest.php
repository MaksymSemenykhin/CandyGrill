<?php

declare(strict_types=1);

namespace Game\Http;

final class IncomingRequest
{
    /**
     * @param array<string, string> $headers Lowercased header names
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly string $rawBody,
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

        $body = \file_get_contents('php://input');

        return new self($method, $path, $headers, \is_string($body) ? $body : '');
    }

    public function header(string $name): ?string
    {
        $key = \strtolower($name);

        return $this->headers[$key] ?? null;
    }
}
