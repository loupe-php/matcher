<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\French\FrenchNormalizer;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;
use Loupe\Matcher\Tokenizer\Token;

class French implements LocaleConfigurationInterface
{
    public function clearCache(): void
    {
    }

    public function enhanceToken(Token $token): Token
    {
        return $token;
    }

    public function getLocale(): Locale
    {
        return Locale::fromString('fr');
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new FrenchNormalizer(new Normalizer());
    }
}
