<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer;

use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    public function testMaximumTokens(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('Hallo, mein Name ist Hase und ich weiß von nichts.', 5);

        $this->assertSame(5, $tokens->count());

        $this->assertSame([
            'hallo',
            'mein',
            'name',
            'ist',
            'hase',
        ], $tokenizer->tokenize('Hallo, mein Name ist Hase und ich weiß von nichts.', 5)
            ->allTermsWithVariants());
    }

    public function testNegatedPhrases(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('Hallo, -mein -"Name ist Hase" und -ich "weiß von" -nichts.');

        $this->assertSame([
            'hallo',
            'mein',
            'name',
            'ist',
            'hase',
            'und',
            'ich',
            'weiß',
            'von',
            'nichts',
        ], $tokens->allTermsWithVariants());

        $this->assertSame([
            'mein',
            'name',
            'ist',
            'hase',
            'ich',
            'nichts',
        ], $tokens->allNegatedTermsWithVariants());
    }

    public function testNegatedTokens(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('Hallo, mein -Name ist -Hase und ich weiß von -nichts.');

        $this->assertSame([
            'hallo',
            'mein',
            'name',
            'ist',
            'hase',
            'und',
            'ich',
            'weiß',
            'von',
            'nichts',
        ], $tokens->allTermsWithVariants());

        $this->assertSame([
            'name',
            'hase',
            'nichts',
        ], $tokens->allNegatedTermsWithVariants());
    }

    public function testNegatedWordPartPhraseTokens(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('-Hallo, mein -Name-ist-Hase und -"ich weiß" von 64-bit-Dingen.');

        $this->assertSame([
            'hallo',
            'mein',
            'name',
            'ist',
            'hase',
            'und',
            'ich',
            'weiß',
            'von',
            '64',
            'bit',
            'dingen',
        ], $tokens->allTermsWithVariants());

        $this->assertSame([
            'hallo',
            'name',
            'ist',
            'hase',
            'ich',
            'weiß',
        ], $tokens->allNegatedTermsWithVariants());
    }

    public function testNegatedWordPartTokens(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('Hallo, mein -Name-ist-Hase und -ich weiß von 64-bit-Dingen.');

        $this->assertSame([
            'hallo',
            'mein',
            'name',
            'ist',
            'hase',
            'und',
            'ich',
            'weiß',
            'von',
            '64',
            'bit',
            'dingen',
        ], $tokens->allTermsWithVariants());

        $this->assertSame([
            'name',
            'ist',
            'hase',
            'ich',
        ], $tokens->allNegatedTermsWithVariants());
    }

    public function testTokenizeWithPhrases(): void
    {
        $tokenizer = new Tokenizer();
        $this->assertSame([
            'hallo',
            'mein',
            'name',
            'ist',
            'hase',
            'und',
            'ich',
            'weiß',
            'von',
            'nichts',
        ], $tokenizer->tokenize('Hallo, mein "Name ist Hase" und ich weiß von nichts.')
            ->allTermsWithVariants());
    }
}
