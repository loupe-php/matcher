<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
{
    public static function decompositionProvider(): \Generator
    {
        yield 'German Donaudampfschifffahrtsgesellschaftskapitän' => [
            'de',
            'Ich bin von Beruf Donaudampfschifffahrtsgesellschaftskapitän.',
            [
                'ich',
                'bin',
                'von',
                'beruf',
                'donaudampfschifffahrtsgesellschaftskapitan',
                'dampf',
                'donau',
                'fahrt',
                'gesell',
                'kapitan',
                'schaft',
                'schiff',
            ],
        ];

        yield 'German Wartungsvertrag' => [
            'de',
            'Wartungsvertrag',
            [
                'wartungsvertrag',
                'vertrag',
                'wartung',
            ],
        ];

        yield 'Dutch' => [
            'nl',
            'De ziektekostenverzekering is duur.',
            [
                'de',
                'ziektekostenverzekering',
                'kost',
                'kosten',
                'ver',
                'zekering',
                'ziekte',
                'is',
                'duur',
            ],
        ];
    }

    /**
     * @param array<string> $expectedTermsWithVariants
     */
    #[DataProvider('decompositionProvider')]
    public function testDecomposition(string $locale, string $string, array $expectedTermsWithVariants): void
    {
        $tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString($locale));

        $this->assertSame($expectedTermsWithVariants, $tokenizer->tokenize($string)->allTermsWithVariants());
    }

    public function testGermanEszettNormalization(): void
    {
        $tokenizer = new Tokenizer();
        // ß should normalize to ss
        $this->assertSame([
            'die',
            'strasse',
            'ist',
            'neben',
            'dem',
            'grosseren',
            'gebaude',
        ], $tokenizer->tokenize('Die Straße ist neben dem größeren Gebäude.')
            ->allTermsWithVariants());
    }

    public function testIcelandicSpecialCharacters(): void
    {
        $tokenizer = new Tokenizer();
        // ð (eth) and þ (thorn) are special characters that should normalize to d and th
        $this->assertSame([
            'thad',
            'er',
            'gott',
            'ad',
            'lesa',
            'islenska',
        ], $tokenizer->tokenize('Það er gott að lesa íslenska.')
            ->allTermsWithVariants());
    }

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
            'weiss',
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
            'weiss',
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
            'weiss',
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
            'weiss',
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
            'weiss',
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

    public function testPolishLNormalization(): void
    {
        $tokenizer = new Tokenizer();
        // Ł/ł should normalize to l
        $this->assertSame([
            'lodz',
            'ma',
            'piekne',
            'zolte',
            'lodzie',
        ], $tokenizer->tokenize('Łódź ma piękne żółte łodzie.')
            ->allTermsWithVariants());
    }

    public function testSlovakDiacriticsNormalization(): void
    {
        $tokenizer = new Tokenizer();
        // Slovak diacritics: č, š, ž, ň, ľ, ť, ď, á, é, í, ó, ú, ý, ô should normalize to c, s, z, n, l, t, d, a, e, i, o, u, y, o
        $this->assertSame([
            'kniznica',
            'ma',
            'velky',
            'vyber',
            'knih',
            'a',
            'casopisov',
        ], $tokenizer->tokenize('Knižnica má veľký výber kníh a časopisov.')
            ->allTermsWithVariants());
    }

    public function testSwedishDiacriticsNormalization(): void
    {
        $tokenizer = new Tokenizer();
        // å/ä/ö should normalize to a/a/o
        $this->assertSame([
            'blabarssoppa',
            'ar',
            'god',
            'och',
            'sot',
        ], $tokenizer->tokenize('Blåbärssoppa är god och söt.')
            ->allTermsWithVariants());
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
            'weiss',
            'von',
            'nichts',
        ], $tokenizer->tokenize('Hallo, mein "Name ist Hase" und ich weiß von nichts.')
            ->allTermsWithVariants());
    }
}
