<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Configuration;

class English extends AbstractPreconfiguredLocale
{
    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    protected function getDecompounderConfiguration(): Configuration
    {
        return new Configuration(
            $this->getDictionary(),
            3
        );
    }
}
