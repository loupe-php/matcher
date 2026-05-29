<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer;

use Loupe\Matcher\Tokenizer\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenizerNormalizationTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: array<string>}>
     */
    public static function foldingCases(): iterable
    {
        yield 'fi ligature' => ['ﬁfteen', ['fifteen']];
        yield 'fl ligature' => ['ﬂight', ['flight']];
        yield 'ffi ligature' => ['oﬃce', ['office']];
        yield 'fullwidth Latin' => ['ＡＢＣ', ['abc']];
        yield 'math script A' => ['𝒜pple', ['apple']];
        yield 'math bold a' => ['𝐚bc', ['abc']];
        yield 'math fraktur' => ['𝔄rt', ['art']];
        yield 'NFD-form café' => ["cafe\u{0301}", ['cafe']];
        yield 'NFD-form naïve' => ["nai\u{0308}ve", ['naive']];
        yield 'combined acute + diaeresis' => ["a\u{0301}\u{0308}", ['a']];
        yield 'German ß (lowercase)' => ['Straße', ['strasse']];
        yield 'German ẞ (uppercase)' => ['STRAẞE', ['strasse']];
        yield 'Polish Łódź' => ['Łódź', ['lodz']];
        yield 'Polish żółć' => ['żółć', ['zolc']];
        yield 'Czech haček' => ['čeština', ['cestina']];
        yield 'Scandinavian å ø' => ['Mårten ørred', ['marten', 'orred']];
        yield 'Vietnamese tones' => ['phở', ['pho']];
        yield 'French ç' => ['garçon', ['garcon']];
        yield 'Icelandic þ ð' => ['Það er', ['thad', 'er']];
        yield 'Æ ligature' => ['Ælfred', ['aelfred']];
        yield 'Œ ligature' => ['Œuvre', ['oeuvre']];
        yield 'angstrom ' => ['Å', ['a']];
        yield 'multi-language sentence' => [
            'A café in Zürich is où you naïvely meet for piña coladas and crème brûlée.',
            ['a', 'cafe', 'in', 'zurich', 'is', 'ou', 'you', 'naively', 'meet', 'for', 'pina', 'coladas', 'and', 'creme', 'brulee'],
        ];
        yield 'isolated combining mark' => ["\u{0301}", []];
        yield 'superscript digit splits word' => ['x²', ['x']];
        yield 'subscript splits word' => ['H₂O', ['h', 'o']];
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('foldingCases')]
    public function testFolding(string $input, array $expected): void
    {
        $tokenizer = new Tokenizer();
        $this->assertSame(
            $expected,
            $tokenizer->tokenize($input)->allTermsWithVariants()
        );
    }

    public function testWasFoldedFlagForAsciiInput(): void
    {
        $tokenizer = new Tokenizer();
        foreach ($tokenizer->tokenize('Hello World 123')->all() as $token) {
            $this->assertFalse(
                $token->wasFolded(),
                \sprintf('Expected token %s to be not folded', $token->getTerm())
            );
        }
    }

    public function testWasFoldedFlagForCaseOnlyChange(): void
    {
        $tokenizer = new Tokenizer();
        $token = $tokenizer->tokenize('Test')->all()[0];
        $this->assertSame('test', $token->getTerm());
        $this->assertFalse($token->wasFolded());
    }

    public function testWasFoldedFlagForFoldedInput(): void
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize('Straße naïve café')->all();
        $this->assertCount(3, $tokens);
        foreach ($tokens as $token) {
            $this->assertTrue(
                $token->wasFolded(),
                \sprintf('Expected token %s to be marked as folded', $token->getTerm())
            );
        }
    }
}
