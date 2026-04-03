<?php

declare(strict_types=1);

namespace Game\I18n;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Загружает домен **api** из файлов `translations/api.<lang>.yaml` (`en` / `ru`, Symfony Translation).
 */
final class ApiTranslator
{
    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public static function createForProject(string $projectRoot): self
    {
        $t = new Translator(self::defaultLangFromEnv());
        $t->addLoader('yaml', new YamlFileLoader());
        foreach (['en', 'ru'] as $lang) {
            $file = $projectRoot . '/translations/api.' . $lang . '.yaml';
            if (is_file($file)) {
                $t->addResource('yaml', $file, $lang, 'api');
            }
        }
        $t->setFallbackLocales(['en']);

        return new self($t);
    }

    private static function defaultLangFromEnv(): string
    {
        $v = $_ENV['APP_LANG'] ?? getenv('APP_LANG');

        return (\is_string($v) && $v === 'ru') ? 'ru' : 'en';
    }

    public function symfonyTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * @param array<string, int|float|string|\Stringable> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $lang = null): string
    {
        return $this->translator->trans($id, $parameters, 'api', $lang ?? $this->translator->getLocale());
    }

    public function setLocale(string $lang): void
    {
        $this->translator->setLocale($lang);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
