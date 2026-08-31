<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\ResultCacheConfiguration;
use PHPUnit\Framework\TestCase;

final class ResultCacheConfigurationTest extends TestCase
{
    public function testDefaultBudget(): void
    {
        $configuration = new ResultCacheConfiguration();

        $this->assertSame(25_000, $configuration->getMaximumEntries());
        $this->assertTrue($configuration->isEnabled());
    }

    public function testConfigurationIsImmutableAndCanDisableCaching(): void
    {
        $configuration = new ResultCacheConfiguration();
        $disabledConfiguration = $configuration->withDisabled();

        $this->assertNotSame($configuration, $disabledConfiguration);
        $this->assertSame(25_000, $configuration->getMaximumEntries());
        $this->assertSame(0, $disabledConfiguration->getMaximumEntries());
        $this->assertFalse($disabledConfiguration->isEnabled());
    }

    public function testNegativeBudgetIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Use zero to disable caching');

        new ResultCacheConfiguration(-1);
    }
}
