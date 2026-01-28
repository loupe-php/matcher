<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

final readonly class Term
{
    public function __construct(
        public string $term,
        public int $length,
        public bool $isValid
    ) {

    }
}
