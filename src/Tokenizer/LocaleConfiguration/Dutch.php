<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Configuration;

class Dutch extends AbstractPreconfiguredLocale
{
    public function getLocale(): Locale
    {
        return Locale::fromString('nl');
    }

    protected function getDecompounderConfiguration(): Configuration
    {
        $configuration = new Configuration(
            $this->getDictionary(),
            3
        );
        return $configuration->withInterfixes(['s', 'en', 'e']);
    }
}
