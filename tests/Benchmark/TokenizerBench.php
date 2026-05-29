<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Benchmark;

use Loupe\Matcher\Tokenizer\Tokenizer;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[BeforeMethods('setUp')]
#[Revs(100)]
#[Iterations(10)]
#[Warmup(2)]
#[OutputTimeUnit('microseconds', precision: 2)]
class TokenizerBench
{
    private string $text;

    private Tokenizer $tokenizer;

    /**
     * @param array{locale: string, size: int} $params
     */
    public function setUp(array $params): void
    {
        $this->tokenizer = new Tokenizer($params['locale']);
        $this->text = self::loadFixture($params['locale'], $params['size']);
    }

    /**
     * Pure ICU break-iterator pass — no Tokenizer code involved.
     * Drift here between baseline and PR indicates environmental noise
     * (runner load, thermal state), not a real regression in Tokenizer.
     */
    #[ParamProviders('provideCorpus')]
    #[Groups(['control'])]
    public function benchControlBreakIterator(): void
    {
        $bi = \IntlRuleBasedBreakIterator::createWordInstance(null); // @phpstan-ignore-line - null is allowed
        $bi->setText($this->text);
        foreach ($bi->getPartsIterator() as $_) {
        }
    }

    #[ParamProviders('provideCorpus')]
    public function benchTokenize(): void
    {
        $this->tokenizer->tokenize($this->text);
    }

    public function provideCorpus(): \Generator
    {
        foreach (['en', 'de'] as $locale) {
            foreach ([100, 1000, 10000] as $size) {
                yield "{$locale}-{$size}" => [
                    'locale' => $locale,
                    'size' => $size,
                ];
            }
        }
    }

    private static function loadFixture(string $locale, int $size): string
    {
        $raw = file_get_contents(__DIR__ . "/fixtures/{$locale}.txt");
        if ($raw === false) {
            throw new \RuntimeException("Missing fixture for locale {$locale}");
        }

        // Repeat until long enough, then trim to the exact byte length.
        $len = \strlen($raw);
        if ($len < $size) {
            $raw = str_repeat($raw, intdiv($size, $len) + 1);
        }

        return substr($raw, 0, $size);
    }
}
