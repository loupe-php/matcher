<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Loupe\Matcher\Tokenizer\Token;

interface LocaleConfigurationInterface
{
    /**
     * Use this method to add variants (e.g. decomposition) to the token.
     */
    public function enhanceToken(Token $token): Token;

    public function getLocale(): Locale;

    public function getNormalizer(): NormalizerInterface;
}
