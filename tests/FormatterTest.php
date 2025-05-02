<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\Formatter;
use Loupe\Matcher\FormatterOptions;
use Loupe\Matcher\Matcher;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    private Matcher $matcher;

    private TokenCollection $queryTerms;

    protected function setUp(): void
    {
        $tokenizer = new Tokenizer();

        $this->matcher = new Matcher($tokenizer);
        $this->queryTerms = $tokenizer->tokenize('test');
    }

    public function testFormatWithCrop(): void
    {
        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withCropLength(10)
            ->withCropMarker('...')
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string used for cropping.', $this->queryTerms, $options);

        $this->assertSame('...a test string...', $result->getFormattedText());
    }

    public function testFormatWithHighlight(): void
    {
        $options = (new FormatterOptions())
            ->withEnableHighlight()
            ->withHighlightStartTag('<b>')
            ->withHighlightEndTag('</b>')
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string.', $this->queryTerms, $options);

        $this->assertSame('This is a <b>test</b> string.', $result->getFormattedText());
    }

    public function testFormatWithHighlightAndCrop(): void
    {
        $options = (new FormatterOptions())
            ->withEnableHighlight()
            ->withEnableCrop()
            ->withHighlightStartTag('[')
            ->withHighlightEndTag(']')
            ->withCropLength(15)
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string and we use it to test the cropping and highlighting features combined.', $this->queryTerms, $options);

        $this->assertSame('…his is a [test] string and…use it to [test] the cropping…', $result->getFormattedText());

    }

    public function testFormatWithoutHighlightOrCrop(): void
    {
        $options = new FormatterOptions();

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string.', $this->queryTerms, $options);

        $this->assertSame('This is a test string.', $result->getFormattedText());
    }
}
