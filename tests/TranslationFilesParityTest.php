<?php

declare(strict_types=1);

namespace Game\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps `translations/api.en.yaml` and `api.ru.yaml` in lockstep (same keys).
 */
final class TranslationFilesParityTest extends TestCase
{
    public function testEnAndRuApiYamlHaveIdenticalKeysInTheSameOrder(): void
    {
        $root = dirname(__DIR__);
        /** @var array<string, string> $en */
        $en = Yaml::parseFile($root . '/translations/api.en.yaml');
        /** @var array<string, string> $ru */
        $ru = Yaml::parseFile($root . '/translations/api.ru.yaml');

        $this->assertNotSame([], $en);
        $this->assertNotSame([], $ru);
        $this->assertSame(array_keys($en), array_keys($ru), 'Add the missing key to both api.en.yaml and api.ru.yaml.');
    }

    public function testEveryTranslationValueIsNonEmptyString(): void
    {
        $root = dirname(__DIR__);
        foreach (['en', 'ru'] as $lang) {
            /** @var array<string, string> $map */
            $map = Yaml::parseFile($root . '/translations/api.' . $lang . '.yaml');
            foreach ($map as $id => $text) {
                $this->assertIsString($text, "{$lang}: {$id}");
                $this->assertNotSame('', trim($text), "{$lang}: {$id} is empty");
            }
        }
    }
}
