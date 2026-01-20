<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;

class English extends AbstractPreconfiguredLocale
{
    public function getInterfixes(): array
    {
        return [];
    }

    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return 3;
    }
}
