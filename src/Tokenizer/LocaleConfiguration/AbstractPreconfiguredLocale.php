<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\Decompounder\Configuration;
use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
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

    public function getDictionary(): FastSetDictionary
    {
        return new FastSetDictionary(
            $this->getLocale(),
            __DIR__ . '/../../../dictionaries/' . $this->getLocale()->toString()
        );
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }

    abstract protected function getDecompounderConfiguration(): Configuration;
}
