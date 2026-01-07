<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\MemoryCacheDictionary;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class LocaleConfigurationWithInMemoryCachedDictionary implements LocaleConfigurationInterface
{
    private DictionaryInterface $dictionary;

    public function __construct(
        private LocaleConfigurationInterface $inner
    ) {
        $this->dictionary = new MemoryCacheDictionary($this->inner->getDictionary());
    }

    public function getDictionary(): DictionaryInterface
    {
        return $this->dictionary;
    }

    public function getInterfixes(): array
    {
        return $this->inner->getInterfixes();
    }

    public function getLocale(): Locale
    {
        return $this->inner->getLocale();
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return $this->inner->getMinimumDecompositionTermLength();
    }

    public function getNormalizer(): NormalizerInterface
    {
        return $this->inner->getNormalizer();
    }
}
