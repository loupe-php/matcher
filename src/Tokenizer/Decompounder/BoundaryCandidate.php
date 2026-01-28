<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final class BoundaryCandidate
{
    public function __construct(
        public readonly string $left,
        public readonly string $right,
        public readonly int $penalty = 0,
    ) {
    }
}
