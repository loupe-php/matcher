<?php

declare(strict_types=1);

namespace Formatting;

use Loupe\Matcher\Formatting\Cropper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CropperTest extends TestCase
{
    public static function cropHighlightedTextProvider(): \Generator
    {
        yield 'Cropping with too much context and no change' => [
            'A wonderful serenity has <em>taken</em> possession of my entire soul, like these sweet mornings have <em>taken</em> all spring.',
            'A wonderful serenity has <em>taken</em> possession of my entire soul, like these sweet mornings have <em>taken</em> all spring.',
        ];

        yield 'Cropping with less context and change' => [
            'A wonderful serenity has <em>taken</em> possession of my entire soul, like these sweet mornings have <em>taken</em> all spring.',
            '…serenity has <em>taken</em> possession…',
            25,
        ];

        yield 'Cropping around single term in center' => [
            'A wonderful serenity has taken possession of my entire <em>soul</em>, like these sweet mornings have taken all spring.',
            '…serenity has taken possession of my entire <em>soul</em>, like these sweet mornings have taken…',
        ];

        yield 'Cropping around repeating term' => [
            'A wonderful serenity has taken possession of my entire <em>soul</em>, like these sweet mornings of spring which I enjoy with my whole <em>soul</em>. I am alone, and feel the charm of existence in this spot, which was created for the bliss of a <em>soul</em> like mine.',
            '…serenity has taken possession of my entire <em>soul</em>, like these sweet mornings of spring which I enjoy with my whole <em>soul</em>. I am alone, and feel the charm of existence…',
        ];

        yield 'Cropping around multiple terms' => [
            'A wonderful serenity has taken possession of my entire <em>soul</em>, like these sweet mornings of spring which I enjoy with my whole being. I am alone, and feel the charm of existence in this spot, which was created for the <em>bliss</em> of a heart like mine.',
            '…serenity has taken possession of my entire <em>soul</em>, like these sweet mornings of spring…',
        ];

        yield 'Cropping at start' => [
            '<em>Wonderful</em> serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole soul.',
            '<em>Wonderful</em> serenity has taken possession of…',
        ];

        yield 'Cropping at end' => [
            'Wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole <em>panorama</em>.',
            '…spring which I enjoy with my whole <em>panorama</em>.',
        ];

        yield 'Cropping with custom length' => [
            'Wonderful serenity has taken possession of my <em>entire</em> soul, like these sweet mornings of spring which I enjoy with my <em>whole</em> panorama.',
            '…my <em>entire</em> soul…',
            15,
        ];

        yield 'Cropping with custom marker' => [
            'Wonderful serenity has taken possession of my <em>entire</em> soul, like these sweet mornings of spring which I enjoy with my <em>whole</em> panorama.',
            ' --- possession of my <em>entire</em> soul, like --- ',
            25,
            ' --- ',
        ];

        yield 'Cropping with custom highlight tags' => [
            'Wonderful serenity has taken <mark>possession</mark> of my <em>entire</em> soul, like these sweet mornings of <mark>spring</mark> which I enjoy with my <em>whole</em> panorama.',
            '…taken <mark>possession</mark> of my <em>entire</em>…',
            25,
            '…',
            '<mark>',
            '</mark>',
        ];

        yield 'Cropping with many distant highlights stays bounded' => [
            str_repeat('prefix filler filler filler filler filler <em>needle</em> suffix filler filler filler filler filler ', 20),
            '…filler filler <em>needle</em> suffix filler…',
            25,
        ];
    }

    #[DataProvider('cropHighlightedTextProvider')]
    public function testCropHighlightedText(string $highlightedText, string $expectedResult, int $cropLength = 50, string $cropMarker = '…', string $highlightStartTag = '<em>', string $highlightEndTag = '</em>'): void
    {
        $cropper = new Cropper($cropLength, $cropMarker, $highlightStartTag, $highlightEndTag);

        $this->assertSame($expectedResult, $cropper->cropHighlightedText($highlightedText));
    }
}
