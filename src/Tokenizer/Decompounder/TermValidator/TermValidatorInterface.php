<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\TermValidator;

interface TermValidatorInterface
{
    public function isValid(string $term): bool;
}
