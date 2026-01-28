<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

final class VariantDictionary implements DictionaryInterface
{
    public function __construct(
        private DictionaryInterface $inner,
        private VariantExpanderInterface $expander
    ) {
    }

    public function has(string $term): bool
    {
        if ($this->inner->has($term)) {
            return true;
        }

        foreach ($this->expander->expand($term) as $variant) {
            if ($this->inner->has($variant)) {
                return true;
            }
        }

        return false;
    }
}
