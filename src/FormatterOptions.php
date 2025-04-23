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

    public function shouldCrop(): bool
    {
        return $this->shouldCrop;
    }

    public function shouldHighlight(): bool
    {
        return $this->shouldHighlight;
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
}
