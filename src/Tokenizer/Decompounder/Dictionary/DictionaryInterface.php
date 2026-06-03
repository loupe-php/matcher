<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

interface DictionaryInterface
{
    public function has(string $term): bool;
}
