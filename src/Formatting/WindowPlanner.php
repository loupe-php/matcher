<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Span;

/**
 * Window Planner.
 *
 * Plans which regions ("windows") of a source text to keep around match spans.
 * Uses a sliding-window greedy algorithm for cropping:
 *  1. Generate candidate windows at each match position (start-aligned + centered)
 *  2. Score each candidate by query coverage [distinct_terms, total_matches, -length]
 *  3. Select the best non-overlapping subset via greedy selection
 *
 * Each crop window is bounded to cropLength (plus minor word-boundary snapping),
 * ensuring predictable output: total <= maxFragments × cropLength + markers.
 *
 * For truncation, picks a single window centered on the densest match cluster.
 */
class WindowPlanner
{
    private const CROP_BOUNDARY_CHARS = [' ', "\r", "\n", "\t", ','];

    private const TRUNCATION_BOUNDARY_CHARS = [' ', "\t", "\n", "\r"];

    /**
     * Plan a sequence of context windows for cropping.
     * Each window is bounded to cropLength (before word-boundary snapping).
     * Selection is greedy and non-overlapping:
     *  - prioritizeMatches=true:  pick best-scoring windows first, return in document order
     *  - prioritizeMatches=false: pick windows in document order (first-come-first-served)
     *
     * @param MatchSpan[] $matchSpans
     * @return Span[]
     */
    public function planCropWindows(
        string $text,
        array $matchSpans,
        int $cropLength,
        int $maxFragments = -1,
        bool $prioritizeMatches = false,
    ): array {
        if ($cropLength <= 0 || $text === '' || $matchSpans === []) {
            return [];
        }

        $candidates = $this->generateCandidateWindows($text, $matchSpans, $cropLength);

        return $this->selectNonOverlapping($candidates, $matchSpans, $maxFragments, $prioritizeMatches);
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
     * Generate candidate windows at each match span position.
     * Two candidates per span: centered on the span, and start-aligned at the span.
     * All candidates are bounded to windowLength and snapped to word boundaries.
     *
     * @param MatchSpan[] $matchSpans
     * @return Span[]
     */
    private function generateCandidateWindows(string $text, array $matchSpans, int $windowLength): array
    {
        $textLength = mb_strlen($text, 'UTF-8');

        if ($textLength <= $windowLength) {
            return [new Span(0, $textLength)];
        }

        $positions = [];
        foreach ($matchSpans as $span) {
            // Centered: window centered on the match span
            $positions[] = $span->getStartPosition() - (int) floor(($windowLength - $span->getLength()) / 2);
            // Start-aligned: window starts at the match span
            $positions[] = $span->getStartPosition();
        }

        $seen = [];
        $windows = [];
        foreach ($positions as $rawPos) {
            $start = max(0, min($rawPos, $textLength - $windowLength));
            $end = min($textLength, $start + $windowLength);

            // Snap to word boundaries
            if ($start > 0) {
                $start = $this->closestCropBoundary($text, $start, false);
            }
            if ($end < $textLength) {
                $end = $this->closestCropBoundary($text, $end, true);
            }

            if ($start >= $end) {
                continue;
            }

            $key = $start . ':' . $end;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $windows[] = new Span($start, $end);
        }

        return $windows;
    }

    /**
     * Score and select non-overlapping windows via greedy selection.
     * With prioritization: pick best-scoring first. Without: pick in document order.
     * Always returns results in document order.
     *
     * @param Span[]      $candidates
     * @param MatchSpan[] $matchSpans
     * @return Span[] in document order
     */
    private function selectNonOverlapping(array $candidates, array $matchSpans, int $maxFragments, bool $prioritizeMatches): array
    {
        $scored = [];
        foreach ($candidates as $window) {
            $score = $this->scoreWindow($window, $matchSpans);
            $centering = $this->centeringScore($window, $matchSpans);
            $scored[] = [
                'window' => $window,
                'score' => $score,
                'centering' => $centering,
            ];
        }

        if ($prioritizeMatches) {
            // Best score first, then best centering, then earliest position
            usort($scored, function ($a, $b) {
                $cmp = $b['score'] <=> $a['score'];
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = $b['centering'] <=> $a['centering'];
                if ($cmp !== 0) {
                    return $cmp;
                }
                return $a['window']->getStartPosition() <=> $b['window']->getStartPosition();
            });
        } else {
            // Document order (first-come-first-served)
            usort($scored, fn ($a, $b) => $a['window']->getStartPosition() <=> $b['window']->getStartPosition());
        }

        $selected = [];
        foreach ($scored as $entry) {
            if ($maxFragments >= 0 && \count($selected) >= $maxFragments) {
                break;
            }

            $candidate = $entry['window'];
            $overlaps = false;
            foreach ($selected as $existing) {
                if ($candidate->getStartPosition() < $existing->getEndPosition()
                    && $existing->getStartPosition() < $candidate->getEndPosition()) {
                    $overlaps = true;
                    break;
                }
            }

            if (!$overlaps) {
                $selected[] = $candidate;
            }
        }

        usort($selected, fn ($a, $b) => $a->getStartPosition() <=> $b->getStartPosition());

        return $selected;
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
        $textLength = mb_strlen($text, 'UTF-8');
        $boundaries = [];
        foreach (self::CROP_BOUNDARY_CHARS as $char) {
            if ($forward) {
                $boundary = mb_strpos($text, $char, $position, 'UTF-8');
                if ($boundary !== false) {
                    $boundaries[] = $boundary;
                }
            } else {
                $boundary = mb_strrpos($text, $char, 0 - ($textLength - $position), 'UTF-8');
                if ($boundary !== false) {
                    $boundaries[] = $boundary + 1;
                }
            }
        }

        if (empty($boundaries)) {
            // No word boundary found — snap to text edge rather than cutting mid-word
            return $forward ? $textLength : 0;
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
