<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantExpanderInterface;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;
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
        return $token->withAddedVariants($this->decompounder->decompoundTerm($token->getTerm()));
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }

    abstract protected function getDecompounderConfiguration(): ConfigurationInterface;

    /**
     * @param array<string,bool> $allowList
     */
    protected function getDefaultIsValidClosureForTermPool(DictionaryInterface $dictionary, array $allowList = []): \Closure
    {
        return function (string $term) use ($dictionary, $allowList): bool {
            $minLength = $this->getDecompounderConfiguration()->getMinimumDecompositionTermLength();

            if (mb_strlen($term) < $minLength) {
                return isset($allowList[$term]);
            }

            return $dictionary->has($term);
        };
    }

    protected function getFastSetDictionary(): FastSetDictionary
    {
        return new FastSetDictionary(__DIR__ . '/../../../dictionaries/' . $this->getLocale()->toString());
    }

    /**
     * Defaults to 30k cache entries. This should be a fair balance for fast lookups for a lot of terms
     * while ensuring memory is low. Should end up being a max of 2 - 3 MB of RAM depending on the length
     * of the terms.
     */
    protected function getTermPool(\Closure $isValidClosure, int $maxCacheEntries = 30_000): TermPool
    {
        return new TermPool($isValidClosure, $maxCacheEntries);
    }

    protected function wrapDictionaryWithVariantDictionary(DictionaryInterface $dictionary, VariantExpanderInterface $variantExpander): DictionaryInterface
    {
        return new VariantDictionary($dictionary, $variantExpander);
    }
}
