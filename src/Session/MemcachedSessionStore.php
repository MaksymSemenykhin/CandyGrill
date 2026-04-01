<?php

declare(strict_types=1);

namespace Game\Session;

final class MemcachedSessionStore implements SessionStore
{
    private \Memcached $memcached;

    public function __construct(string $host, int $port)
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('SESSION_DRIVER=memcached requires the memcached PHP extension.');
        }
        $this->memcached = new \Memcached();
        $this->memcached->addServer($host, $port);
    }

    public function get(string $key): ?string
    {
        $v = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }

        return \is_string($v) ? $v : null;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->memcached->set($key, $value, $ttlSeconds);
    }
}
