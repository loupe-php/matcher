<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;

class Dutch extends AbstractPreconfiguredLocale
{
    public function getInterfixes(): array
    {
        return ['s', 'en', 'e'];
    }

    public function getLocale(): Locale
    {
        return Locale::fromString('nl');
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return 3;
    }
}
