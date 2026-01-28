<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final class BoundaryContext
{
    public function __construct(
        public readonly Term $term,
        public readonly int $splitPos,
        public readonly Term $left,
        public readonly Term $right,
    ) {
    }
}
