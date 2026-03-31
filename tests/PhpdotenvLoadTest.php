<?php

declare(strict_types=1);

namespace Game\Tests;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class PhpdotenvLoadTest extends TestCase
{
    public function testVlucasDotenvPopulatesEnv(): void
    {
        $key = 'CANDYGRILL_VLUCAS_' . bin2hex(random_bytes(6));
        $dir = sys_get_temp_dir() . '/candygrill_dotenv_' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $this->assertNotFalse(file_put_contents($dir . '/.env', "{$key}=loaded_value\n"));

        if (\array_key_exists($key, $_ENV)) {
            unset($_ENV[$key]);
        }
        \putenv($key);

        Dotenv::createImmutable($dir)->load();

        $this->assertSame('loaded_value', $_ENV[$key] ?? null);

        unset($_ENV[$key]);
        \putenv($key);
        unlink($dir . '/.env');
        rmdir($dir);
    }
}
