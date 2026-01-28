<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\TermValidator;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;

final readonly class DefaultTermValidator implements TermValidatorInterface
{
    public function __construct(
        private DictionaryInterface $dictionary,
        private int $minimumDecompositionTermLength,
        /**
         * @var array<string, bool> $allowList
         */
        private array $allowList = []
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
