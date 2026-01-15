<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Normalizer;

interface NormalizerInterface
{
    public function normalize(string $term): string;
}
