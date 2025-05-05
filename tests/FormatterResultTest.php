<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\FormatterResult;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\TestCase;

final class FormatterResultTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $matches = (new Tokenizer())->tokenize('');
        $result = new FormatterResult('text', $matches);

        $this->assertEquals('text', $result->getFormattedText());
        $this->assertEquals($matches, $result->getMatches());
        $this->assertEquals(false, $result->hasMatches());
    }

    public function testMatches(): void
    {
        $matches = (new Tokenizer())->tokenize('foo bar baz');
        $result = new FormatterResult('text', $matches);

        $this->assertEquals(true, $result->hasMatches());
    }

    public function testMatchesArray(): void
    {
        $matches = (new Tokenizer())->tokenize('foo bar baz', stopWords: ['bar']);
        $result = new FormatterResult('text', $matches);

        $this->assertEquals([
            [
                'start' => 0,
                'length' => 3,
                'stopword' => false,
            ],
            [
                'start' => 8,
                'length' => 3,
                'stopword' => false,
            ],
        ], $result->getMatchesArray());
    }

    public function testStopwordMatchesArray(): void
    {
        $matches = (new Tokenizer())->tokenize('foo bar baz', stopWords: ['bar'], includeStopWords: true);
        $result = new FormatterResult('text', $matches);

        $this->assertEquals([
            [
                'start' => 0,
                'length' => 3,
                'stopword' => false,
            ],
            [
                'start' => 4,
                'length' => 3,
                'stopword' => true,
            ],
            [
                'start' => 8,
                'length' => 3,
                'stopword' => false,
            ],
        ], $result->getMatchesArray());
    }
}
