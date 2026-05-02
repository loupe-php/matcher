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
        if ($this->truncationLength <= 0 || $input->getText() === '') {
            return $input;
        }

        $textLength = mb_strlen($input->getText(), 'UTF-8');
        if ($textLength <= $this->truncationLength) {
            return $input;
        }

        if ($this->prioritizeMatches && $input->getSpans() !== []) {
            $window = (new WindowPlanner())->planTruncationWindow($input->getText(), $input->getSpans(), $this->truncationLength);
            if ($window !== null) {
                return $this->renderWindow($input, $window);
            }
        }

        return $this->headTruncate($input);
    }

    private function headTruncate(FormattedText $input): FormattedText
    {
        $cut = $this->snapEndBackward($input->getText(), $this->truncationLength);
        if ($cut <= 0) {
            $cut = $this->truncationLength;
        }

        $body = mb_substr($input->getText(), 0, $cut, 'UTF-8');
        $spans = [];
        foreach ($input->getSpans() as $matchSpan) {
            if ($matchSpan->getStartPosition() >= $cut) {
                continue;
            }
            $end = min($matchSpan->getEndPosition(), $cut);
            $spans[] = new MatchSpan($matchSpan->getStartPosition(), $end, $matchSpan->getTerms());
        }

        return new FormattedText($body . $this->truncationMarker, $spans);
    }

    private function renderWindow(FormattedText $input, Span $window): FormattedText
    {
        $text = $input->getText();
        $textLength = mb_strlen($text, 'UTF-8');
        $windowStart = $window->getStartPosition();
        $windowEnd = $window->getEndPosition();

        $body = mb_substr($text, $windowStart, $windowEnd - $windowStart, 'UTF-8');

        $leadingMarker = $windowStart > 0 ? $this->truncationMarker : '';
        $trailingMarker = $windowEnd < $textLength ? $this->truncationMarker : '';
        $delta = mb_strlen($leadingMarker, 'UTF-8') - $windowStart;

        $rebasedSpans = [];
        foreach ($input->getSpans() as $matchSpan) {
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
}
