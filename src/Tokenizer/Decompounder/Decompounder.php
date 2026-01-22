<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

class Decompounder
{
    public function __construct(
        private Configuration $configuration
    ) {
    }

    /**
     * Returns all decomposition split variants (meaning no part can be further decomposed),
     * and never returns the term itself.
     * @return array<string>
     */
    public function decompoundTerm(string $term, int|null $termLength = null): array
    {
        $termLength = $termLength ?? mb_strlen($term);
        if ($termLength <= $this->configuration->getMinimumDecompositionTermLength()) {
            return [];
        }

        $variants = $this->split($term);

        // Keep a stable order
        sort($variants, SORT_STRING);

        return $variants;
    }

    /**
     * Collect unique leaf terms for given term.
     * Returns null if the term cannot be fully decomposed into dictionary-valid (or allow listed) leaves.
     *
     * @param array<mixed> $leafCache
     * @param array<mixed> $decomposableCache
     *
     * @return array<mixed>|null
     */
    private function collectLeafTerms(string $term, array &$leafCache, array &$decomposableCache): ?array
    {
        if (\array_key_exists($term, $leafCache)) {
            return $leafCache[$term];
        }

        $minLength = $this->configuration->getMinimumDecompositionTermLength();

        $termIsValid = $this->isValidCandidateSide($term, $minLength);
        $termIsDecomposable = $this->isDecomposable($term, $minLength, $decomposableCache);

        // If we only want leaves: a valid, non-decomposable term is a leaf.
        if ($termIsValid && !$this->configuration->includeIntermediateTerms() && !$termIsDecomposable) {
            return $leafCache[$term] = [$term];
        }

        // If we want intermediate terms: start with the term itself if it is valid.
        $uniqueTerms = [];
        if ($termIsValid && $this->configuration->includeIntermediateTerms()) {
            $uniqueTerms[$term] = true;
        }

        foreach ($this->splitCandidates($term, $minLength) as [$left, $right]) {
            $leftIsDecomposable = $this->isDecomposable($left, $minLength, $decomposableCache);

            if (!$leftIsDecomposable) {
                $leftLeaves = [$left];
            } else {
                $leftLeaves = $this->collectLeafTerms($left, $leafCache, $decomposableCache);
                if ($leftLeaves === null) {
                    continue;
                }
            }

            $rightLeaves = $this->collectLeafTerms($right, $leafCache, $decomposableCache);
            if ($rightLeaves === null) {
                continue;
            }

            foreach ($leftLeaves as $leafTerm) {
                $uniqueTerms[$leafTerm] = true;
            }
            foreach ($rightLeaves as $leafTerm) {
                $uniqueTerms[$leafTerm] = true;
            }
        }

        if ($uniqueTerms === []) {
            return $leafCache[$term] = null;
        }

        return $leafCache[$term] = array_keys($uniqueTerms);
    }

    /**
     * True if term can be split into two valid terms (dictionary-valid OR allow-listed),
     * either directly (left|right) or by removing an interfix at the boundary (left|interfix|right).
     *
     * @param array<string, bool> $decomposableCache
     */
    private function isDecomposable(string $term, int $minLength, array &$decomposableCache): bool
    {
        if (isset($decomposableCache[$term])) {
            return $decomposableCache[$term];
        }

        foreach ($this->splitCandidates($term, $minLength) as [$left, $right]) {
            // splitCandidates already guarantees both sides are valid; any yielded pair means decomposable.
            return $decomposableCache[$term] = true;
        }

        return $decomposableCache[$term] = false;
    }

    /**
     * A side is valid if:
     * - if shorter than minLength => must be allow-listed
     * - otherwise => must be in dictionary
     */
    private function isValidCandidateSide(string $term, int $minLength): bool
    {
        if (mb_strlen($term) < $minLength) {
            return $this->configuration->isTermOnAllowList($term);
        }

        return $this->configuration->getDictionary()->has($term);
    }

    /**
     * @return array<string>
     */
    private function split(string $term): array
    {
        $leafCache = [];
        $decomposableCache = [];

        $result = $this->collectLeafTerms($term, $leafCache, $decomposableCache);

        // Ignore ourselves
        if ([$term] === $result) {
            return [];
        }

        return $result ?? [];
    }

    /**
     * Yield all candidate (left, right) pairs where both sides are valid:
     * - each side is either allow-listed (if shorter than min length) or in the dictionary (if longer than or equal to min length)
     *
     * @return iterable<array{0:string,1:string}>
     */
    private function splitCandidates(string $term, int $minLength): iterable
    {
        $interfixes = $this->configuration->getInterfixes();
        $termLength = mb_strlen($term);

        if ($termLength < 2) {
            return;
        }

        for ($i = 1; $i <= $termLength - 1; $i++) {
            $left = mb_substr($term, 0, $i);
            if (!$this->isValidCandidateSide($left, $minLength)) {
                continue;
            }

            // Direct boundary: left | right
            $right = mb_substr($term, $i, $termLength - $i);
            if ($this->isValidCandidateSide($right, $minLength)) {
                yield [$left, $right];
            }

            // Interfix boundary: left | interfix | rightAfterInterfix
            foreach ($interfixes as $interfix) {
                $interfixLength = mb_strlen($interfix);

                if ($i + $interfixLength > $termLength) {
                    continue;
                }

                if (mb_substr($term, $i, $interfixLength) !== $interfix) {
                    continue;
                }

                $rightAfterInterfix = mb_substr(
                    $term,
                    $i + $interfixLength,
                    $termLength - ($i + $interfixLength)
                );

                if ($this->isValidCandidateSide($rightAfterInterfix, $minLength)) {
                    yield [$left, $rightAfterInterfix];
                }
            }
        }
    }
}
