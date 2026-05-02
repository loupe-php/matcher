<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Span;

class Truncator implements Transformer
{
    private const WORD_BOUNDARIES = [' ', "\t", "\n", "\r"];

    public function __construct(
        private int $truncationLength,
        private string $truncationMarker,
        private bool $prioritizeMatches = false,
    ) {
    }

    public function transform(FormattedText $input): FormattedText
    {
        if ($this->truncationLength <= 0 || $input->text === '') {
            return $input;
        }

        $textLength = mb_strlen($input->text, 'UTF-8');
        if ($textLength <= $this->truncationLength) {
            return $input;
        }

        if ($this->prioritizeMatches && $input->spans !== []) {
            return $this->smartTruncate($input);
        }

        return $this->headTruncate($input);
    }

    private function headTruncate(FormattedText $input): FormattedText
    {
        $cut = $this->snapEndBackward($input->text, $this->truncationLength);
        if ($cut <= 0) {
            $cut = $this->truncationLength;
        }

        $body = mb_substr($input->text, 0, $cut, 'UTF-8');
        $spans = [];
        foreach ($input->spans as $matchSpan) {
            if ($matchSpan->getStartPosition() >= $cut) {
                continue;
            }
            $end = min($matchSpan->getEndPosition(), $cut);
            $spans[] = new MatchSpan($matchSpan->getStartPosition(), $end, $matchSpan->getTerms());
        }

        return new FormattedText($body . $this->truncationMarker, $spans);
    }

    private function smartTruncate(FormattedText $input): FormattedText
    {
        $text = $input->text;
        $matchSpans = $input->spans;
        $textLength = mb_strlen($text, 'UTF-8');
        $scorer = new Scorer();

        $candidateStarts = [];
        foreach ($matchSpans as $matchSpan) {
            $spanStart = $matchSpan->getStartPosition();
            $spanLength = $matchSpan->getLength();

            $candidateStarts[] = $spanStart;
            $candidateStarts[] = $spanStart - (int) floor(($this->truncationLength - $spanLength) / 2);
        }

        $bestScore = null;
        $bestStart = 0;
        foreach ($candidateStarts as $rawStart) {
            $start = max(0, min($rawStart, $textLength - $this->truncationLength));
            $end = $start + $this->truncationLength;
            $score = $scorer->scoreSnippet(new Span($start, $end), $matchSpans);
            if ($bestScore === null || $score > $bestScore || ($score === $bestScore && $start < $bestStart)) {
                $bestScore = $score;
                $bestStart = $start;
            }
        }

        $windowStart = $bestStart;
        $windowEnd = min($textLength, $windowStart + $this->truncationLength);

        if ($windowStart > 0) {
            $windowStart = $this->snapStartForward($text, $windowStart);
        }
        if ($windowEnd < $textLength) {
            $windowEnd = $this->snapEndBackward($text, $windowEnd);
        }

        if ($windowStart >= $windowEnd) {
            return $this->headTruncate($input);
        }

        $body = mb_substr($text, $windowStart, $windowEnd - $windowStart, 'UTF-8');

        $leadingMarker = $windowStart > 0 ? $this->truncationMarker : '';
        $trailingMarker = $windowEnd < $textLength ? $this->truncationMarker : '';
        $delta = mb_strlen($leadingMarker, 'UTF-8') - $windowStart;

        $rebasedSpans = [];
        foreach ($matchSpans as $matchSpan) {
            if ($matchSpan->getEndPosition() <= $windowStart || $matchSpan->getStartPosition() >= $windowEnd) {
                continue;
            }
            $clippedStart = max($matchSpan->getStartPosition(), $windowStart);
            $clippedEnd = min($matchSpan->getEndPosition(), $windowEnd);
            $rebasedSpans[] = new MatchSpan($clippedStart + $delta, $clippedEnd + $delta, $matchSpan->getTerms());
        }

        return new FormattedText($leadingMarker . $body . $trailingMarker, $rebasedSpans);
    }

    private function snapEndBackward(string $text, int $position): int
    {
        while ($position > 0) {
            $char = mb_substr($text, $position, 1, 'UTF-8');
            if (\in_array($char, self::WORD_BOUNDARIES, true)) {
                return $position;
            }
            $position--;
        }
        return $position;
    }

    private function snapStartForward(string $text, int $position): int
    {
        $length = mb_strlen($text, 'UTF-8');
        while ($position < $length) {
            $char = mb_substr($text, $position, 1, 'UTF-8');
            if (\in_array($char, self::WORD_BOUNDARIES, true)) {
                return $position + 1;
            }
            $position++;
        }
        return $position;
    }
}
