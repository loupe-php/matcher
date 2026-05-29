<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

class LocaleConfiguration
{
    public static function fromLocaleString(string $locale): ?LocaleConfigurationInterface
    {
        return match ($locale) {
            'de' => new German(),
            'en' => new English(),
            default => null,
        };
    }
}
