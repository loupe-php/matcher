<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\Decompounder\DefaultConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\ResultCacheConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;
use PHPUnit\Framework\TestCase;

final class DecompounderResultCacheTest extends TestCase
{
    public function testCachesDecomposedAndInvalidCompleteTerms(): void
    {
        [$decompounder, $validator] = $this->createDecompounder();

        $this->assertSame(['film', 'making'], $decompounder->decompoundTerm('filmmaking'));
        $callsAfterCompound = $validator->getCallCount();
        $this->assertSame(['film', 'making'], $decompounder->decompoundTerm('filmmaking'));
        $this->assertSame($callsAfterCompound, $validator->getCallCount());

        $this->assertSame([], $decompounder->decompoundTerm('beautiful'));
        $callsAfterInvalidTerm = $validator->getCallCount();
        $this->assertSame([], $decompounder->decompoundTerm('beautiful'));
        $this->assertSame($callsAfterInvalidTerm, $validator->getCallCount());
    }

    public function testDisabledCacheAlwaysDecompoundsAgain(): void
    {
        [$decompounder, $validator] = $this->createDecompounder(
            (new ResultCacheConfiguration())->withDisabled(),
        );

        $decompounder->decompoundTerm('beautiful');
        $callsAfterFirstTerm = $validator->getCallCount();
        $decompounder->decompoundTerm('beautiful');

        $this->assertGreaterThan($callsAfterFirstTerm, $validator->getCallCount());
    }

    public function testCacheUsesFifoEviction(): void
    {
        [$decompounder, $validator] = $this->createDecompounder(new ResultCacheConfiguration(2));

        $decompounder->decompoundTerm('filmmaking');
        $decompounder->decompoundTerm('beautiful');
        $callsAfterInitialTerms = $validator->getCallCount();

        $decompounder->decompoundTerm('filmmaking'); // A hit must not promote this entry.
        $this->assertSame($callsAfterInitialTerms, $validator->getCallCount());
        $decompounder->decompoundTerm('wonderful');
        $callsAfterEviction = $validator->getCallCount();
        $decompounder->decompoundTerm('filmmaking');

        $this->assertGreaterThan($callsAfterEviction, $validator->getCallCount());
    }

    public function testCacheCanBeClearedExplicitly(): void
    {
        [$decompounder, $validator] = $this->createDecompounder();

        $decompounder->decompoundTerm('filmmaking');
        $callsAfterFirstTerm = $validator->getCallCount();
        $decompounder->clearResultCache();
        $decompounder->decompoundTerm('filmmaking');

        $this->assertGreaterThan($callsAfterFirstTerm, $validator->getCallCount());
    }

    /**
     * @return array{Decompounder, CountingTermValidator}
     */
    private function createDecompounder(ResultCacheConfiguration|null $resultCacheConfiguration = null): array
    {
        $validator = new CountingTermValidator(['film', 'making']);
        $termPool = new TermPool($validator);
        $configuration = new DefaultConfiguration($termPool, 3);

        return [new Decompounder($configuration, false, $resultCacheConfiguration), $validator];
    }
}
