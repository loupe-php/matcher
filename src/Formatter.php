<?php

declare(strict_types=1);

namespace Loupe\Matcher;

use Loupe\Matcher\Formatting\Cropper;
use Loupe\Matcher\Formatting\FormattedText;
use Loupe\Matcher\Formatting\Highlighter;
use Loupe\Matcher\Formatting\Truncator;
use Loupe\Matcher\Tokenizer\TokenCollection;

class Formatter
{
    public function __construct(
        private Matcher $matcher
    ) {
    }

    public function format(string $text, TokenCollection|string $query, FormatterOptions $options, TokenCollection|null $matches = null): FormatterResult
    {
        if ($options->shouldCrop() && $options->shouldTruncate() && $options->getCropLength() > $options->getTruncationLength()) {
            throw new \InvalidArgumentException(\sprintf(
                'crop_length (%d) must not exceed truncation_length (%d) when both are enabled.',
                $options->getCropLength(),
                $options->getTruncationLength(),
            ));
        }

        $matches = $matches ?? $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $current = new FormattedText($text, $spans);

        if ($options->shouldCrop()) {
            $cropper = new Cropper(
                $options->getCropLength(),
                $options->getCropMarker(),
                $options->getHighlightStartTag(),
                $options->getHighlightEndTag(),
                $options->shouldPrioritizeMatches(),
                $options->shouldTruncate() ? $options->getTruncationLength() : null,
            );
            $current = $cropper->transform($current);
        }

        if ($options->shouldTruncate()) {
            $truncator = new Truncator(
                $options->getTruncationLength(),
                $options->getTruncationMarker(),
                $options->shouldPrioritizeMatches(),
            );
            $current = $truncator->transform($current);
        }

        if ($options->shouldHighlight()) {
            $highlighter = new Highlighter($options->getHighlightStartTag(), $options->getHighlightEndTag());
            $current = $highlighter->transform($current);
        }

        return new FormatterResult($current->text, $matches);
    }
}
