<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Matcher;
use Loupe\Matcher\Tokenizer\MatchSpan;
use Loupe\Matcher\Tokenizer\Token;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatcherTest extends TestCase
{
    private Matcher $matcher;

    protected function setUp(): void
    {
        $tokenizer = new Tokenizer();
        $stopWords = ['the', 'is', 'at', 'which', 'on'];
        $this->matcher = new Matcher($tokenizer, $stopWords);
    }

    public function testCalculateMatchSpansDoesNotCacheMatchesBetweenCalls(): void
    {
        $text = (new Tokenizer())->tokenize('lorem ipsum lorem');
        $query = 'lorem';
        $matches = new TokenCollection([self::createToken('lorem', 0)]);

        $this->assertSame(
            [[0, 5, ['lorem']]],
            $this->spanTuples($this->matcher->calculateMatchSpans($text, $query, $matches)),
        );

        $matches->add(self::createToken('lorem', 12));

        $this->assertSame(
            [[0, 5, ['lorem']], [12, 17, ['lorem']]],
            $this->spanTuples($this->matcher->calculateMatchSpans($text, $query, $matches)),
        );
    }

    public function testCalculateMatchSpansKeepsRepeatedTermsInOrder(): void
    {
        $text = 'Lorem lorem LOREM ipsum lorem';
        $query = 'lorem';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertSame(
            [[0, 17, ['lorem', 'lorem', 'lorem']], [24, 29, ['lorem']]],
            $this->spanTuples($spans),
        );
    }

    /**
     * @return iterable<string, array{list<Token>, list<Token>, list<array{int, int, list<string>}>}>
     */
    public static function suppliedMatchMembershipProvider(): iterable
    {
        $text = [self::createToken('lorem', 0), self::createToken('lorem', 6), self::createToken('ipsum', 12)];

        yield 'no matches' => [$text, [], []];

        yield 'equal term and position in a different token object' => [
            $text,
            [self::createToken('lorem', 6, id: 99)],
            [[6, 11, ['lorem']]],
        ];

        yield 'same term at a different position' => [
            $text,
            [self::createToken('lorem', 3)],
            [],
        ];

        yield 'different term at the same position' => [
            $text,
            [self::createToken('ipsum', 0)],
            [],
        ];

        yield 'shared variant only' => [
            $text,
            [self::createToken('dolor', 0)->withVariants(['lorem'])],
            [],
        ];

        yield 'reordered and duplicated matches' => [
            $text,
            [
                self::createToken('ipsum', 12),
                self::createToken('lorem', 0),
                self::createToken('ipsum', 12),
                self::createToken('lorem', 0),
            ],
            [[0, 5, ['lorem']], [12, 17, ['ipsum']]],
        ];

        yield 'numeric-looking terms are compared as strings' => [
            [self::createToken('1', 0), self::createToken('x', 2), self::createToken('01', 4), self::createToken('x', 7), self::createToken('1.0', 9)],
            [self::createToken('01', 4)],
            [[4, 6, ['01']]],
        ];

        yield 'term and position do not collide when concatenated' => [
            [self::createToken('a1', 2), self::createToken('x', 5), self::createToken('a', 12)],
            [self::createToken('a', 12)],
            [[12, 13, ['a']]],
        ];
    }

    /**
     * @param list<Token>                         $textTokens
     * @param list<Token>                         $matchTokens
     * @param list<array{int, int, list<string>}> $expected
     */
    #[DataProvider('suppliedMatchMembershipProvider')]
    public function testCalculateMatchSpansWithSuppliedMatches(array $textTokens, array $matchTokens, array $expected): void
    {
        $matcher = new Matcher(new Tokenizer());
        $spans = $matcher->calculateMatchSpans(new TokenCollection($textTokens), new TokenCollection(), new TokenCollection($matchTokens));

        $this->assertSame($expected, $this->spanTuples($spans));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function suppliedMatchOrderProvider(): iterable
    {
        yield 'stop words around and among matches' => ['checking if the quick fox is visible at the fox', 'the quick fox is'];
        yield 'stop words between matches' => ['maybe the fox is quick and brown', 'quick fox'];
        yield 'stop words only' => ['the is at which on', 'is at'];
        yield 'punctuation' => ['The quick, brown fox. Quick? Fox!', 'quick fox'];
        yield 'phrase' => ['a quick fox and the fox is quick', '"quick fox" the'];
        yield 'no matches' => ['The quick brown fox', 'lazy dog'];
    }

    #[DataProvider('suppliedMatchOrderProvider')]
    public function testCalculateMatchSpansIsIndependentOfSuppliedMatchOrder(string $text, string $query): void
    {
        $tokenizer = new Tokenizer();
        $textTokens = $tokenizer->tokenize($text);
        $queryTokens = $tokenizer->tokenize($query);
        $matches = $this->matcher->calculateMatches($textTokens, $queryTokens);
        $reordered = new TokenCollection([...array_reverse($matches->all()), ...$matches->all()]);

        $expected = $this->spanTuples($this->matcher->calculateMatchSpans($text, $query, $matches));

        $this->assertSame($expected, $this->spanTuples($this->matcher->calculateMatchSpans($textTokens, $queryTokens, $reordered)));
    }

    public function testCalculateMatchSpansMatchesNormalizedPositionsAndReturnsOriginalPositions(): void
    {
        $tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString('de'));
        $matcher = new Matcher($tokenizer);
        $text = $tokenizer->tokenize('Straße und Straße');

        // Normalized positions are shifted by the folding of ß to ss
        $this->assertSame([0, 8, 12], array_map(static fn (Token $token) => $token->getStartPosition(), $text->all()));
        $this->assertSame([0, 7, 11], array_map(static fn (Token $token) => $token->getOriginalStartPosition(), $text->all()));

        $byNormalizedPosition = $matcher->calculateMatchSpans($text, 'straße', new TokenCollection([self::createToken('strasse', 12)]));
        $this->assertSame([[11, 17, ['strasse']]], $this->spanTuples($byNormalizedPosition));

        $byOriginalPosition = $matcher->calculateMatchSpans($text, 'straße', new TokenCollection([self::createToken('strasse', 11)]));
        $this->assertSame([], $this->spanTuples($byOriginalPosition));

        $calculated = $matcher->calculateMatchSpans($text, 'straße', $matcher->calculateMatches($text, 'straße'));
        $this->assertSame([[0, 6, ['strasse']], [11, 17, ['strasse']]], $this->spanTuples($calculated));
    }

    public function testCalculateMatchSpansMergesAdjacentTokens(): void
    {
        $text = 'awfully quick brown fox sighted';
        $query = 'quick brown fox';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertCount(1, $spans);
        $this->assertSame(8, $spans[0]->getStartPosition());
        $this->assertSame(23, $spans[0]->getEndPosition());
        $this->assertSame(15, $spans[0]->getLength());
    }

    public function testDecompositionKeepsCorrectPositions(): void
    {
        $text = 'Die Spaßkanone sorgte für großartige Stimmungssteigerung';
        $query = 'kanone steigerung';

        $tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString('de'));
        $matcher = new Matcher($tokenizer);
        $matches = $matcher->calculateMatches($text, $query);
        $spans = $matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertCount(2, $matches->all());
        $this->assertSame([4, 14], [$spans[0]->getStartPosition(), $spans[0]->getEndPosition()]);
        $this->assertSame([37, 56], [$spans[1]->getStartPosition(), $spans[1]->getEndPosition()]);
    }

    public function testEmptyTextReturnsEmptyCollection(): void
    {
        $result = $this->matcher->calculateMatches('', 'query');
        $this->assertCount(0, $result->all());
    }

    public function testNormalizationKeepsCorrectPositions(): void
    {
        $text = 'Das größte Gespräch war für die längste Nacht geplant';
        $query = 'Gespräch Nacht';

        $tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString('de'));
        $matcher = new Matcher($tokenizer);
        $matches = $matcher->calculateMatches($text, $query);
        $spans = $matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertCount(2, $matches->all());
        $this->assertSame([11, 19], [$spans[0]->getStartPosition(), $spans[0]->getEndPosition()]);
        $this->assertSame([40, 45], [$spans[1]->getStartPosition(), $spans[1]->getEndPosition()]);
    }

    public function testSimpleMatch(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $query = 'quick fox dog';
        $matches = $this->matcher->calculateMatches($text, $query);

        $this->assertCount(3, $matches->all());
        $words = array_map(static fn ($t) => $t->getTerm(), $matches->all());

        $this->assertContains('quick', $words);
        $this->assertContains('fox', $words);
        $this->assertContains('dog', $words);
    }

    public function testStopWordsAloneDoNotCreateSpan(): void
    {
        $text = 'the is at which on';
        $query = 'is at';
        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertEmpty($spans);
    }

    public function testStopWordsAmongQueryTermsAreIncluded(): void
    {
        $text = 'another quick fox in sight now';
        $query = 'quick fox in sight';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertCount(1, $spans);
    }

    public function testStopWordsAroundQueryTermsAreIncluded(): void
    {
        $text = 'checking if the quick fox is visible';
        $query = 'the quick fox is';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertCount(1, $spans);
        $this->assertSame(12, $spans[0]->getStartPosition());
        $this->assertSame(28, $spans[0]->getEndPosition());
    }

    public function testStopWordsBetweenQueryTermsAreNotIncluded(): void
    {
        $text = 'maybe the fox is quick and brown';
        $query = 'quick fox';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($text, $query, $matches);

        $this->assertCount(2, $spans);
    }

    public function testStopWordsNotPartOfMatches(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $query = 'the quick fox dog';
        $matches = $this->matcher->calculateMatches($text, $query);

        $this->assertCount(3, $matches->all());
        $words = array_map(static fn ($t) => $t->getTerm(), $matches->all());

        $this->assertNotContains('the', $words);
    }

    private static function createToken(string $term, int $startPosition, int $id = 0): Token
    {
        return new Token($id, $term, $startPosition, false, false);
    }

    /**
     * @param array<MatchSpan> $spans
     *
     * @return list<array{int, int, array<int, string>}>
     */
    private function spanTuples(array $spans): array
    {
        return array_map(
            static fn (MatchSpan $span) => [$span->getStartPosition(), $span->getEndPosition(), $span->getTerms()],
            array_values($spans),
        );
    }
}
