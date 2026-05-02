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
    private array $stopWords;

    protected function setUp(): void
    {
        $tokenizer = new Tokenizer();

        $this->stopWords = ['a', 'of', 'the'];
        $this->matcher = new Matcher($tokenizer, stopWords: $this->stopWords);
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

        yield 'Highlighting single word' => [
            'juice',
            'Juice',
            '[Juice]',
            true,
            '[',
            ']',
        ];

        yield 'Highlighting single word with whitespace' => [
            'juice',
            ' Juice ',
            ' [Juice] ',
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
                    (new Token(0, 'my', 0, false, false)),
                    (new Token(1, 'wonder', 3, false, false))->withVariants(['wonders']),
                    (new Token(2, 'soul', 10, false, false))->withVariants(['souls']),
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

        $query = $query instanceof TokenCollection ? $query : (new Tokenizer())->tokenize($query);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);

        $this->assertSame($expectedResult, $result->getFormattedText());
    }

    public function testFormatDoesNotThrowOnDefaultLengthsWithMatchPrioritization(): void
    {
        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withEnableTruncation()
            ->withEnableMatchPrioritization();

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('A test sentence.', $this->queryTerms, $options);

        $this->assertSame('A test sentence.', $result->getFormattedText());
    }

    public function testFormatThrowsWhenCropLengthExceedsTruncationLength(): void
    {
        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withEnableTruncation()
            ->withCropLength(300)
            ->withTruncationLength(100);

        $formatter = new Formatter($this->matcher);

        $this->expectException(\InvalidArgumentException::class);
        $formatter->format('Some text with a test in it.', $this->queryTerms, $options);
    }

    public function testFormatTruncationClosesOpenHighlight(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('a meeting of souls');

        $options = (new FormatterOptions())
            ->withEnableHighlight()
            ->withEnableTruncation()
            ->withHighlightStartTag('[')
            ->withHighlightEndTag(']')
            ->withTruncationLength(10)
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('A meeting of souls has taken possession.', $query, $options);

        $this->assertSame('[A meeting]…', $result->getFormattedText());
    }

    public function testFormatTruncationDoesNotCutWords(): void
    {
        $options = (new FormatterOptions())
            ->withEnableHighlight()
            ->withEnableTruncation()
            ->withHighlightStartTag('[')
            ->withHighlightEndTag(']')
            ->withTruncationLength(12)
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('this is a test of patience to find a suitable example', $this->queryTerms, $options);

        $this->assertSame('this is a…', $result->getFormattedText());
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

    public function testFormatWithCropAndTruncation(): void
    {
        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withEnableTruncation()
            ->withCropLength(15)
            ->withTruncationLength(35)
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string and we use it to test the cropping and highlighting features combined.', $this->queryTerms, $options);

        $this->assertSame('…is a test string…it to test the…', $result->getFormattedText());
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

    public function testFormatWithHighlightAndTruncation(): void
    {
        $options = (new FormatterOptions())
            ->withEnableHighlight()
            ->withEnableTruncation()
            ->withHighlightStartTag('[')
            ->withHighlightEndTag(']')
            ->withTruncationLength(20)
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string used for truncation testing.', $this->queryTerms, $options);

        $this->assertSame('This is a [test]…', $result->getFormattedText());
    }

    public function testFormatWithMatchPrioritizationCropEmitsSelectedWindowsInDocumentOrder(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('test');

        // Three clusters: dense at start, sparse middle, medium at end
        $text = 'Cluster A has test test test together. Filler text fills the gap between clusters here nicely. Single sparse test alone here. More filler fills the next gap between segments. End test and test paired close.';

        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withCropLength(30)
            ->withTruncationLength(100);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);
        $output = $result->getFormattedText();

        // Top two clusters by token count: A (3 matches) and end (2 matches), sparse middle dropped
        $this->assertStringContainsString('test test test', $output);
        $this->assertStringContainsString('test and test', $output);
        $this->assertStringNotContainsString('Single sparse test alone', $output);

        // Document order: cluster A appears before end cluster
        $clusterAPosition = mb_strpos($output, 'test test test');
        $endPosition = mb_strpos($output, 'test and test');
        $this->assertNotFalse($clusterAPosition);
        $this->assertNotFalse($endPosition);
        $this->assertLessThan($endPosition, $clusterAPosition);
    }

    public function testFormatWithMatchPrioritizationCropPicksDenserWindow(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('test');

        $text = 'A test alone over here. Plenty of unrelated content fills the middle gap nicely. Then test and test together at the end of this sentence.';

        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withCropLength(30)
            ->withTruncationLength(40);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);
        $output = $result->getFormattedText();

        $this->assertStringContainsString('test and test', $output);
        $this->assertStringNotContainsString('alone over here', $output);
    }

    public function testFormatWithMatchPrioritizationCropPrefersDistinctTerms(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('alpha beta gamma');

        // Cluster X: alpha alpha alpha (3 matches, 1 distinct term)
        // Cluster Y: alpha beta gamma (3 matches, 3 distinct terms)
        $text = 'Cluster X has alpha alpha alpha repeated thrice. Plenty of unrelated middle content fills the gap. Cluster Y has alpha beta gamma in it.';

        $options = (new FormatterOptions())
            ->withEnableCrop()
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withCropLength(30)
            ->withTruncationLength(40);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);
        $output = $result->getFormattedText();

        $this->assertStringContainsString('alpha beta gamma', $output);
        $this->assertStringNotContainsString('alpha alpha alpha', $output);
    }

    public function testFormatWithMatchPrioritizationTruncationCentersOnDensestCluster(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('test');

        $text = 'Sparse leading test alone here. Plenty of unrelated middle content fills the gap. Dense cluster test and test and test together. Some trailing filler to extend the text further.';

        $options = (new FormatterOptions())
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withTruncationLength(50);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);
        $output = $result->getFormattedText();

        $this->assertStringStartsWith('…', $output);
        $this->assertStringEndsWith('…', $output);
        $this->assertStringContainsString('test and test and test', $output);
    }

    public function testFormatWithMatchPrioritizationTruncationFallsBackToHeadTruncationWithoutMatches(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('elephant');

        $text = 'A wonderful serenity has taken possession of my entire soul today.';

        $options = (new FormatterOptions())
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withTruncationLength(30);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);

        $this->assertSame('A wonderful serenity has taken…', $result->getFormattedText());
    }

    public function testFormatWithMatchPrioritizationTruncationKeepsHighlightTagsWhenHighlightDisabled(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('test');

        $text = 'Sparse leading content here. Plenty of unrelated middle content fills the gap. Dense cluster test and test and test together. Some trailing filler.';

        $options = (new FormatterOptions())
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withTruncationLength(50);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);
        $output = $result->getFormattedText();

        // Smart truncation must locate matches even when highlight is off — Unhighlighter strips tags
        $this->assertStringNotContainsString('<em>', $output);
        $this->assertStringNotContainsString('</em>', $output);
        $this->assertStringContainsString('test', $output);
    }

    public function testFormatWithMatchPrioritizationTruncationPreservesHighlightsWhenEnabled(): void
    {
        $tokenizer = new Tokenizer();
        $query = $tokenizer->tokenize('test');

        $text = 'Sparse leading content here. Plenty of unrelated middle content fills the gap. Dense cluster test and test and test together. Some trailing filler.';

        $options = (new FormatterOptions())
            ->withEnableHighlight()
            ->withEnableTruncation()
            ->withEnableMatchPrioritization()
            ->withHighlightStartTag('[')
            ->withHighlightEndTag(']')
            ->withTruncationLength(50);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);
        $output = $result->getFormattedText();

        $this->assertStringContainsString('[test] and [test] and [test]', $output);
    }

    public function testFormatWithoutHighlightOrCrop(): void
    {
        $options = new FormatterOptions();

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string.', $this->queryTerms, $options);

        $this->assertSame('This is a test string.', $result->getFormattedText());
    }

    public function testFormatWithTruncation(): void
    {
        $options = (new FormatterOptions())
            ->withEnableTruncation()
            ->withTruncationLength(20)
            ->withTruncationMarker('...')
        ;

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format('This is a test string used for truncation testing.', $this->queryTerms, $options);

        $this->assertSame('This is a test...', $result->getFormattedText());
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

        $query = $query instanceof TokenCollection ? $query : (new Tokenizer())->tokenize($query);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);

        $this->assertSame($expectedResult, $result->getFormattedText());
    }

    #[DataProvider('truncationProvider')]
    public function testTruncation(
        string|TokenCollection $query,
        string $text,
        string $expectedResult,
        bool $enableTruncation = false,
        int $truncationLength = 250,
        string $truncationMarker = '…',
    ): void {
        $options = (new FormatterOptions());
        if ($enableTruncation) {
            $options = $options->withEnableTruncation()
                ->withTruncationLength($truncationLength)
                ->withTruncationMarker($truncationMarker);
        } else {
            $options = $options->withDisableTruncation();
        }

        $query = $query instanceof TokenCollection ? $query : (new Tokenizer())->tokenize($query);

        $formatter = new Formatter($this->matcher);
        $result = $formatter->format($text, $query, $options);

        $this->assertSame($expectedResult, $result->getFormattedText());
    }

    public static function truncationProvider(): \Generator
    {
        yield 'No truncation' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken possession of my entire soul.',
        ];

        yield 'Text shorter than limit is unchanged' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken possession of my entire soul.',
            true,
            250,
        ];

        yield 'Truncates at word boundary with marker' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul.',
            'A wonderful serenity has taken possession of my entire soul, like these sweet…',
            true,
            80,
        ];

        yield 'Truncates with custom marker' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken [...]',
            true,
            32,
            ' [...]',
        ];

        yield 'Truncation length zero is a no-op' => [
            'soul',
            'A wonderful serenity has taken possession of my entire soul.',
            'A wonderful serenity has taken possession of my entire soul.',
            true,
            0,
        ];

        yield 'Truncates multibyte text safely' => [
            'café',
            'A café in Zürich is où you naïvely meet for piña coladas and crème brûlée.',
            'A café in Zürich is où you naïvely meet for piña…',
            true,
            50,
        ];
    }
}
