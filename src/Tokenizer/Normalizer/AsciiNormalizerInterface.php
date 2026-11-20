<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Normalizer;

/**
 * A normalizer that may change pure-ASCII terms beyond lower-casing them.
 *
 * The tokenizer only lower-cases ASCII terms, skipping the normalizer, as transliteration cannot change them. A
 * normalizer implementing this interface is applied to every term instead.
 */
interface AsciiNormalizerInterface extends NormalizerInterface
{
}
