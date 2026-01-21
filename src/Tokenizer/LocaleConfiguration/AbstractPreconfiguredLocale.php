<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\Decompounder\Configuration;
use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\MemoryCacheDictionary;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Loupe\Matcher\Tokenizer\Token;

abstract class AbstractPreconfiguredLocale implements LocaleConfigurationInterface
{
    private Decompounder $decompounder;

    public function __construct()
    {
        $this->decompounder = new Decompounder($this->getDecompounderConfiguration());
    }

    public function enhanceToken(Token $token): Token
    {
        return $token->withAddedVariants($this->decompounder->decompoundTerm($token->getTerm(), $token->getLength()));
    }

    public function getDictionary(): DictionaryInterface
    {
        return new MemoryCacheDictionary(new FastSetDictionary(
            $this->getLocale(),
            __DIR__ . '/../../../dictionaries/' . $this->getLocale()->toString()
        ), $this->getNumberOfCacheEntriesForMemoryCacheDictionary());
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }

    abstract protected function getDecompounderConfiguration(): Configuration;

    protected function getNumberOfCacheEntriesForMemoryCacheDictionary(): int
    {
        // Default to 15k entries. This should be a fair balance for fast lookups for a lot of terms
        // while ensuring memory is low. Should end up being a max of 2 - 3 MB of RAM depending
        // on the length of the terms.
        return 15_000;
    }
}
