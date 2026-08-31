<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;
use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\DefaultTermValidator;
use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\TermValidatorInterface;
use PHPUnit\Framework\TestCase;

final class TermPoolTest extends TestCase
{
    public function testDelegatesProperly(): void
    {
        $dictionary = $this->createMock(DictionaryInterface::class);
        $dictionary
            ->expects($this->exactly(2)) // Asserts that the inner is only called when not in cache yet
            ->method('has')
            ->willReturn(true)
        ;

        $termPool = new TermPool($this->getTermValidator($dictionary));

        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('foo')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
        $this->assertTrue($termPool->term('bar')->isValid);
    }

    public function testClearRemovesCachedTerms(): void
    {
        $dictionary = $this->createMock(DictionaryInterface::class);
        $dictionary
            ->expects($this->exactly(2))
            ->method('has')
            ->willReturn(true)
        ;

        $termPool = new TermPool($this->getTermValidator($dictionary));

        $termPool->term('foo');
        $termPool->term('foo');
        $termPool->clear();
        $termPool->term('foo');
    }

    private function getTermValidator(DictionaryInterface $dictionary): TermValidatorInterface
    {
        return new DefaultTermValidator($dictionary, 3);
    }
}
