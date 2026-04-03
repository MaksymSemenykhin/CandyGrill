<?php

declare(strict_types=1);

namespace Game\Session;

/**
 * Persists token payloads on disk so `php -S` and similar multi-invocation setups share state.
 * TTL is not enforced (entries are overwritten only on new login/issue in typical use).
 */
final class FileSessionStore implements SessionStore
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    public function get(string $key): ?string
    {
        $map = $this->readMap();

        return $map[$key] ?? null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $map = $this->readMap();
        $map[$key] = $value;
        $this->writeMap($map);
    }

    private function ensureParentDirectory(): void
    {
        $dir = \dirname($this->path);
        if ($dir === '.' || $dir === '' || \is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0777, true) && !\is_dir($dir)) {
            throw new \RuntimeException('Cannot create session store directory: ' . $dir);
        }
    }

    /**
     * @return array<string, string>
     */
    private function readMap(): array
    {
        $this->ensureParentDirectory();
        $h = fopen($this->path, 'cb+');
        if ($h === false) {
            return [];
        }
        try {
            if (!flock($h, LOCK_SH)) {
                return [];
            }
            rewind($h);
            $raw = stream_get_contents($h);
            flock($h, LOCK_UN);
            if ($raw === false || $raw === '') {
                return [];
            }
            try {
                /** @var mixed $data */
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }

            if (!\is_array($data)) {
                return [];
            }
            /** @var array<string, string> $out */
            $out = [];
            foreach ($data as $k => $v) {
                if (\is_string($k) && \is_string($v)) {
                    $out[$k] = $v;
                }
            }

            return $out;
        } finally {
            fclose($h);
        }
    }

    /**
     * @param array<string, string> $map
     */
    private function writeMap(array $map): void
    {
        $this->ensureParentDirectory();
        $h = fopen($this->path, 'cb+');
        if ($h === false) {
            throw new \RuntimeException('Cannot open session store file: ' . $this->path);
        }
        try {
            if (!flock($h, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock session store file: ' . $this->path);
            }
            ftruncate($h, 0);
            rewind($h);
            $json = json_encode($map, JSON_THROW_ON_ERROR);
            fwrite($h, $json);
            fflush($h);
            flock($h, LOCK_UN);
        } finally {
            fclose($h);
        }
    }
}
