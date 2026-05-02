<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

/**
 * A Span enriched with the matched query terms it covers.
 *
 * Adjacent matched tokens (and intervening relevant stopwords) are merged into
 * a single span; $terms holds the lowercased term of each matched token within
 * it (stopwords excluded), which lets consumers score distinctness and totals
 * without re-tokenizing the underlying text.
 */
class MatchSpan extends Span
{
    /**
     * @param array<int, string> $terms lowercased terms of the matched tokens covered by this span
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
