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

class French extends AbstractPreconfiguredLocale
{
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
            $this->getTermPool(new DefaultTermValidator($this->getFastSetDictionary(), 4, ['vis' => true])),
            3,
        );
    }
}
