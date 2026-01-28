<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\MemoryCacheDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantExpanderInterface;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Loupe\Matcher\Tokenizer\Token;

abstract class AbstractPreconfiguredLocale implements LocaleConfigurationInterface
{
    private Decompounder $decompounder;

    public function __construct(bool $keepIntermediateTerms = true)
    {
        $this->decompounder = new Decompounder($this->getDecompounderConfiguration(), $keepIntermediateTerms);
    }

    public function enhanceToken(Token $token): Token
    {
        return $token->withAddedVariants($this->decompounder->decompoundTerm($token->getTerm(), $token->getLength()));
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }

    abstract protected function getDecompounderConfiguration(): ConfigurationInterface;

    protected function getFastSetDictionary(): FastSetDictionary
    {
        return new FastSetDictionary(__DIR__ . '/../../../dictionaries/' . $this->getLocale()->toString());
    }

    /**
     * Defaults to 30k cache entries. This should be a fair balance for fast lookups for a lot of terms
     * while ensuring memory is low. Should end up being a max of 2 - 3 MB of RAM depending on the length
     * of the terms.
     */
    protected function wrapDictionaryWithInMemoryCacheDictionary(DictionaryInterface $dictionary, int $maxEntries = 30_000): DictionaryInterface
    {
        return new MemoryCacheDictionary($dictionary, $maxEntries);
    }

    protected function wrapDictionaryWithVariantDictionary(DictionaryInterface $dictionary, VariantExpanderInterface $variantExpander): DictionaryInterface
    {
        return new VariantDictionary($dictionary, $variantExpander);
    }
}
