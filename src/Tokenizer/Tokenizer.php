<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

class Tokenizer implements TokenizerInterface
{
    public const VERSION = '0.3.0'; // Increase this whenever the logic changes so it gives e.g. Loupe the opportunity to detect when a reindex is needed

    private \IntlRuleBasedBreakIterator $breakIterator;

    private ?\Transliterator $transliterator = null;

    public function __construct(
        private ?string $locale = null
    ) {
        $this->breakIterator = \IntlRuleBasedBreakIterator::createWordInstance($this->locale); // @phpstan-ignore-line - null is allowed

    }

    public function matches(Token $token, TokenCollection $tokens): bool
    {
        foreach ($tokens->all() as $checkToken) {
            foreach ($checkToken->allTerms() as $checkTerm) {
                if (\in_array($checkTerm, $token->allTerms(), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function tokenize(string $string, ?int $maxTokens = null): TokenCollection
    {
        $this->breakIterator->setText($string);

        $tokens = new TokenCollection();
        $id = 0;
        $position = 0;
        $phrase = false;
        $negated = false;
        $whitespace = true;

        foreach ($this->breakIterator->getPartsIterator() as $term) {
            // Set negation if the previous token was not a word and we're not in a phrase
            if (!$phrase && $whitespace) {
                $negated = false;
                if ($term === '-') {
                    $negated = true;
                }
            }

            // Toggle phrases between quotes
            if ($term === '"') {
                $phrase = !$phrase;
                if (!$phrase) {
                    $negated = false;
                }
            }

            $status = $this->breakIterator->getRuleStatus();
            $word = $this->isWord($status);
            $whitespace = $this->isWhitespace($status, $term);

            if (!$word) {
                $position += mb_strlen($term, 'UTF-8');
                continue;
            }

            if ($maxTokens !== null && $tokens->count() >= $maxTokens) {
                break;
            }

            // Normalize (NFKC)
            $term = (string) \Normalizer::normalize($term, \Normalizer::NFKC);
            // Decompose accents
            $term = (string) \Normalizer::normalize($term, \Normalizer::FORM_D);
            // Transliterate to ASCII (handles characters like ß, Ł/ł, å/ä/ö that Normalizer doesn't decompose)
            $term = $this->transliterateToAscii($term);
            // Remove any remaining diacritics
            $term = (string) preg_replace('/\p{Mn}+/u', '', $term);
            // Lowercase
            $term = mb_strtolower($term, 'UTF-8');

            $token = new Token(
                $id++,
                $term,
                $position,
                $phrase,
                $negated,
            );

            $position += $token->getLength();
            $tokens->add($token);
        }

        return $tokens;
    }

    private function isWhitespace(?int $status, string $token): bool
    {
        return ($status === null || ($status >= \IntlBreakIterator::WORD_NONE && $status < \IntlBreakIterator::WORD_NONE_LIMIT)) && trim($token) === '';
    }

    private function isWord(?int $status): bool
    {
        return $status >= \IntlBreakIterator::WORD_NONE_LIMIT;
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
