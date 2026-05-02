<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Span;

class Cropper implements Transformer
{
    public function __construct(
        private int $cropLength,
        private string $cropMarker,
        private string $highlightStartTag,
        private string $highlightEndTag,
        private bool $prioritizeMatches = false,
        private ?int $truncationBudget = null,
    ) {
    }

    /**
     * Crop pre-highlighted text. Convenience entry-point that parses highlight tags
     * back into spans, runs the spans-based cropping logic, then re-renders tags.
     */
    public function cropHighlightedText(string $text): string
    {
        if ($this->highlightStartTag === '' || $this->highlightEndTag === '') {
            return $text;
        }

        $parsed = $this->parseTaggedText($text);
        $cropped = $this->transform($parsed);
        $highlighter = new Highlighter($this->highlightStartTag, $this->highlightEndTag);
        return $highlighter->transform($cropped)->text;
    }

    public function transform(FormattedText $input): FormattedText
    {
        if ($this->cropLength <= 0 || $input->text === '' || $input->spans === []) {
            return $input;
        }

        $windows = $this->buildWindows($input->text, $input->spans);

        if ($this->prioritizeMatches && $this->truncationBudget !== null) {
            $windows = $this->selectByPriority($input->spans, $windows);
        }

        return $this->renderWindows($input->text, $input->spans, $windows);
    }

    /**
     * @param array<int, array{window: Span, tokens: array<string, true>, totalTokens: int, visibleLength: int}> $candidates
     * @return array<int, array{window: Span, tokens: array<string, true>, totalTokens: int, visibleLength: int}>
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
            $tokens = [];
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
                foreach ($entry['tokens'] as $t => $_) {
                    $tokens[$t] = true;
                }
                $total += $entry['totalTokens'];
                $length += $entry['visibleLength'];
                $picks[] = $entry;
            }
            if ($cost > $budget) {
                continue;
            }
            $key = [\count($tokens), $total, -$length];
            if ($key > $bestKey) {
                $bestKey = $key;
                $bestPicks = $picks;
            }
        }

        return $bestPicks;
    }

    /**
     * Build one context window per span, merging adjacent/overlapping windows.
     *
     * @param Span[] $spans
     * @return Span[]
     */
    private function buildWindows(string $text, array $spans): array
    {
        $textLength = mb_strlen($text, 'UTF-8');
        $tagOverhead = mb_strlen($this->highlightStartTag, 'UTF-8') + mb_strlen($this->highlightEndTag, 'UTF-8');
        // cropLength historically counts tagged-output chars; in original-text coords we
        // discount per-span tag overhead so the resulting tagged window matches cropLength.
        $effectiveCropLength = max(1, $this->cropLength - $tagOverhead);
        $windows = [];

        foreach ($spans as $span) {
            $spanLength = $span->getLength();

            if ($spanLength >= $this->cropLength) {
                $window = $span;
            } else {
                $padding = (int) floor(($this->cropLength - $spanLength) / 2);
                $contextStart = max(0, $span->getStartPosition() - $padding);
                $contextEnd = min($textLength, $span->getEndPosition() + $padding);
                $adjustedStart = max(0, min($contextStart, $span->getEndPosition() - $effectiveCropLength));
                $adjustedEnd = min($textLength, max($contextEnd, $span->getStartPosition() + $effectiveCropLength));

                $window = new Span(
                    $this->closestWordBoundary($text, $adjustedStart, false),
                    $this->closestWordBoundary($text, $adjustedEnd, true),
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

    private function closestWordBoundary(string $string, int $position, bool $forward = true): int
    {
        $boundaries = [];
        foreach ([' ', "\r", "\n", "\t", ','] as $char) {
            if ($forward) {
                $boundary = mb_strpos($string, $char, $position, 'UTF-8');
                if ($boundary !== false) {
                    $boundaries[] = $boundary;
                }
            } else {
                $boundary = mb_strrpos($string, $char, 0 - (mb_strlen($string) - $position), 'UTF-8');
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
     * Parse a string with highlight tags into (originalText, spans).
     */
    private function parseTaggedText(string $text): FormattedText
    {
        if ($this->highlightStartTag === '' || $this->highlightEndTag === '') {
            return new FormattedText($text);
        }

        $chunks = [];
        foreach (explode($this->highlightStartTag, $text) as $outer) {
            foreach (explode($this->highlightEndTag, $outer, 2) as $inner) {
                $chunks[] = $inner;
            }
        }

        if (\count($chunks) < 3 || \count($chunks) % 2 !== 1) {
            return new FormattedText($text);
        }

        $original = '';
        $spans = [];
        $position = 0;
        foreach ($chunks as $i => $chunk) {
            $chunkLength = mb_strlen($chunk, 'UTF-8');
            $original .= $chunk;

            if ($i % 2 === 1) {
                $spans[] = new MatchSpan($position, $position + $chunkLength, $this->splitChunkIntoTerms($chunk));
            }

            $position += $chunkLength;
        }

        return new FormattedText($original, $spans);
    }

    /**
     * @param MatchSpan[] $matchSpans
     * @param Span[]      $windows
     */
    private function renderWindows(string $text, array $matchSpans, array $windows): FormattedText
    {
        $textLength = mb_strlen($text, 'UTF-8');
        $result = '';
        $outputSpans = [];

        foreach ($windows as $i => $window) {
            $needsLeadingMarker = $i === 0 ? $window->getStartPosition() > 0 : true;
            if ($needsLeadingMarker) {
                $result .= $this->cropMarker;
            }

            $offsetInOutput = ($i === 0 && !$needsLeadingMarker) ? 0 : mb_strlen($result, 'UTF-8');
            $result .= mb_substr($text, $window->getStartPosition(), $window->getLength(), 'UTF-8');

            foreach ($matchSpans as $matchSpan) {
                if ($matchSpan->getStartPosition() < $window->getStartPosition()
                    || $matchSpan->getEndPosition() > $window->getEndPosition()) {
                    continue;
                }
                $delta = $offsetInOutput - $window->getStartPosition();
                $outputSpans[] = new MatchSpan(
                    $matchSpan->getStartPosition() + $delta,
                    $matchSpan->getEndPosition() + $delta,
                    $matchSpan->getTerms(),
                );
            }
        }

        $lastWindow = $windows[\count($windows) - 1] ?? null;
        if ($lastWindow && $lastWindow->getEndPosition() < $textLength) {
            $result .= $this->cropMarker;
        }

        return new FormattedText($result, $outputSpans);
    }

    /**
     * Greedy/exhaustive subset selection: anchor on the highest-scoring window,
     * then pick the best-scoring subset of the rest that fits in the truncation
     * budget. Selected windows returned in document order.
     *
     * @param MatchSpan[] $matchSpans
     * @param Span[]      $windows
     * @return Span[]
     */
    private function selectByPriority(array $matchSpans, array $windows): array
    {
        \assert($this->truncationBudget !== null);

        if (\count($windows) <= 1) {
            return $windows;
        }

        $markerLength = mb_strlen($this->cropMarker, 'UTF-8');

        $scored = [];
        foreach ($windows as $window) {
            $tokensInside = [];
            $totalTokens = 0;
            foreach ($matchSpans as $matchSpan) {
                if ($matchSpan->getStartPosition() < $window->getStartPosition()
                    || $matchSpan->getEndPosition() > $window->getEndPosition()) {
                    continue;
                }
                foreach ($matchSpan->getTerms() as $term) {
                    $tokensInside[$term] = true;
                    $totalTokens++;
                }
            }
            $scored[] = [
                'window' => $window,
                'tokens' => $tokensInside,
                'totalTokens' => $totalTokens,
                'visibleLength' => $window->getLength(),
            ];
        }

        usort($scored, function ($a, $b) {
            return [\count($b['tokens']), $b['totalTokens'], -$b['visibleLength']]
                <=> [\count($a['tokens']), $a['totalTokens'], -$a['visibleLength']];
        });

        $anchor = $scored[0];
        $rest = \array_slice($scored, 1);
        $selected = [$anchor];
        $remainingBudget = $this->truncationBudget - ($anchor['visibleLength'] + $markerLength);

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

    /**
     * Fallback used by the cropHighlightedText() public adapter: when we only have
     * tagged text to work from, derive the matched terms by splitting the highlighted
     * chunk on whitespace.
     *
     * @return array<int, string>
     */
    private function splitChunkIntoTerms(string $chunk): array
    {
        $trimmed = trim($chunk);
        if ($trimmed === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $trimmed) ?: [];
        return array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $parts);
    }
}
