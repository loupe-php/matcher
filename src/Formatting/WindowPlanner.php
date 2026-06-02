<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Span;

/**
 * Window Planner.
 *
 * Plans which regions ("windows") of a source text to keep around match spans.
 * Handles  building candidate windows around matches, scoring them by query coverage,
 * selecting subsets into max fragment budget, and picking single windows centered on
 * the densest match cluster.
 */
class WindowPlanner
{
    private const CROP_BOUNDARY_CHARS = [' ', "\r", "\n", "\t", ','];

    private const TRUNCATION_BOUNDARY_CHARS = [' ', "\t", "\n", "\r"];

    /**
     * Plan a sequence of context windows for cropping, one per match cluster.
     * Selection strategy depends on prioritizeMatches:
     *  - false: return the first N windows in document order
     *  - true: score windows by relevance, pick the best N, return in document order
     *
     * @param MatchSpan[] $matchSpans
     * @return Span[]
     */
    public function planCropWindows(
        string $text,
        array $matchSpans,
        int $cropLength,
        int $tagOverhead = 0,
        int $maxFragments = -1,
        bool $prioritizeMatches = false,
    ): array {
        if ($cropLength <= 0 || $text === '' || $matchSpans === []) {
            return [];
        }

        $windows = $this->buildPerMatchWindows($text, $matchSpans, $cropLength, $tagOverhead);

        if ($maxFragments < 0 || \count($windows) <= $maxFragments) {
            return $windows;
        }

        if ($prioritizeMatches) {
            return $this->selectByPriority($windows, $matchSpans, $maxFragments);
        }

        return \array_slice($windows, 0, $maxFragments);
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
        $bestCentering = 0;
        $bestStart = 0;
        foreach ($candidateStarts as $rawStart) {
            $start = max(0, min($rawStart, $textLength - $windowLength));
            $end = $start + $windowLength;
            $window = new Span($start, $end);
            $score = $this->scoreWindow($window, $matchSpans);
            $centering = $this->centeringScore($window, $matchSpans);
            if ($bestScore === null || $score > $bestScore || ($score === $bestScore && $centering > $bestCentering)) {
                $bestScore = $score;
                $bestCentering = $centering;
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
     * Build one context window per match span. Merge adjacent/overlapping windows.
     *
     * @param MatchSpan[] $matchSpans
     * @return Span[]
     */
    private function buildPerMatchWindows(string $text, array $matchSpans, int $cropLength, int $tagOverhead): array
    {
        $textLength = mb_strlen($text, 'UTF-8');
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

    /**
     * Measure how well the contained matches are centered within a window.
     *
     * @param MatchSpan[] $matchSpans
     */
    private function centeringScore(Span $window, array $matchSpans): int
    {
        $firstMatch = null;
        $lastMatch = null;

        foreach ($matchSpans as $matchSpan) {
            if ($matchSpan->getStartPosition() < $window->getStartPosition()
                || $matchSpan->getEndPosition() > $window->getEndPosition()) {
                continue;
            }
            if ($firstMatch === null || $matchSpan->getStartPosition() < $firstMatch) {
                $firstMatch = $matchSpan->getStartPosition();
            }
            if ($lastMatch === null || $matchSpan->getEndPosition() > $lastMatch) {
                $lastMatch = $matchSpan->getEndPosition();
            }
        }

        if ($firstMatch === null) {
            return 0;
        }

        $leftPad = $firstMatch - $window->getStartPosition();
        $rightPad = $window->getEndPosition() - $lastMatch;

        return min($leftPad, $rightPad);
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
     * Score all windows by query coverage and pick the best N.
     *
     * @param Span[]      $windows
     * @param MatchSpan[] $matchSpans
     * @return Span[] in document order
     */
    private function selectByPriority(array $windows, array $matchSpans, int $maxFragments): array
    {
        $scored = [];
        foreach ($windows as $window) {
            $score = $this->scoreWindow($window, $matchSpans);
            $scored[] = [
                'window' => $window,
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $selected = \array_slice($scored, 0, $maxFragments);

        usort($selected, fn ($a, $b) => $a['window']->getStartPosition() <=> $b['window']->getStartPosition());

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
