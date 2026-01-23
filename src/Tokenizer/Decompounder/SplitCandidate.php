<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final readonly class SplitCandidate
{
    public function __construct(
        public string $left,
        public string $right,
        public bool $usedInterfixRemoval
    ) {
    }
}
