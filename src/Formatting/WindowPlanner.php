<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Span;

/**
 * Plans which regions ("windows") of a source text to keep around match spans.
 *
 * Owns all match-prioritization logic: building candidate windows around matches,
 * scoring them by query coverage, selecting subsets that fit a length budget,
 * and picking single windows centered on the densest match cluster.
 */
class WindowPlanner
{
    private const CROP_BOUNDARY_CHARS = [' ', "\r", "\n", "\t", ','];

    private const TRUNCATION_BOUNDARY_CHARS = [' ', "\t", "\n", "\r"];

    /**
     * Plan a sequence of context windows for cropping, one per match cluster.
     *
     * Without a totalBudget, every match span gets its own window (overlapping ones merged).
     * With a totalBudget, an anchor is selected by score and the best-scoring subset of
     * the remaining windows that fits in the budget is returned.
     *
     * @param MatchSpan[] $matchSpans
     * @return Span[] in document order
     */
    public function planCropWindows(
        string $text,
        array $matchSpans,
        int $cropLength,
        int $tagOverhead,
        ?int $totalBudget,
        int $markerLength,
    ): array {
        if ($cropLength <= 0 || $text === '' || $matchSpans === []) {
            return [];
        }

        $windows = $this->buildPerMatchWindows($text, $matchSpans, $cropLength, $tagOverhead);

        if ($totalBudget === null || \count($windows) <= 1) {
            return $windows;
        }

        return $this->selectByPriority($windows, $matchSpans, $totalBudget, $markerLength);
    }

    /**
     * Plan a single window of the requested length centered on the densest match cluster.
     * Returns null if no viable window can be placed (caller should fall back to head truncation).
     *
     * @param MatchSpan[] $matchSpans
     */
    public function planTruncationWindow(string $text, array $matchSpans, int $windowLength): ?Span
    {
        if ($windowLength <= 0 || $text === '' || $matchSpans === []) {
            return null;
        }

        $textLength = mb_strlen($text, 'UTF-8');
        if ($textLength <= $windowLength) {
            return new Span(0, $textLength);
        }

        $candidateStarts = [];
        foreach ($matchSpans as $matchSpan) {
            $spanStart = $matchSpan->getStartPosition();
            $spanLength = $matchSpan->getLength();
            $candidateStarts[] = $spanStart;
            $candidateStarts[] = $spanStart - (int) floor(($windowLength - $spanLength) / 2);
        }

        $bestScore = null;
        $bestStart = 0;
        foreach ($candidateStarts as $rawStart) {
            $start = max(0, min($rawStart, $textLength - $windowLength));
            $end = $start + $windowLength;
            $score = $this->scoreWindow(new Span($start, $end), $matchSpans);
            if ($bestScore === null || $score > $bestScore || ($score === $bestScore && $start < $bestStart)) {
                $bestScore = $score;
                $bestStart = $start;
            }
        }

        $start = $bestStart;
        $end = min($textLength, $start + $windowLength);

        if ($start > 0) {
            $start = $this->snapStartForwardToWhitespace($text, $start);
        }
        if ($end < $textLength) {
            $end = $this->snapEndBackwardToWhitespace($text, $end);
        }

        if ($start >= $end) {
            return null;
        }

        return new Span($start, $end);
    }

    /**
     * @param array<int, array{window: Span, terms: array<string, true>, total: int, visibleLength: int}> $candidates
     * @return array<int, array{window: Span, terms: array<string, true>, total: int, visibleLength: int}>
     */
    private function bestSubsetWithinBudget(array $candidates, int $budget, int $markerLength): array
    {
        $n = \count($candidates);
        if ($n === 0) {
            return [];
        }

        if ($n > 12) {
            $selected = [];
            $used = 0;
            foreach ($candidates as $entry) {
                $cost = $entry['visibleLength'] + $markerLength;
                if ($used + $cost <= $budget) {
                    $selected[] = $entry;
                    $used += $cost;
                }
            }
            return $selected;
        }

        $bestKey = [-1, -1, 0];
        $bestPicks = [];
        $cap = 1 << $n;
        for ($mask = 1; $mask < $cap; $mask++) {
            $cost = 0;
            $terms = [];
            $total = 0;
            $length = 0;
            $picks = [];
            for ($i = 0; $i < $n; $i++) {
                if (($mask & (1 << $i)) === 0) {
                    continue;
                }
                $entry = $candidates[$i];
                $cost += $entry['visibleLength'] + $markerLength;
                if ($cost > $budget) {
                    break;
                }
                foreach ($entry['terms'] as $t => $_) {
                    $terms[$t] = true;
                }
                $total += $entry['total'];
                $length += $entry['visibleLength'];
                $picks[] = $entry;
            }
            if ($cost > $budget) {
                continue;
            }
            $key = [\count($terms), $total, -$length];
            if ($key > $bestKey) {
                $bestKey = $key;
                $bestPicks = $picks;
            }
        }

        return $bestPicks;
    }

    /**
     * Build one context window per match span; merge adjacent/overlapping windows.
     *
     * @param MatchSpan[] $matchSpans
     * @return Span[]
     */
    private function buildPerMatchWindows(string $text, array $matchSpans, int $cropLength, int $tagOverhead): array
    {
        $textLength = mb_strlen($text, 'UTF-8');
        // cropLength historically counts tagged-output chars; in original-text coords we
        // discount per-span tag overhead so the resulting tagged window matches cropLength.
        $effectiveCropLength = max(1, $cropLength - $tagOverhead);
        $windows = [];

        foreach ($matchSpans as $span) {
            $spanLength = $span->getLength();

            if ($spanLength >= $cropLength) {
                $window = $span;
            } else {
                $padding = (int) floor(($cropLength - $spanLength) / 2);
                $contextStart = max(0, $span->getStartPosition() - $padding);
                $contextEnd = min($textLength, $span->getEndPosition() + $padding);
                $adjustedStart = max(0, min($contextStart, $span->getEndPosition() - $effectiveCropLength));
                $adjustedEnd = min($textLength, max($contextEnd, $span->getStartPosition() + $effectiveCropLength));

                $window = new Span(
                    $this->closestCropBoundary($text, $adjustedStart, false),
                    $this->closestCropBoundary($text, $adjustedEnd, true),
                );
            }

            $prev = $windows[\count($windows) - 1] ?? null;
            if ($prev && $prev->getEndPosition() >= $window->getStartPosition()) {
                $window = new Span($prev->getStartPosition(), max($prev->getEndPosition(), $window->getEndPosition()));
                array_pop($windows);
            }

            $windows[] = $window;
        }

        return $windows;
    }

    private function closestCropBoundary(string $text, int $position, bool $forward): int
    {
        $boundaries = [];
        foreach (self::CROP_BOUNDARY_CHARS as $char) {
            if ($forward) {
                $boundary = mb_strpos($text, $char, $position, 'UTF-8');
                if ($boundary !== false) {
                    $boundaries[] = $boundary;
                }
            } else {
                $boundary = mb_strrpos($text, $char, 0 - (mb_strlen($text) - $position), 'UTF-8');
                if ($boundary !== false) {
                    $boundaries[] = $boundary + 1;
                }
            }
        }

        if (empty($boundaries)) {
            return $position;
        }

        return $forward ? min($boundaries) : max($boundaries);
    }

    /**
     * Score a candidate window against the match spans.
     * Larger tuple = better window: [distinct query terms, total matched tokens, -length].
     *
     * @param MatchSpan[] $matchSpans
     * @return array{int, int, int}
     */
    private function scoreWindow(Span $window, array $matchSpans): array
    {
        $distinct = [];
        $total = 0;
        foreach ($matchSpans as $matchSpan) {
            if ($matchSpan->getStartPosition() < $window->getStartPosition()
                || $matchSpan->getEndPosition() > $window->getEndPosition()) {
                continue;
            }
            foreach ($matchSpan->getTerms() as $term) {
                $distinct[$term] = true;
                $total++;
            }
        }
        return [\count($distinct), $total, -$window->getLength()];
    }

    /**
     * Anchor on the highest-scoring window; pick the best subset of the rest that fits.
     *
     * @param Span[]      $windows
     * @param MatchSpan[] $matchSpans
     * @return Span[] in document order
     */
    private function selectByPriority(array $windows, array $matchSpans, int $budget, int $markerLength): array
    {
        $scored = [];
        foreach ($windows as $window) {
            $terms = [];
            $total = 0;
            foreach ($matchSpans as $matchSpan) {
                if ($matchSpan->getStartPosition() < $window->getStartPosition()
                    || $matchSpan->getEndPosition() > $window->getEndPosition()) {
                    continue;
                }
                foreach ($matchSpan->getTerms() as $term) {
                    $terms[$term] = true;
                    $total++;
                }
            }
            $scored[] = [
                'window' => $window,
                'terms' => $terms,
                'total' => $total,
                'visibleLength' => $window->getLength(),
            ];
        }

        usort($scored, function ($a, $b) {
            return [\count($b['terms']), $b['total'], -$b['visibleLength']]
                <=> [\count($a['terms']), $a['total'], -$a['visibleLength']];
        });

        $anchor = $scored[0];
        $rest = \array_slice($scored, 1);
        $selected = [$anchor];
        $remainingBudget = $budget - ($anchor['visibleLength'] + $markerLength);

        if ($remainingBudget > 0 && $rest !== []) {
            foreach ($this->bestSubsetWithinBudget($rest, $remainingBudget, $markerLength) as $entry) {
                $selected[] = $entry;
            }
        }

        usort($selected, function ($a, $b) {
            return $a['window']->getStartPosition() <=> $b['window']->getStartPosition();
        });

        return array_map(fn ($e) => $e['window'], $selected);
    }

    private function snapEndBackwardToWhitespace(string $text, int $position): int
    {
        while ($position > 0) {
            $char = mb_substr($text, $position, 1, 'UTF-8');
            if (\in_array($char, self::TRUNCATION_BOUNDARY_CHARS, true)) {
                return $position;
            }
            $position--;
        }
        return $position;
    }

    private function snapStartForwardToWhitespace(string $text, int $position): int
    {
        $length = mb_strlen($text, 'UTF-8');
        while ($position < $length) {
            $char = mb_substr($text, $position, 1, 'UTF-8');
            if (\in_array($char, self::TRUNCATION_BOUNDARY_CHARS, true)) {
                return $position + 1;
            }
            $position++;
        }
        return $position;
    }
}
