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
        return $highlighter->transform($cropped)->getText();
    }

    public function transform(FormattedText $input): FormattedText
    {
        if ($this->cropLength <= 0 || $input->getText() === '' || $input->getSpans() === []) {
            return $input;
        }

        $tagOverhead = mb_strlen($this->highlightStartTag, 'UTF-8') + mb_strlen($this->highlightEndTag, 'UTF-8');
        $totalBudget = ($this->prioritizeMatches && $this->truncationBudget !== null) ? $this->truncationBudget : null;
        $markerLength = mb_strlen($this->cropMarker, 'UTF-8');

        $windows = (new WindowPlanner())->planCropWindows(
            $input->getText(),
            $input->getSpans(),
            $this->cropLength,
            $tagOverhead,
            $totalBudget,
            $markerLength,
        );

        if ($windows === []) {
            return $input;
        }

        return $this->renderWindows($input->getText(), $input->getSpans(), $windows);
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
        $terms = array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $parts);

        return $terms;
    }
}
