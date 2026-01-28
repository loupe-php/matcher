<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;
use PHPUnit\Framework\TestCase;

class TermPoolTest extends TestCase
{
    public function testDelegatesProperly(): void
    {
        $dictionary = $this->createMock(DictionaryInterface::class);
        $dictionary
            ->expects($this->exactly(2)) // Asserts that the inner is only called when not in cache yet
            ->method('has')
            ->willReturn(true)
        ;

        $termPool = new TermPool($this->getIsValidClosure($dictionary));

        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
    }

    public function testWithCacheSize(): void
    {
        $dictionary = $this->createMock(DictionaryInterface::class);
        $dictionary
            ->expects($this->exactly(5))
            ->method('has')
            ->willReturn(true)
        ;

        $termPool = new TermPool($this->getIsValidClosure($dictionary), 2);

        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
        $this->assertTrue($termPool->term('baz')->isValid);
        $this->assertTrue($termPool->term('baz')->isValid);
        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
    }

    private function getIsValidClosure(DictionaryInterface $dictionary): \Closure
    {
        return function (string $term) use ($dictionary) {
            return $dictionary->has($term);
        };
    }
}
