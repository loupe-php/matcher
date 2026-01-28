<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanDecompounderConfiguration;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanNormalizer;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanVariantExpander;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class German extends AbstractPreconfiguredLocale
{
    public function getLocale(): Locale
    {
        return Locale::fromString('de');
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new GermanNormalizer(new Normalizer());
    }

    protected function getDecompounderConfiguration(): ConfigurationInterface
    {
        $dictionary = $this->getFastSetDictionary();
        $dictionary = $this->wrapDictionaryWithVariantDictionary($dictionary, new GermanVariantExpander());
        $dictionary = $this->wrapDictionaryWithInMemoryCacheDictionary($dictionary);

        return new GermanDecompounderConfiguration($dictionary);
    }
}
