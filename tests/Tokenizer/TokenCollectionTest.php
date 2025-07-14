<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer;

use Loupe\Matcher\StopWords\InMemoryStopWords;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\TestCase;

class TokenCollectionTest extends TestCase
{
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
