<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantExpanderInterface;
use Loupe\Matcher\Tokenizer\Decompounder\ResultCacheConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;
use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\TermValidatorInterface;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Loupe\Matcher\Tokenizer\Token;

abstract class AbstractPreconfiguredLocale implements LocaleConfigurationInterface
{
    private readonly Decompounder $decompounder;

    public function __construct(
        bool $keepIntermediateTerms = true,
        private readonly string|null $fastSetCacheDirectory = null,
        ResultCacheConfiguration|null $resultCacheConfiguration = null,
    ) {
        $this->decompounder = new Decompounder(
            $this->getDecompounderConfiguration(),
            $keepIntermediateTerms,
            $resultCacheConfiguration,
        );
    }

    public function clearCache(): void
    {
        $this->decompounder->clearResultCache();
    }

    public function enhanceToken(Token $token): Token
    {
        $variants = $this->decompounder->decompoundTerm($token->getTerm());
        if ([] === $variants) {
            return $token;
        }

        return $token->withAddedVariants($variants);
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }

    abstract protected function getDecompounderConfiguration(): ConfigurationInterface;

    protected function getFastSetDictionary(): FastSetDictionary
    {
        $locale = $this->getLocale()->toString();
        $dictionaryDirectory = __DIR__.'/../../../dictionaries/'.$locale;
        $cacheDirectory = $this->fastSetCacheDirectory ? $this->fastSetCacheDirectory.'/'.$locale : null;

        return new FastSetDictionary($dictionaryDirectory, $cacheDirectory);
    }

    protected function getTermPool(TermValidatorInterface $termValidator): TermPool
    {
        return new TermPool($termValidator);
    }

    protected function wrapDictionaryWithVariantDictionary(DictionaryInterface $dictionary, VariantExpanderInterface $variantExpander): DictionaryInterface
    {
        return new VariantDictionary($dictionary, $variantExpander);
    }
}
