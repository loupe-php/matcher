<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final class BoundaryContext
{
    public function __construct(
        public readonly string $term,
        public readonly int $splitPos,
        public readonly string $left,
        public readonly string $right,
    ) {
    }
}
