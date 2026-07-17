<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\FormatterOptions;
use PHPUnit\Framework\TestCase;

final class FormatterOptionsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $options = new FormatterOptions();

        $this->assertFalse($options->shouldCrop());
        $this->assertFalse($options->shouldHighlight());
        $this->assertFalse($options->shouldTruncate());
        $this->assertFalse($options->shouldTruncate());
        $this->assertSame(50, $options->getCropLength());
        $this->assertSame('…', $options->getCropMarker());
        $this->assertSame(10, $options->getCropMaxFragments());
        $this->assertSame(250, $options->getTruncationLength());
        $this->assertSame('…', $options->getTruncationMarker());
        $this->assertSame('<em>', $options->getHighlightStartTag());
        $this->assertSame('</em>', $options->getHighlightEndTag());
    }

    public function testFromArrayWithCustomValues(): void
    {
        $options = FormatterOptions::fromArray([
            'crop_length' => 20,
            'crop_marker' => '...',
            'crop_max_fragments' => 3,
            'enable_crop' => true,
            'enable_highlight' => true,
            'highlight_start_tag' => '<strong>',
            'highlight_end_tag' => '</strong>',
            'enable_truncation' => true,
            'truncation_length' => 100,
            'truncation_marker' => ' ...',
        ]);

        $this->assertTrue($options->shouldCrop());
        $this->assertTrue($options->shouldHighlight());
        $this->assertTrue($options->shouldTruncate());
        $this->assertTrue($options->shouldCrop());
        $this->assertTrue($options->shouldHighlight());
        $this->assertTrue($options->shouldTruncate());
        $this->assertSame(20, $options->getCropLength());
        $this->assertSame('...', $options->getCropMarker());
        $this->assertSame(3, $options->getCropMaxFragments());
        $this->assertSame('<strong>', $options->getHighlightStartTag());
        $this->assertSame('</strong>', $options->getHighlightEndTag());
        $this->assertSame(100, $options->getTruncationLength());
        $this->assertSame(' ...', $options->getTruncationMarker());
        $this->assertSame(100, $options->getTruncationLength());
        $this->assertSame(' ...', $options->getTruncationMarker());
    }

    public function testFromArrayWithDefaults(): void
    {
        $options = FormatterOptions::fromArray([]);

        $this->assertFalse($options->shouldCrop());
        $this->assertFalse($options->shouldHighlight());
        $this->assertFalse($options->shouldTruncate());
        $this->assertSame(50, $options->getCropLength());
        $this->assertSame('…', $options->getCropMarker());
        $this->assertSame(10, $options->getCropMaxFragments());
        $this->assertSame(250, $options->getTruncationLength());
        $this->assertSame('…', $options->getTruncationMarker());
        $this->assertSame('<em>', $options->getHighlightStartTag());
        $this->assertSame('</em>', $options->getHighlightEndTag());
    }

    public function testFromArrayWithDisablingOptions(): void
    {
        $options = FormatterOptions::fromArray([
            'enable_crop' => false,
            'enable_highlight' => false,
            'enable_truncation' => false,
        ]);

        $this->assertFalse($options->shouldCrop());
        $this->assertFalse($options->shouldHighlight());
        $this->assertFalse($options->shouldTruncate());
    }

    public function testWithCropLength(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withCropLength(100);

        $this->assertSame(50, $options->getCropLength());
        $this->assertSame(100, $newOptions->getCropLength());
    }

    public function testWithCropMarker(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withCropMarker('...');

        $this->assertSame('…', $options->getCropMarker());
        $this->assertSame('...', $newOptions->getCropMarker());
    }

    public function testWithCropMaxFragments(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withCropMaxFragments(3);

        $this->assertSame(10, $options->getCropMaxFragments());
        $this->assertSame(3, $newOptions->getCropMaxFragments());

        $unlimited = $options->withCropMaxFragments(-1);
        $this->assertSame(-1, $unlimited->getCropMaxFragments());
    }

    public function testWithDisableCrop(): void
    {
        $options = (new FormatterOptions())->withEnableCrop();
        $newOptions = $options->withDisableCrop();

        $this->assertTrue($options->shouldCrop());
        $this->assertFalse($newOptions->shouldCrop());
    }

    public function testWithDisableHighlight(): void
    {
        $options = (new FormatterOptions())->withEnableHighlight();
        $newOptions = $options->withDisableHighlight();

        $this->assertTrue($options->shouldHighlight());
        $this->assertFalse($newOptions->shouldHighlight());
    }

    public function testWithDisableTruncation(): void
    {
        $options = (new FormatterOptions())->withEnableTruncation();
        $newOptions = $options->withDisableTruncation();

        $this->assertTrue($options->shouldTruncate());
        $this->assertFalse($newOptions->shouldTruncate());
    }

    public function testWithEnableCrop(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withEnableCrop();

        $this->assertFalse($options->shouldCrop());
        $this->assertTrue($newOptions->shouldCrop());
    }

    public function testWithEnableHighlight(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withEnableHighlight();

        $this->assertFalse($options->shouldHighlight());
        $this->assertTrue($newOptions->shouldHighlight());
    }

    public function testWithEnableTruncation(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withEnableTruncation();

        $this->assertFalse($options->shouldTruncate());
        $this->assertTrue($newOptions->shouldTruncate());
    }

    public function testWithHighlightEndTag(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withHighlightEndTag('</strong>');

        $this->assertSame('</em>', $options->getHighlightEndTag());
        $this->assertSame('</strong>', $newOptions->getHighlightEndTag());
    }

    public function testWithHighlightStartTag(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withHighlightStartTag('<strong>');

        $this->assertSame('<em>', $options->getHighlightStartTag());
        $this->assertSame('<strong>', $newOptions->getHighlightStartTag());
    }

    public function testWithTruncationLength(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withTruncationLength(100);

        $this->assertSame(250, $options->getTruncationLength());
        $this->assertSame(100, $newOptions->getTruncationLength());
    }
}
