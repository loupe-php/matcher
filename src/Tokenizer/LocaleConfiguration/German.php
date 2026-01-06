<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;

class German extends AbstractPreconfiguredLocale
{
    public function getInterfixes(): array
    {
        return ['s', 'es', 'n', 'en', 'er', 'e'];
    }

    public function getLocale(): Locale
    {
        return Locale::fromString('de');
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return 3;
    }
}
