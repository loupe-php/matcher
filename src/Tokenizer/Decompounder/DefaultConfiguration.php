<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;

class DefaultConfiguration implements ConfigurationInterface
{
    public function __construct(
        private readonly DictionaryInterface $dictionary,
        private readonly int $minimumDecompositionTermLength,
        /**
         * @var array<string,bool>
         */
        private readonly array $allowList = [],
        /**
         * @var array<string>
         */
        private readonly array $interfixes = [],
    ) {

    }

    public function boundaryCandidates(BoundaryContext $boundaryContext): iterable
    {
        $left = $boundaryContext->left;
        $right = $boundaryContext->right;

        // 1) If both are valid directly, that's a perfect hit (0 penalty)
        if ($this->isValidTerm($left) && $this->isValidTerm($right)) {
            yield new BoundaryCandidate($left, $right, 0);
        }

        // 2) Try configured interfixes, if we find valid terms for those splits, we return them as candidate,
        // but we add a penalty of 1 (direct hits should be preferred)
        foreach ($this->interfixes as $interfix) {
            $length = mb_strlen($interfix);
            if (mb_substr($boundaryContext->term, $boundaryContext->splitPos, $length) !== $interfix) {
                continue;
            }

            $rightAfter = mb_substr($boundaryContext->term, $boundaryContext->splitPos + $length);
            if ($this->isValidTerm($left) && $this->isValidTerm($rightAfter)) {
                yield new BoundaryCandidate($left, $rightAfter, 1);
            }
        }
    }

    public function getMinimumDecompositionTermLength(): int
    {
        return $this->minimumDecompositionTermLength;
    }

    public function isValidTerm(string $term): bool
    {
        $minLength = $this->getMinimumDecompositionTermLength();

        if (mb_strlen($term) < $minLength) {
            return isset($this->allowList[$term]);
        }

        return $this->dictionary->has($term);
    }
}
