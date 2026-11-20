<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration\French;

use Loupe\Matcher\Tokenizer\Normalizer\AsciiNormalizerInterface;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

/**
 * Drops an elided article or pronoun from the start of a term: `l’acompte` and `acompte` become the same term.
 *
 * ICU joins letters across both the straight and the typographic apostrophe (UAX #29), so `l’acompte` is a single
 * word, and only when a letter follows the apostrophe. The particles are those of Lucene's French ElisionFilter. A
 * particle that ends a longer word (`aujourd’hui`) is kept.
 */
class FrenchNormalizer implements AsciiNormalizerInterface
{
    private const ELISION = "/^(?:c|d|j|l|m|n|s|t|qu|jusqu|lorsqu|puisqu|quoiqu)'/";

    public function __construct(private readonly NormalizerInterface $inner)
    {
    }

    public function normalize(string $term): string
    {
        // The inner normalizer lower-cases the term and turns the typographic apostrophe into a straight one.
        $term = $this->inner->normalize($term);

        return preg_replace(self::ELISION, '', $term) ?? $term;
    }
}
