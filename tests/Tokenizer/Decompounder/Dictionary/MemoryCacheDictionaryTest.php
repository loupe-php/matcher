<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\MemoryCacheDictionary;
use PHPUnit\Framework\TestCase;

class MemoryCacheDictionaryTest extends TestCase
{
    public function testDelegatesProperly(): void
    {
        $inner = $this->createMock(DictionaryInterface::class);
        $inner
            ->expects($this->exactly(2)) // Asserts that the inner is only called when not in cache yet
            ->method('has')
            ->willReturn(true)
        ;

        $memoryCacheDictionary = new MemoryCacheDictionary($inner);

        $this->assertTrue($memoryCacheDictionary->has('foo'));
        $this->assertTrue($memoryCacheDictionary->has('foo'));
        $this->assertTrue($memoryCacheDictionary->has('bar'));
        $this->assertTrue($memoryCacheDictionary->has('bar'));
    }

    public function testWithCacheSize(): void
    {
        $inner = $this->createMock(DictionaryInterface::class);
        $inner
            ->expects($this->exactly(5))
            ->method('has')
            ->willReturn(true)
        ;

        $memoryCacheDictionary = new MemoryCacheDictionary($inner, 2);

        $this->assertTrue($memoryCacheDictionary->has('foo'));
        $this->assertTrue($memoryCacheDictionary->has('foo'));
        $this->assertTrue($memoryCacheDictionary->has('bar'));
        $this->assertTrue($memoryCacheDictionary->has('bar'));
        $this->assertTrue($memoryCacheDictionary->has('baz'));
        $this->assertTrue($memoryCacheDictionary->has('baz'));
        $this->assertTrue($memoryCacheDictionary->has('foo'));
        $this->assertTrue($memoryCacheDictionary->has('foo'));
        $this->assertTrue($memoryCacheDictionary->has('bar'));
        $this->assertTrue($memoryCacheDictionary->has('bar'));
    }
}
