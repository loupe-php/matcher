<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Benchmark;

use Loupe\Matcher\Formatter;
use Loupe\Matcher\FormatterOptions;
use Loupe\Matcher\Matcher;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * Span calculation across text sizes and match densities.
 *
 * Dense texts consist only of matching words, so the number of matches grows with the text.
 * Sparse texts contain one match every 50 words, and texts without matches measure the
 * fixed cost of span calculation when nothing needs highlighting.
 */
#[Revs(5)]
#[Iterations(5)]
#[Warmup(1)]
#[OutputTimeUnit('milliseconds', precision: 3)]
class MatchSpanBench
{
    private const FILLER = ['ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod'];

    private Formatter $formatter;

    private FormatterOptions $highlightOptions;

    private Matcher $matcher;

    private TokenCollection $matches;

    private string $query;

    private TokenCollection $queryTokens;

    private string $text;

    private TokenCollection $textTokens;

    /**
     * @param array{density: string, size: int} $params
     */
    public function setUp(array $params): void
    {
        $tokenizer = new Tokenizer();

        $this->matcher = new Matcher($tokenizer);
        $this->formatter = new Formatter($this->matcher);
        $this->highlightOptions = (new FormatterOptions())->withEnableHighlight();
        $this->query = 'lorem';
        $this->text = self::buildText($params['density'], $params['size']);

        $this->textTokens = $tokenizer->tokenize($this->text);
        $this->queryTokens = $tokenizer->tokenize($this->query);
        $this->matches = $this->matcher->calculateMatches($this->textTokens, $this->queryTokens);
    }

    /**
     * Span calculation with pre-tokenized inputs and pre-computed matches — span logic only.
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideDensityAndSize')]
    public function benchCalculateMatchSpansPreTokenized(): void
    {
        $this->matcher->calculateMatchSpans($this->textTokens, $this->queryTokens, $this->matches);
    }

    /**
     * Highlighting with supplied matches — includes tokenization and highlighting.
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideDensityAndSize')]
    public function benchFormatHighlightWithSuppliedMatches(): void
    {
        $this->formatter->format($this->text, $this->query, $this->highlightOptions, $this->matches);
    }

    public function provideDensityAndSize(): \Generator
    {
        yield 'short' => ['density' => 'dense', 'size' => 10];

        foreach ([1000, 2000, 4000, 8000] as $size) {
            yield "dense {$size}" => ['density' => 'dense', 'size' => $size];
        }

        foreach ([1000, 4000] as $size) {
            yield "sparse {$size}" => ['density' => 'sparse', 'size' => $size];
            yield "none {$size}" => ['density' => 'none', 'size' => $size];
        }
    }

    private static function buildText(string $density, int $size): string
    {
        $words = [];

        for ($i = 0; $i < $size; ++$i) {
            $words[] = match ($density) {
                'dense' => 'lorem',
                'sparse' => 0 === $i % 50 ? 'lorem' : self::FILLER[$i % \count(self::FILLER)],
                default => self::FILLER[$i % \count(self::FILLER)],
            };
        }

        return implode(' ', $words);
    }
}
