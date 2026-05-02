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
        $options->validate();

        $matches = $matches ?? $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $current = new FormattedText($text, $spans);

        if ($options->shouldCrop()) {
            $current = $this->crop($current, $options);
        }

        if ($options->shouldTruncate()) {
            $current = $this->truncate($current, $options);
        }

        if ($options->shouldHighlight()) {
            $current = $this->highlight($current, $options);
        }

        return new FormatterResult($current->getText(), $matches);
    }

    private function crop(FormattedText $input, FormatterOptions $options): FormattedText
    {
        $cropper = new Cropper(
            $options->getCropLength(),
            $options->getCropMarker(),
            $options->getHighlightStartTag(),
            $options->getHighlightEndTag(),
            $options->shouldPrioritizeMatches(),
            $options->shouldTruncate() ? $options->getTruncationLength() : null,
        );

        return $cropper->transform($input);
    }

    private function highlight(FormattedText $input, FormatterOptions $options): FormattedText
    {
        $highlighter = new Highlighter($options->getHighlightStartTag(), $options->getHighlightEndTag());

        return $highlighter->transform($input);
    }

    private function truncate(FormattedText $input, FormatterOptions $options): FormattedText
    {
        $truncator = new Truncator(
            $options->getTruncationLength(),
            $options->getTruncationMarker(),
            $options->shouldPrioritizeMatches(),
        );

        return $truncator->transform($input);
    }
}
