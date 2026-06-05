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
        $this->assertEquals(50, $options->getCropLength());
        $this->assertEquals('…', $options->getCropMarker());
        $this->assertEquals(10, $options->getCropMaxFragments());
        $this->assertEquals(250, $options->getTruncationLength());
        $this->assertEquals('…', $options->getTruncationMarker());
        $this->assertEquals('<em>', $options->getHighlightStartTag());
        $this->assertEquals('</em>', $options->getHighlightEndTag());
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
        $this->assertEquals(20, $options->getCropLength());
        $this->assertEquals('...', $options->getCropMarker());
        $this->assertEquals(3, $options->getCropMaxFragments());
        $this->assertEquals('<strong>', $options->getHighlightStartTag());
        $this->assertEquals('</strong>', $options->getHighlightEndTag());
        $this->assertEquals(100, $options->getTruncationLength());
        $this->assertEquals(' ...', $options->getTruncationMarker());
        $this->assertEquals(100, $options->getTruncationLength());
        $this->assertEquals(' ...', $options->getTruncationMarker());
    }

    public function testFromArrayWithDefaults(): void
    {
        $options = FormatterOptions::fromArray([]);

        $this->assertFalse($options->shouldCrop());
        $this->assertFalse($options->shouldHighlight());
        $this->assertFalse($options->shouldTruncate());
        $this->assertEquals(50, $options->getCropLength());
        $this->assertEquals('…', $options->getCropMarker());
        $this->assertEquals(10, $options->getCropMaxFragments());
        $this->assertEquals(250, $options->getTruncationLength());
        $this->assertEquals('…', $options->getTruncationMarker());
        $this->assertEquals('<em>', $options->getHighlightStartTag());
        $this->assertEquals('</em>', $options->getHighlightEndTag());
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

        $this->assertEquals(50, $options->getCropLength());
        $this->assertEquals(100, $newOptions->getCropLength());
    }

    public function testWithCropMarker(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withCropMarker('...');

        $this->assertEquals('…', $options->getCropMarker());
        $this->assertEquals('...', $newOptions->getCropMarker());
    }

    public function testWithCropMaxFragments(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withCropMaxFragments(3);

        $this->assertEquals(10, $options->getCropMaxFragments());
        $this->assertEquals(3, $newOptions->getCropMaxFragments());

        $unlimited = $options->withCropMaxFragments(-1);
        $this->assertEquals(-1, $unlimited->getCropMaxFragments());
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

        $this->assertEquals('</em>', $options->getHighlightEndTag());
        $this->assertEquals('</strong>', $newOptions->getHighlightEndTag());
    }

    public function testWithHighlightStartTag(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withHighlightStartTag('<strong>');

        $this->assertEquals('<em>', $options->getHighlightStartTag());
        $this->assertEquals('<strong>', $newOptions->getHighlightStartTag());
    }

    public function testWithTruncationLength(): void
    {
        $options = new FormatterOptions();
        $newOptions = $options->withTruncationLength(100);

        $this->assertEquals(250, $options->getTruncationLength());
        $this->assertEquals(100, $newOptions->getTruncationLength());
    }
}
