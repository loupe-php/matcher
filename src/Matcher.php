<?php

declare(strict_types=1);

namespace Loupe\Matcher;

use Loupe\Matcher\StopWords\InMemoryStopWords;
use Loupe\Matcher\StopWords\StopWordsInterface;
use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Token;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\TokenizerInterface;

class Matcher
{
    private StopWordsInterface $stopWords;

    /**
     * @param StopWordsInterface|array<string> $stopWords
     */
    public function __construct(
        private TokenizerInterface $tokenizer,
        StopWordsInterface|array $stopWords = []
    ) {
        $this->stopWords = $stopWords instanceof StopWordsInterface ? $stopWords : new InMemoryStopWords($stopWords);
    }

    public function calculateMatches(TokenCollection|string $text, TokenCollection|string $query): TokenCollection
    {
        if ($text === '') {
            return new TokenCollection();
        }

        $textTokens = $text instanceof TokenCollection ? $text : $this->tokenizer->tokenize($text);
        $queryTokens = $query instanceof TokenCollection ? $query : $this->tokenizer->tokenize($query);

        $matches = new TokenCollection();
        foreach ($textTokens->all() as $textToken) {
            if (!$this->stopWords->isStopWord($textToken) && $this->tokenizer->matches($textToken, $queryTokens)) {
                $matches->add($textToken);
            }
        }

        return $matches;
    }

    /**
     * Merge adjacent matching tokens, including any surrounding stopwords.
     * @return MatchSpan[]
     */
    public function calculateMatchSpans(TokenCollection|string $text, TokenCollection|string $query, TokenCollection $matches): array
    {
        $textTokens = $text instanceof TokenCollection ? $text : $this->tokenizer->tokenize($text);
        $queryTokens = $query instanceof TokenCollection ? $query : $this->tokenizer->tokenize($query);

        $spans = [];
        $currentSpan = null;
        $currentSpanTerms = [];
        $currentSpanHasMatch = false;

        foreach ($textTokens->all() as $textToken) {
            $isMatch = $this->isMatch($textToken, $matches);
            $isStopword = $this->isRelevantStopWord($textToken, $queryTokens);
            $isRelevant = $isMatch || $isStopword;

            if ($isMatch) {
                $currentSpanHasMatch = true;
            }

            if (!$isRelevant) {
                // Close the current span
                if ($currentSpan && $currentSpanHasMatch) {
                    $spans[] = $currentSpan->withTerms($currentSpanTerms);
                }
                $currentSpan = null;
                $currentSpanTerms = [];
                $currentSpanHasMatch = false;
                continue;
            }

            if ($currentSpan) {
                $currentSpan = $currentSpan->withEndPosition($textToken->getOriginalEndPosition());
            } else {
                $currentSpan = new MatchSpan($textToken->getOriginalStartPosition(), $textToken->getOriginalEndPosition());
            }

            if ($isMatch) {
                $currentSpanTerms[] = mb_strtolower($textToken->getTerm(), 'UTF-8');
            }
        }

        if ($currentSpan && $currentSpanHasMatch) {
            $spans[] = $currentSpan->withTerms($currentSpanTerms);
        }

        return $spans;
    }

    private function isMatch(Token $token, TokenCollection $matches): bool
    {
        // Must be in the matches at exactly the same position
        return !$this->stopWords->isStopWord($token) && $matches->contains($token, checkPosition: true);
    }

    private function isRelevantStopWord(Token $token, TokenCollection $query): bool
    {
        // Must be a stopword and at any position in the query
        return $this->stopWords->isStopWord($token) && $query->contains($token, checkPosition: false);
    }
}
