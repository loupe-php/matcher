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
        $this->assertEquals(50, $options->getCropLength());
        $this->assertEquals('…', $options->getCropMarker());
        $this->assertEquals('<em>', $options->getHighlightStartTag());
        $this->assertEquals('</em>', $options->getHighlightEndTag());
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
}
