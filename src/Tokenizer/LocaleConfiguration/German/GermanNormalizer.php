<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration\German;

use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class GermanNormalizer implements NormalizerInterface
{
    public function __construct(
        private NormalizerInterface $inner
    ) {

    }

    public function normalize(string $term): string
    {
        return $this->inner->normalize(strtr($term, [
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ]));
    }
}
