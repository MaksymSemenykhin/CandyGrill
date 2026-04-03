<?php

declare(strict_types=1);

namespace Game\Tests;

use Game\Session\FileSessionStore;
use PHPUnit\Framework\TestCase;

final class FileSessionStoreTest extends TestCase
{
    public function testGetCreatesParentDirectoryAndDoesNotWarn(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cg-fs-' . bin2hex(random_bytes(6));
        $path = $base . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'store.json';
        $dir = \dirname($path);
        $this->assertDirectoryDoesNotExist($dir);

        $store = new FileSessionStore($path);
        $this->assertNull($store->get('missing-key'));
        $this->assertDirectoryExists($dir);

        $store->set('k1', 'payload-a', 3600);
        $this->assertSame('payload-a', $store->get('k1'));

        @unlink($path);
        @rmdir($dir);
        @rmdir($base);
    }
}
