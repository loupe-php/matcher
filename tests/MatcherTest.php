<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\Matcher;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
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

    public function testCalculateMatchSpansMergesAdjacentTokens(): void
    {
        $text = 'quick brown fox';
        $query = 'quick brown fox';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($matches);

        $this->assertCount(1, $spans);
        $this->assertSame(0, $spans[0]->getStartPosition());
        $this->assertSame(15, $spans[0]->getEndPosition());
        $this->assertSame(15, $spans[0]->getLength());
    }

    public function testEmptyTextReturnsEmptyCollection(): void
    {
        $result = $this->matcher->calculateMatches('', 'query');
        $this->assertInstanceOf(TokenCollection::class, $result);
        $this->assertCount(0, $result->all());
    }

    public function testSimpleMatch(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $query = 'quick fox dog';
        $matches = $this->matcher->calculateMatches($text, $query);

        $this->assertGreaterThan(0, \count($matches->all()));
        $words = array_map(fn ($t) => $t->getTerm(), $matches->all());

        $this->assertContains('quick', $words);
        $this->assertContains('fox', $words);
        $this->assertContains('dog', $words);
    }

    public function testStopWordsAloneDoNotCreateSpan(): void
    {
        $text = 'the is at which on';
        $query = 'the is at';
        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($matches);

        $this->assertEmpty($spans);
    }

    public function testStopWordsBetweenQueryTermsAreNotIncluded(): void
    {
        $text = 'quick the fox';
        $query = 'quick fox';

        $matches = $this->matcher->calculateMatches($text, $query);
        $spans = $this->matcher->calculateMatchSpans($matches);

        $this->assertCount(2, $spans);
    }
}
