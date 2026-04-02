<?php

declare(strict_types=1);

namespace Game\I18n;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Загружает домен **api** из {@see translations/api.{locale}.yaml} (Symfony Translation).
 */
final class ApiTranslator
{
    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public static function createForProject(string $projectRoot): self
    {
        $t = new Translator(self::defaultLocaleFromEnv());
        $t->addLoader('yaml', new YamlFileLoader());
        foreach (['en', 'ru'] as $locale) {
            $file = $projectRoot . '/translations/api.' . $locale . '.yaml';
            if (is_file($file)) {
                $t->addResource('yaml', $file, $locale, 'api');
            }
        }
        $t->setFallbackLocales(['en']);

        return new self($t);
    }

    private static function defaultLocaleFromEnv(): string
    {
        $v = $_ENV['APP_LOCALE'] ?? getenv('APP_LOCALE');

        return (\is_string($v) && $v === 'ru') ? 'ru' : 'en';
    }

    public function symfonyTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * @param array<string, int|float|string|\Stringable> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, 'api', $locale ?? $this->translator->getLocale());
    }

    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
