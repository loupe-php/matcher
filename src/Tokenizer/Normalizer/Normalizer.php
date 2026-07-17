<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Normalizer;

class Normalizer implements NormalizerInterface
{
    private \Transliterator|null $transliterator = null;

    public function normalize(string $term): string
    {
        $term = $this->transliterateToAscii($term);

        return mb_strtolower($term, 'UTF-8');
    }

    private function transliterateToAscii(string $term): string
    {
        $transliterator = $this->transliterator;

        if (null === $transliterator) {
            $transliterator = \Transliterator::create('NFKD; [:Nonspacing Mark:] Remove; Latin-ASCII');
            if (!$transliterator instanceof \Transliterator) {
                return $term;
            }
            $this->transliterator = $transliterator;
        }

        $result = $transliterator->transliterate($term);

        return false !== $result ? $result : $term;
    }
}
