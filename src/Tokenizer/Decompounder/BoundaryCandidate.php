<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final class BoundaryCandidate
{
    public function __construct(
        public readonly Term $left,
        public readonly Term $right,
        public readonly int $penalty = 0,
    ) {
        if (!$this->left->isValid || !$this->right->isValid) {
            throw new \InvalidArgumentException('Boundary candidate cannot contain invalid terms.');
        }
    }
}
