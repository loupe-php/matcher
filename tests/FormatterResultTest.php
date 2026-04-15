<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\FormatterResult;
use Loupe\Matcher\Matcher;
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
        $matches = (new Tokenizer())->tokenize('foo bar baz');
        $result = new FormatterResult('text', $matches);

        $this->assertEquals([
            [
                'start' => 0,
                'length' => 3,
            ],
            [
                'start' => 4,
                'length' => 3,
            ],
            [
                'start' => 8,
                'length' => 3,
            ],
        ], $result->getMatchesArray());
    }

    public function testNormalizedMatchesArray(): void
    {
        $text = 'Die größten Früchte haben die meiste Süße';
        $query = 'größten Süße';

        $tokenizer = new Tokenizer();
        $matcher = new Matcher($tokenizer);
        $matches = $matcher->calculateMatches($text, $query);

        $result = new FormatterResult($text, $matches);

        $this->assertEquals([
            [
                'start' => 4,
                'length' => 7,
            ],
            [
                'start' => 37,
                'length' => 4,
            ],
        ], $result->getMatchesArray());
    }
}
