<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\Decompounder\DefaultConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\DefaultTermValidator;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\French\FrenchNormalizer;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Loupe\Matcher\Tokenizer\Token;

class French extends AbstractPreconfiguredLocale
{
    private const MIN_DICTIONARY_TERM_LENGTH = 4;

    public function enhanceToken(Token $token): Token
    {
        // A split needs at least one dictionary term, or two copies of the short term "vis".
        $term = $token->getTerm();
        if ('visvis' !== $term && !preg_match('/^[a-z]{7,}$/D', $term)) {
            return $token;
        }

        return parent::enhanceToken($token);
    }

    public function getLocale(): Locale
    {
        return Locale::fromString('fr');
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new FrenchNormalizer(new Normalizer());
    }

    protected function getDecompounderConfiguration(): ConfigurationInterface
    {
        return new DefaultConfiguration(
            $this->getTermPool(new DefaultTermValidator($this->getFastSetDictionary(), self::MIN_DICTIONARY_TERM_LENGTH, ['vis' => true])),
            3,
        );
    }
}
