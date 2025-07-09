<?php

declare(strict_types=1);

namespace Loupe\Matcher;

use Loupe\Matcher\StopWords\InMemoryStopWords;
use Loupe\Matcher\StopWords\StopWordsInterface;
use Loupe\Matcher\Tokenizer\Span;
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
            if ($this->tokenizer->matches($textToken, $queryTokens)) {
                $matches->add($textToken);
            }
        }

        return $matches;
    }

    /**
     * Merge adjacent matching tokens, including any surrounding stopwords.
     * @return Span[]
     */
    public function calculateMatchSpans(TokenCollection|string $text, TokenCollection|string $query, TokenCollection $matches): array
    {
        $textTokens = $text instanceof TokenCollection ? $text : $this->tokenizer->tokenize($text);
        $queryTokens = $query instanceof TokenCollection ? $query : $this->tokenizer->tokenize($query);

        $spans = [];
        $currentSpan = null;
        $prevWasRelevant = false;
        $nextIsRelevant = null;

        foreach ($textTokens->all() as $i => $textToken) {
            $isRelevant = $nextIsRelevant ?? $this->isRelevantToken($textToken, $queryTokens, $matches);
            $nextIsRelevant = $textTokens->atIndex($i + 1)
                ? $this->isRelevantToken($textTokens->atIndex($i + 1), $queryTokens, $matches)
                : false;

            switch (true) {
                case $currentSpan && $isRelevant:
                case $currentSpan && $prevWasRelevant:
                    // Extend the current span
                    $currentSpan = $currentSpan->withEndPosition($textToken->getEndPosition());
                    break;
                case !$currentSpan && $isRelevant:
                    // Start a new span
                    $currentSpan = new Span($textToken->getStartPosition(), $textToken->getEndPosition());
                    break;
                case $currentSpan && !$isRelevant:
                    // Close the current span
                    $spans[] = $currentSpan;
                    $currentSpan = null;
                    break;
                default:
                    // No action needed, continue
                    break;
            }
        }

        // If we have an open span at the end, close it
        if ($currentSpan) {
            $spans[] = $currentSpan;
        }

        return $spans;
    }

    private function isRelevantToken(Token $token, TokenCollection $query, TokenCollection $matches): bool
    {
        // Must be in the matches at exactly the same position
        if ($matches->contains($token, checkPosition: true)) {
            return true;
        }

        // Must be a stopword and at any position in the query
        if ($this->stopWords->isStopWord($token) && $query->contains($token, checkPosition: false)) {
            return true;
        }

        return false;
    }
}
