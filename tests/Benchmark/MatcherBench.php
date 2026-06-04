<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Benchmark;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Matcher;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Revs(20)]
#[Iterations(10)]
#[Warmup(2)]
#[OutputTimeUnit('microseconds', precision: 2)]
class MatcherBench
{
    private Matcher $matcher;

    private Matcher $matcherWithStopWords;

    private TokenCollection $matches;

    private TokenCollection $matchesWithStopWords;

    private string $query;

    private TokenCollection $queryTokens;

    private string $text;

    private TokenCollection $textTokens;

    private Tokenizer $tokenizer;

    /**
     * Base setup: creates both matchers and loads raw text/query strings.
     *
     * @param array{locale: string} $params
     */
    public function setUp(array $params): void
    {
        $locale = $params['locale'];
        $this->tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString($locale));
        $stopWords = self::loadStopWords($locale);

        $this->matcher = new Matcher($this->tokenizer);
        $this->matcherWithStopWords = new Matcher($this->tokenizer, $stopWords);
        $this->text = self::loadFixture($locale, 10000);
        $this->query = $locale === 'en' ? 'rabbit alice sister' : 'Gregor Zimmer Bett';
    }

    /**
     * End-to-end match calculation from raw strings — includes tokenization of both inputs.
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatches(): void
    {
        $this->matcher->calculateMatches($this->text, $this->query);
    }

    /**
     * Match calculation with pre-tokenized inputs — isolates matching logic from tokenization overhead.
     */
    #[BeforeMethods(['setUp', 'setUpTokenized'])]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatchesPreTokenized(): void
    {
        $this->matcher->calculateMatches($this->textTokens, $this->queryTokens);
    }

    /**
     * End-to-end match calculation with stop words supplied — measures stop-word filtering overhead.
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatchesWithStopWords(): void
    {
        $this->matcherWithStopWords->calculateMatches($this->text, $this->query);
    }

    /**
     * Full pipeline from raw strings: tokenize → match → merge spans.
     * Text is tokenized twice (once by calculateMatches, once by calculateMatchSpans).
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatchSpans(): void
    {
        $matches = $this->matcher->calculateMatches($this->text, $this->query);
        $this->matcher->calculateMatchSpans($this->text, $this->query, $matches);
    }

    /**
     * Span merging with pre-tokenized inputs and pre-computed matches — span logic only.
     */
    #[BeforeMethods(['setUp', 'setUpTokenized', 'setUpMatches'])]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatchSpansPreTokenized(): void
    {
        $this->matcher->calculateMatchSpans($this->textTokens, $this->queryTokens, $this->matches);
    }

    /**
     * Span merging with pre-tokenized inputs and stop words — isolates span logic with stop-word overhead.
     */
    #[BeforeMethods(['setUp', 'setUpTokenized', 'setUpMatches'])]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatchSpansPreTokenizedWithStopWords(): void
    {
        $this->matcherWithStopWords->calculateMatchSpans($this->textTokens, $this->queryTokens, $this->matchesWithStopWords);
    }

    /**
     * Full pipeline with stop words supplied — tokenize → match → merge spans, with stop-word filtering.
     */
    #[BeforeMethods('setUp')]
    #[ParamProviders('provideCorpus')]
    public function benchCalculateMatchSpansWithStopWords(): void
    {
        $matches = $this->matcherWithStopWords->calculateMatches($this->text, $this->query);
        $this->matcherWithStopWords->calculateMatchSpans($this->text, $this->query, $matches);
    }

    public function provideCorpus(): \Generator
    {
        foreach (['en', 'de'] as $locale) {
            yield $locale => [
                'locale' => $locale,
            ];
        }
    }

    /**
     * Pre-computes matches — only needed by pre-tokenized span benchmarks.
     */
    public function setUpMatches(): void
    {
        $this->matches = $this->matcher->calculateMatches($this->textTokens, $this->queryTokens);
        $this->matchesWithStopWords = $this->matcherWithStopWords->calculateMatches($this->textTokens, $this->queryTokens);
    }

    /**
     * Tokenizes text and query — only needed by pre-tokenized benchmarks.
     */
    public function setUpTokenized(): void
    {
        $this->textTokens = $this->tokenizer->tokenize($this->text);
        $this->queryTokens = $this->tokenizer->tokenize($this->query);
    }

    private static function loadFixture(string $locale, int $size): string
    {
        $raw = file_get_contents(__DIR__ . "/fixtures/text/{$locale}.txt");
        if ($raw === false) {
            throw new \RuntimeException("Missing fixture for locale {$locale}");
        }

        // Repeat until long enough, then trim to the exact byte length.
        $len = \strlen($raw);
        if ($len < $size) {
            $raw = str_repeat($raw, intdiv($size, $len) + 1);
        }

        return substr($raw, 0, $size);
    }

    /**
     * @return array<string>
     */
    private static function loadStopWords(string $locale): array
    {
        $raw = file_get_contents(__DIR__ . "/fixtures/stopwords/{$locale}.txt");
        if ($raw === false) {
            throw new \RuntimeException("Missing stop words fixture for locale {$locale}");
        }

        return array_filter(explode("\n", trim($raw)));
    }
}
