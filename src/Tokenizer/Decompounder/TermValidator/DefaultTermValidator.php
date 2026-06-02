<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\TermValidator;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;

final class DefaultTermValidator implements TermValidatorInterface
{
    public function __construct(
        private readonly DictionaryInterface $dictionary,
        private readonly int $minimumDecompositionTermLength,
        /**
         * @var array<string, bool> $allowList
         */
        private readonly array $allowList = []
    ) {

    }

    public function isValid(string $term): bool
    {
        if (mb_strlen($term) < $this->minimumDecompositionTermLength) {
            return isset($this->allowList[$term]);
        }

        return $this->dictionary->has($term);
    }
}
