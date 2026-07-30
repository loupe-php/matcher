<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer;

use Loupe\Matcher\StopWords\InMemoryStopWords;
use Loupe\Matcher\Tokenizer\Phrase;
use Loupe\Matcher\Tokenizer\Token;
use Loupe\Matcher\Tokenizer\TokenCollection;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TokenCollectionTest extends TestCase
{
    /**
     * @param array<array<string>> $expectedTerms
     * @param array<bool>          $expectedNegated
     */
    #[TestWith(['"one two"', [['one', 'two']], [false]], 'one phrase')]
    #[TestWith(['"one two" "three four"', [['one', 'two'], ['three', 'four']], [false, false]], 'adjacent phrases')]
    #[TestWith(['"one two" unquoted "three four"', [['one', 'two'], ['unquoted'], ['three', 'four']], [false, false, false]], 'phrases separated by a token')]
    #[TestWith(['"one two"   "three four"', [['one', 'two'], ['three', 'four']], [false, false]], 'phrases separated by multiple spaces')]
    #[TestWith(['"one two", "three four"', [['one', 'two'], ['three', 'four']], [false, false]], 'phrases separated by punctuation')]
    #[TestWith(['-"one two" -"three four"', [['one', 'two'], ['three', 'four']], [true, true]], 'negated phrases')]
    #[TestWith(['unquoted "one two', [['unquoted'], ['one', 'two']], [false, false]], 'unclosed phrase')]
    public function testPhraseGroups(string $query, array $expectedTerms, array $expectedNegated): void
    {
        $groups = (new Tokenizer())->tokenize($query)->phraseGroups();

        $actualTerms = [];
        $actualNegated = [];

        foreach ($groups as $group) {
            $actualTerms[] = array_map(
                static fn (Token $token): string => $token->getTerm(),
                $group->all(),
            );
            $actualNegated[] = $group->isNegated();
        }

        $this->assertSame($expectedTerms, $actualTerms);
        $this->assertSame($expectedNegated, $actualNegated);
        $this->assertInstanceOf(Phrase::class, $groups[array_key_last($groups)]);
    }

    public function testPhraseStartDistinguishesAdjacentPhrases(): void
    {
        $tokens = (new Tokenizer())->tokenize('"one two" "three four"')->all();

        $this->assertTrue($tokens[0]->startsPhrase());
        $this->assertFalse($tokens[1]->startsPhrase());
        $this->assertTrue($tokens[2]->startsPhrase());
        $this->assertFalse($tokens[3]->startsPhrase());
    }

    public function testLegacyPhraseTokensRemainGroupedByBooleanFlag(): void
    {
        $tokens = new TokenCollection([
            new Token(0, 'one', 0, true, false),
            new Token(1, 'two', 4, true, false),
        ]);

        $groups = $tokens->phraseGroups();

        $this->assertCount(1, $groups);
        $this->assertInstanceOf(Phrase::class, $groups[0]);
        $this->assertSame(['one', 'two'], $groups[0]->allTerms());
    }

    public function testWithoutStopWords(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('This is a text.');

        $stopwords = new InMemoryStopWords(['this', 'is']);
        $this->assertSame(['a', 'text'], $tokens->withoutStopWords($stopwords)->allTerms());

        $stopwords = new InMemoryStopWords(['this', 'is', 'a', 'text']);
        $this->assertSame([], $tokens->withoutStopWords($stopwords)->allTerms());
        $this->assertSame(['this', 'is', 'a', 'text'], $tokens->withoutStopWords($stopwords, true)->allTerms());
    }
}
