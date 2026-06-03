<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\ConfigurationInterface;
use Loupe\Matcher\Tokenizer\Decompounder\DefaultConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\DefaultTermValidator;

class English extends AbstractPreconfiguredLocale
{
    /**
     * In English, there are thousands of valid words with 3 letters.
     * For performance reasons, it might be smart to increase this to 4
     * and have an allow list for the shorter terms but that's not doable
     * if the allow list would contain thousands of terms.
     */
    public const MIN_DECOMPOSITION_TERM_LENGTH = 3;

    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    protected function getDecompounderConfiguration(): ConfigurationInterface
    {
        return new DefaultConfiguration(
            $this->getTermPool(new DefaultTermValidator(
                $this->getFastSetDictionary(),
                self::MIN_DECOMPOSITION_TERM_LENGTH,
            )),
            self::MIN_DECOMPOSITION_TERM_LENGTH,
        );
    }
}
