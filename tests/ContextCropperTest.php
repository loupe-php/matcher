<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests;

use Loupe\Matcher\ContextCropper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContextCropperTest extends TestCase
{
    #[DataProvider('trimContextProvider')]
    public function testTrimContext(string $context, int $numberOfContextChars, string $expectedContext, string $preTag = '<em>', string $postTag = '</em>', string $contextEllipsis = '[…]'): void
    {
        $cropper = new ContextCropper($numberOfContextChars, $contextEllipsis, $preTag, $postTag);

        $this->assertSame($expectedContext, $cropper->apply($context));
    }

    public static function trimContextProvider(): \Generator
    {
        yield 'Basic example' => [
            'Lorem ipsum dolor sit amet, <em>consectetur</em> adipiscing elit. Etiam eleifend, augue in dictum lacinia, nisi lacus mollis <em>massa</em>, a pulvinar felis dui nec nisl. Pellentesque justo erat, sollicitudin ac dolor finibus, dapibus lacinia diam.',
            20,
            '[…]ipsum dolor sit amet, <em>consectetur</em> adipiscing elit. Etiam[…]lacinia, nisi lacus mollis <em>massa</em>, a pulvinar felis dui[…]',
        ];

        yield 'Match at the beginning' => [
            '<em>Quisque</em> maximus nec odio sed gravida. Donec ut risus ut urna auctor feugiat.',
            30,
            '<em>Quisque</em> maximus nec odio sed gravida.[…]',
        ];

        yield 'Context overlapping the end of a sentence' => [
            'The quick brown fox jumps over the lazy <em>dog</em>. The <em>fox</em> was very agile.',
            30,
            '[…]brown fox jumps over the lazy <em>dog</em>. The <em>fox</em> was very agile.',
        ];

        yield 'Context overlapping the start of a sentence' => [
            'The quick brown fox jumps over the lazy <em>dog</em>. The <em>fox</em> was very agile and thus this sentence went on forever.',
            40,
            'The quick brown fox jumps over the lazy <em>dog</em>. The <em>fox</em> was very agile and thus this sentence went[…]',
        ];

        yield 'Test with different tags' => [
            'The quick brown fox jumps over the lazy <strong>dog</strong>. The <strong>fox</strong> was very agile.',
            30,
            '[…]brown fox jumps over the lazy <strong>dog</strong>. The <strong>fox</strong> was very agile.',
            '<strong>',
            '</strong>',
        ];

        yield 'Test with multiple word matches tags' => [
            'The quick brown fox jumps <em>over the lazy dog</em>. The <em>fox</em> was very agile and thus this sentence went on forever.',
            30,
            'The quick brown fox jumps <em>over the lazy dog</em>. The <em>fox</em> was very agile and thus this[…]',
        ];

        yield 'Test with non-matching highlight tags just leaves the content untouched' => [
            'The quick brown fox jumps over the lazy <em>dog</em>. The <em>fox</em> was very agile.',
            30,
            'The quick brown fox jumps over the lazy <em>dog</em>. The <em>fox</em> was very agile.',
            '<strong>',
            '</strong>',
        ];

        yield 'Test with different ellipsis' => [
            'The quick brown fox jumps <em>over the lazy dog</em>. The <em>fox</em> was very agile and thus this sentence went on forever.',
            20,
            '~~~quick brown fox jumps <em>over the lazy dog</em>. The <em>fox</em> was very agile and~~~',
            '<em>',
            '</em>',
            '~~~',
        ];
    }
}
