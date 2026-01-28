<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\DefaultTermValidator;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanDecompounderConfiguration;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanNormalizer;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanVariantExpander;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class German extends AbstractPreconfiguredLocale
{
    public const MIN_DECOMPOSITION_TERM_LENGTH = 4;

    private const ALLOW_LIST = [
        'amt' => true,
        'art' => true,
        'bad' => true,
        'bau' => true,
        'bus' => true,
        'ehe' => true,
        'eis' => true,
        'erz' => true,
        'fee' => true,
        'gut' => true,
        'hof' => true,
        'hut' => true,
        'klo' => true,
        'mut' => true,
        'rad' => true,
        'ruf' => true,
        'see' => true,
        'tag' => true,
        'tee' => true,
        'tal' => true,
        'tor' => true,
        'typ' => true,
        'weg' => true,
        'zug' => true,
        'ei' => true,
    ];

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
        $termPool = $this->getTermPool(
            new DefaultTermValidator(
                $dictionary,
                self::MIN_DECOMPOSITION_TERM_LENGTH,
                self::ALLOW_LIST
            )
        );

        return new GermanDecompounderConfiguration($termPool, self::MIN_DECOMPOSITION_TERM_LENGTH);
    }
}
