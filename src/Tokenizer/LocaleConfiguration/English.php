<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Configuration;

class English extends AbstractPreconfiguredLocale
{
    public const MIN_DECOMPOSITION_TERM_LENGTH = 3;

    private const ALLOW_LIST = [

    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    protected function getDecompounderConfiguration(): Configuration
    {
        return (new Configuration(
            $this->wrapDictionaryWithInMemoryCacheDictionary($this->getFastSetDictionary()),
            self::MIN_DECOMPOSITION_TERM_LENGTH,
        ))->withAllowList(self::ALLOW_LIST);
    }
}
