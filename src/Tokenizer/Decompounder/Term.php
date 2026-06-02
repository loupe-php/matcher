<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final class Term
{
    public function __construct(
        public readonly string $term,
        public readonly int $length,
        public readonly bool $isValid
    ) {

    }
}
