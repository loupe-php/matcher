<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

class DefaultConfiguration implements ConfigurationInterface
{
    public function __construct(
        private readonly TermPool $termPool,
        private readonly int $minimumDecompositionTermLength,
        /**
         * @var array<string>
         */
        private readonly array $interfixes = [],
    ) {

    }

    public function boundaryCandidates(BoundaryContext $boundaryContext): iterable
    {
        $left = $boundaryContext->left;
        $right = $boundaryContext->right;

        // 1) If both are valid directly, that's a perfect hit (0 penalty)
        if ($left->isValid && $right->isValid) {
            yield new BoundaryCandidate($left, $right, 0);
        }

        // 2) Try configured interfixes, if we find valid terms for those splits, we return them as candidate,
        // but we add a penalty of 1 (direct hits should be preferred)
        foreach ($this->interfixes as $interfix) {
            $length = mb_strlen($interfix);
            if (mb_substr($boundaryContext->term->term, $boundaryContext->splitPos, $length) !== $interfix) {
                continue;
            }

            $rightAfter = $this->getTermPool()->term(mb_substr($boundaryContext->term->term, $boundaryContext->splitPos + $length));
            if ($left->isValid && $rightAfter->isValid) {
                yield new BoundaryCandidate($left, $rightAfter, 1);
            }
        }
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return $this->minimumDecompositionTermLength;
    }

    public function getTermPool(): TermPool
    {
        return $this->termPool;
    }
}
