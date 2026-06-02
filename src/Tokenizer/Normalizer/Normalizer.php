<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Normalizer;

class Normalizer implements NormalizerInterface
{
    private ?\Transliterator $transliterator = null;

    public function normalize(string $term): string
    {
        $term = $this->transliterateToAscii($term);
        $term = mb_strtolower($term, 'UTF-8');

        return $term;
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
