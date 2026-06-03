<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

/**
 * Match Span.
 *
 * Like a Span, but including the matched query terms it contains.
 * Adjacent matches (and stopwords) are merged into a single span.
 */
class MatchSpan extends Span
{
    /**
     * @param array<int, string>
     */
    public function __construct(
        int $startPosition,
        int $endPosition,
        private array $terms = [],
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * @return array<int, string>
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    public function withEndPosition(int $endPosition): self
    {
        return new self($this->getStartPosition(), $endPosition, $this->terms);
    }

    /**
     * @param array<int, string> $terms
     */
    public function withTerms(array $terms): self
    {
        return new self($this->getStartPosition(), $this->getEndPosition(), $terms);
    }
}
