<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\Formatter;
use Loupe\Matcher\FormatterOptions;
use Loupe\Matcher\Matcher;
use Loupe\Matcher\Tokenizer\Token;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    private Matcher $matcher;

    private TokenCollection $queryTerms;

    /**
     * @var array<string>
     */
    private array $stopwords;

    protected function setUp(): void
    {
        $tokenizer = new Tokenizer();

        $this->stopwords = ['a', 'of', 'the'];
        $this->matcher = new Matcher($tokenizer, stopWords: $this->stopwords);
        $this->queryTerms = $tokenizer->tokenize('test');
    }

    public static function croppingProvider(): \Generator
    {
        yield 'No cropping' => [
            'wonderful',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken possession of my entire soul.',
        ];

        yield 'Cropping with too much context and no change' => [
            'taken',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings have taken all spring.',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings have taken all spring.',
            true,
        ];

        yield 'Cropping with less context and change' => [
            'taken',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings have taken all spring.',
            '…serenity has taken possession…mornings have taken all spring…',
            true,
            25,
        ];

        yield 'Cropping around single term in center' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings have taken all spring.',
            '…serenity has taken possession of my entire soul, like these sweet mornings have taken…',
            true,
        ];

        yield 'Cropping around repeating term' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul. I am alone, and feel the charm of existence in this spot, which was created for the bliss of a soul like mine.',
            '…serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul. I am alone, and feel the charm of existence…which was created for the bliss of a soul like mine.',
            true,
        ];

        yield 'Cropping around multiple terms' => [
            'soul bliss',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole being. I am alone, and feel the charm of existence in this spot, which was created for the bliss of a heart like mine.',
            '…serenity has taken possession of my entire soul, like these sweet mornings of spring…this spot, which was created for the bliss of a heart like mine.',
            true,
        ];

        yield 'Cropping at start' => [
            'Wonderful',
            'Wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul.',
            'Wonderful serenity has taken possession of…',
            true,
        ];

        yield 'Cropping at end' => [
            'panorama',
            'Wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole panorama.',
            '…spring which I enjoy with my whole panorama.',
            true,
        ];

        yield 'Cropping with custom length' => [
            'whole entire',
            'Wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole panorama.',
            '…my entire soul…with my whole pano…',
            true,
            15,
        ];

        yield 'Cropping with custom marker' => [
            'whole entire',
            'Wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole panorama.',
            ' --- possession of my entire soul, like --- enjoy with my whole panorama.',
            true,
            25,
            ' --- ',
        ];
    }

    public static function highlightingProvider(): \Generator
    {
        yield 'No highlighting' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken possession of my entire soul.',
        ];

        yield 'Highlighting' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul.',
            'A wonderful serenity has taken possession of my entire [soul], like these sweet mornings of spring which I enjoy with my whole [soul].',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting with match at start' => [
            'serenity',
            'Serenity has taken possession of my entire soul.',
            '[Serenity] has taken possession of my entire soul.',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting with match at end' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken possession of my entire [soul].',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting with tags' => [
            'my',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul.',
            'A wonderful serenity has taken possession of <b>my</b> entire soul, like these sweet mornings of spring which I enjoy with <b>my</b> whole soul.',
            true,
            '<b>',
            '</b>',
        ];

        yield 'Highlighting with text case' => [
            'my wonderful soul',
            'Wonderful serenity has taken possession. My entire soul of spring which I enjoy with my whole: Soul.',
            '[Wonderful] serenity has taken possession. [My] entire [soul] of spring which I enjoy with [my] whole: [Soul].',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting with subwords' => [
            'wonder',
            'Wonderful serenity has taken possession of my entire soul, like a sweet morning wonder of spring.',
            'Wonderful serenity has taken possession of my entire soul, like a sweet morning [wonder] of spring.',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting with token variants' => [
            new TokenCollection(
                [
                    (new Token(0, 'my', 0, false, false, false)),
                    (new Token(1, 'wonder', 3, false, false, false))->withVariants(['wonders']),
                    (new Token(2, 'soul', 10, false, false, false))->withVariants(['souls']),
                ],
            ),
            'A wonder of wonders has taken possession of my entire soul, like some souls\' mornings of spring.',
            'A [wonder] of [wonders] has taken possession of [my] entire [soul], like some [souls]\' mornings of spring.',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting with stopwords' => [
            'a meeting of souls',
            'A meeting of souls has taken possession of a collection of souls, like a morning of sweet spring.',
            '[A meeting of souls] has taken possession of a collection [of souls], like a morning of sweet spring.',
            true,
            '[',
            ']',
        ];
    }

    #[DataProvider('croppingProvider')]
    public function testCropping(
        string|TokenCollection $query,
        string $text,
        string $expectedResult,
        bool $enableCrop = false,
        int $cropLength = 50,
        string $cropMarker = '…',
    ): void {
        $options = (new FormatterOptions());
        if ($enableCrop) {
            $options = $options->withEnableCrop()
                ->withCropLength($cropLength)
                ->withCropMarker($cropMarker);
        } else {
            $options = $options->withDisableCrop();
        }

        $query = $query instanceof TokenCollection
            ? $query
            : (new Tokenizer())->tokenize($query, stopWords: $this->stopwords, includeStopWords: true);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);

        $this->assertSame($expectedResult, $result->getFormattedText());
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

    #[DataProvider('highlightingProvider')]
    public function testHighlighting(
        string|TokenCollection $query,
        string $text,
        string $expectedResult,
        bool $enableHighlight = false,
        string $highlightStartTag = '<em>',
        string $highlightEndTag = '</em>',
    ): void {
        $options = (new FormatterOptions());
        if ($enableHighlight) {
            $options = $options->withEnableHighlight()
                ->withHighlightStartTag($highlightStartTag)
                ->withHighlightEndTag($highlightEndTag);
        } else {
            $options = $options->withDisableHighlight();
        }

        $query = $query instanceof TokenCollection
            ? $query
            : (new Tokenizer())->tokenize($query, stopWords: $this->stopwords, includeStopWords: true);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);

        $this->assertSame($expectedResult, $result->getFormattedText());
    }
}
