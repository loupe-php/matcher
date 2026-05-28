<?php

declare(strict_types=1);

namespace Loupe\Matcher;

class FormatterOptions
{
    private int $cropLength = 50;

    private string $cropMarker = '…';

    private string $highlightEndTag = '</em>';

    private string $highlightStartTag = '<em>';

    private bool $shouldCrop = false;

    private bool $shouldHighlight = false;

    private bool $shouldTruncate = false;

    private int $truncationLength = 250;

    private string $truncationMarker = '…';

    /**
     * @param array{
     *     crop_length?: int,
     *     crop_marker?: string,
     *     enable_crop?: bool,
     *     enable_highlight?: bool,
     *     enable_truncation?: bool,
     *     truncation_length?: int,
     *     truncation_marker?: string,
     *     highlight_start_tag?: string,
     *     highlight_end_tag?: string
     * } $options
     */
    public static function fromArray(array $options): self
    {
        $formatterOptions = new self();

        if (isset($options['crop_length'])) {
            $formatterOptions = $formatterOptions->withCropLength((int) $options['crop_length']);
        }

        if (isset($options['crop_marker'])) {
            $formatterOptions = $formatterOptions->withCropMarker($options['crop_marker']);
        }

        if (\array_key_exists('enable_crop', $options)) {
            $formatterOptions = $options['enable_crop']
                ? $formatterOptions->withEnableCrop()
                : $formatterOptions->withDisableCrop();
        }

        if (\array_key_exists('enable_highlight', $options)) {
            $formatterOptions = $options['enable_highlight']
                ? $formatterOptions->withEnableHighlight()
                : $formatterOptions->withDisableHighlight();
        }

        if (isset($options['highlight_start_tag'])) {
            $formatterOptions = $formatterOptions->withHighlightStartTag($options['highlight_start_tag']);
        }

        if (isset($options['highlight_end_tag'])) {
            $formatterOptions = $formatterOptions->withHighlightEndTag($options['highlight_end_tag']);
        }

        if (\array_key_exists('enable_truncation', $options)) {
            $formatterOptions = $options['enable_truncation']
                ? $formatterOptions->withEnableTruncation()
                : $formatterOptions->withDisableTruncation();
        }

        if (isset($options['truncation_length'])) {
            $formatterOptions = $formatterOptions->withTruncationLength((int) $options['truncation_length']);
        }

        if (isset($options['truncation_marker'])) {
            $formatterOptions = $formatterOptions->withTruncationMarker($options['truncation_marker']);
        }

        return $formatterOptions;
    }

    public function getCropLength(): int
    {
        return $this->cropLength;
    }

    public function getCropMarker(): string
    {
        return $this->cropMarker;
    }

    public function getHighlightEndTag(): string
    {
        return $this->highlightEndTag;
    }

    public function getHighlightStartTag(): string
    {
        return $this->highlightStartTag;
    }

    public function getTruncationLength(): int
    {
        return $this->truncationLength;
    }

    public function getTruncationMarker(): string
    {
        return $this->truncationMarker;
    }

    public function shouldCrop(): bool
    {
        return $this->shouldCrop;
    }

    public function shouldHighlight(): bool
    {
        return $this->shouldHighlight;
    }

    public function shouldTruncate(): bool
    {
        return $this->shouldTruncate;
    }

    public function withCropLength(int $cropLength): self
    {
        $clone = clone $this;
        $clone->cropLength = $cropLength;
        return $clone;

    }

    public function withCropMarker(string $marker): self
    {
        $clone = clone $this;
        $clone->cropMarker = $marker;
        return $clone;
    }

    public function withDisableCrop(): self
    {
        $clone = clone $this;
        $clone->shouldCrop = false;

        return $clone;
    }

    public function withDisableHighlight(): self
    {
        $clone = clone $this;
        $clone->shouldHighlight = false;
        return $clone;
    }

    public function withDisableTruncation(): self
    {
        $clone = clone $this;
        $clone->shouldTruncate = false;

        return $clone;
    }

    public function withEnableCrop(): self
    {
        $clone = clone $this;
        $clone->shouldCrop = true;

        return $clone;
    }

    public function withEnableHighlight(): self
    {
        $clone = clone $this;
        $clone->shouldHighlight = true;
        return $clone;
    }

    public function withEnableTruncation(): self
    {
        $clone = clone $this;
        $clone->shouldTruncate = true;

        return $clone;
    }

    public function withHighlightEndTag(string $endTag): self
    {
        $clone = clone $this;
        $clone->highlightEndTag = $endTag;
        return $clone;
    }

    public function withHighlightStartTag(string $startTag): self
    {
        $clone = clone $this;
        $clone->highlightStartTag = $startTag;
        return $clone;
    }

    public function withTruncationLength(int $truncationLength): self
    {
        $clone = clone $this;
        $clone->truncationLength = $truncationLength;
        return $clone;
    }

    public function withTruncationMarker(string $truncationMarker): self
    {
        $clone = clone $this;
        $clone->truncationMarker = $truncationMarker;
        return $clone;
    }
}
