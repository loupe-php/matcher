<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Span;

class Scorer
{
    /**
     * Score a candidate snippet (the chunk of text we're considering keeping)
     * against the match spans found in the source text.
     *
     * Returns a sortable tuple where larger = better snippet:
     *   [distinct query terms covered, total matched tokens inside, -snippet length]
     *
     * Distinct-terms primary keeps single-term spam from outranking snippets that
     * cover more of the query; the negative-length tiebreak prefers tighter snippets.
     *
     * Snippet and match spans must share the same coordinate system.
     *
     * @param Span        $snippet    the candidate window of text being scored
     * @param MatchSpan[] $matchSpans match positions plus their matched terms
     * @return array{int, int, int}
     */
    public function scoreSnippet(Span $snippet, array $matchSpans): array
    {
        $distinct = [];
        $total = 0;

        foreach ($matchSpans as $matchSpan) {
            if ($matchSpan->getStartPosition() < $snippet->getStartPosition()
                || $matchSpan->getEndPosition() > $snippet->getEndPosition()) {
                continue;
            }
            foreach ($matchSpan->getTerms() as $term) {
                $distinct[$term] = true;
                $total++;
            }
        }

        return [\count($distinct), $total, -$snippet->getLength()];
    }
}
