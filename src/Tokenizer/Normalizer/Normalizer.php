<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Normalizer;

class Normalizer implements NormalizerInterface
{
    private ?\Transliterator $transliterator = null;

    public function normalize(string $term): string
    {
        // Normalize (NFKC)
        $term = (string) \Normalizer::normalize($term, \Normalizer::NFKC);
        // Decompose accents
        $term = (string) \Normalizer::normalize($term, \Normalizer::FORM_D);
        // Transliterate to ASCII (handles characters like ß, Ł/ł, å/ä/ö that Normalizer doesn't decompose)
        $term = $this->transliterateToAscii($term);
        // Remove any remaining diacritics
        $term = (string) preg_replace('/\p{Mn}+/u', '', $term);
        // Lowercase
        return mb_strtolower($term, 'UTF-8');
    }

    private function transliterateToAscii(string $term): string
    {
        $transliterator = $this->transliterator;

        if ($transliterator === null) {
            $transliterator = \Transliterator::create('NFKD; [:Nonspacing Mark:] Remove; Latin-ASCII');
            if (!$transliterator instanceof \Transliterator) {
                return $term;
            }
            $this->transliterator = $transliterator;
        }

        $result = $transliterator->transliterate($term);

        return $result !== false ? $result : $term;
    }
}
