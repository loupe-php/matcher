<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\TermValidatorInterface;

final class CountingTermValidator implements TermValidatorInterface
{
    private int $callCount = 0;

    /**
     * @param array<string> $validTerms
     */
    public function __construct(private readonly array $validTerms)
    {
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function isValid(string $term): bool
    {
        ++$this->callCount;

        return \in_array($term, $this->validTerms, true);
    }
}
