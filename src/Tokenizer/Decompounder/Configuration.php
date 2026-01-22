<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;

class Configuration
{
    /**
     * @var array<string, bool>
     */
    private array $allowList = [];

    /**
     * Very often, terms can be split into multiple terms.
     * Imagine the German "dampfschifffahrt". It is "dampf", "schiff" and "fahrt.
     * But "dampfschiff" and "schiffahrt" are both also valid, they can just be split even further.
     * When set to true, these are kept in the result (default).
     */
    private bool $includeIntermediateTerms = true;

    /**
     * @var array<string>
     */
    private array $interfixes = [];

    public function __construct(
        private DictionaryInterface $dictionary,
        private int $minimumDecompositionTermLength
    ) {

    }

    /**
     * @return array<string, bool>
     */
    public function getAllowList(): array
    {
        return $this->allowList;
    }

    public function getDictionary(): DictionaryInterface
    {
        return $this->dictionary;
    }

    /**
     * @return array<string>
     */
    public function getInterfixes(): array
    {
        return $this->interfixes;
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return $this->minimumDecompositionTermLength;
    }

    public function includeIntermediateTerms(): bool
    {
        return $this->includeIntermediateTerms;
    }

    public function isTermOnAllowList(string $term): bool
    {
        return isset($this->allowList[$term]);
    }

    /**
     * The allow list must be keyed by the terms. The value must be true.
     *
     * @param array<string, bool> $allowList
     */
    public function withAllowList(array $allowList): self
    {
        foreach ($allowList as $k => $v) {
            if (!\is_string($k) || $v !== true) {
                throw new \InvalidArgumentException('Invalid allow list format.');
            }

            if (mb_strlen($k) >= $this->minimumDecompositionTermLength) {
                throw new \LogicException('Terms on the allow list must be shorter than the minimum allowed length.');
            }
        }

        $clone = clone $this;
        $clone->allowList = $allowList;
        return $clone;
    }

    /**
     * @param array<string> $interfixes
     */
    public function withInterfixes(array $interfixes): self
    {
        $clone = clone $this;
        $clone->interfixes = $interfixes;
        return $clone;
    }

    public function withIntermediateTerms(bool $includeIntermediateTerms): self
    {

        $clone = clone $this;
        $clone->includeIntermediateTerms = $includeIntermediateTerms;
        return $clone;
    }
}
