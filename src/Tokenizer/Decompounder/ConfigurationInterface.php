<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

interface ConfigurationInterface
{
    /**
     * Return all valid boundary candidates for a split position.
     *
     * @return iterable<BoundaryCandidate>
     */
    public function boundaryCandidates(BoundaryContext $boundaryContext): iterable;

    public function getMinimumDecompositionTermLength(): int;

    public function getTermPool(): TermPool;
}
