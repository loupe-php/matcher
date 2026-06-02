<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

interface VariantExpanderInterface
{
    /**
     * @return array<string>
     */
    public function expand(string $term): array;
}
