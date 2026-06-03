<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration\German;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\VariantExpanderInterface;

class GermanVariantExpander implements VariantExpanderInterface
{
    public function expand(string $term): array
    {
        // Künstlerinnen -> Künstlerin
        // Ärztinnen -> Ärztin
        $result = (string) preg_replace('/innen$/u', 'in', $term);

        if ($result === $term) {
            return [];
        }

        return [$result];
    }
}
