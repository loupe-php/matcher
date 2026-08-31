<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final class ResultCacheConfiguration
{
    public const DEFAULT_MAX_ENTRIES = 25_000;

    public function __construct(private int $maximumEntries = self::DEFAULT_MAX_ENTRIES)
    {
        $this->validateMaximumEntries($maximumEntries);
    }

    public function getMaximumEntries(): int
    {
        return $this->maximumEntries;
    }

    public function isEnabled(): bool
    {
        return $this->maximumEntries > 0;
    }

    public function withMaximumEntries(int $maximumEntries): self
    {
        $this->validateMaximumEntries($maximumEntries);

        $clone = clone $this;
        $clone->maximumEntries = $maximumEntries;

        return $clone;
    }

    public function withDisabled(): self
    {
        return $this->withMaximumEntries(0);
    }

    private function validateMaximumEntries(int $maximumEntries): void
    {
        if ($maximumEntries < 0) {
            throw new \InvalidArgumentException('The result cache budget must be zero or greater. Use zero to disable caching.');
        }
    }
}
