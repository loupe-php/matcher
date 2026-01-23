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
     * @param array<string, array<string>|null> $leafCache
     * @param array<string, bool> $decomposableCache
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
        $bestInterfixRemovalCount = null;
        $bestTerms = [];

        foreach ($this->splitCandidates($term, $minLength) as $candidate) {
            $left = $candidate->left;
            $right = $candidate->right;
            $usedInterfixRemoval = $candidate->usedInterfixRemoval;
            $leftIsDecomposable = $this->isDecomposable($left, $minLength, $decomposableCache);

            if (!$leftIsDecomposable) {
                $leftLeaves = [$left];
                $leftPenalty = 0;
            } else {
                [$leftLeaves, $leftPenalty] = $this->collectLeavesOrSelf($left, $leafCache, $decomposableCache);
            }

            [$rightLeaves, $rightPenalty] = $this->collectLeavesOrSelf($right, $leafCache, $decomposableCache);

            $penalty = $leftPenalty + $rightPenalty + ($usedInterfixRemoval ? 1 : 0);

            if ($bestInterfixRemovalCount !== null && $penalty > $bestInterfixRemovalCount) {
                continue; // This is worse, ignore
            }

            if ($bestInterfixRemovalCount === null || $penalty < $bestInterfixRemovalCount) {
                $bestInterfixRemovalCount = $penalty;
                $bestTerms = []; // We found a new best: remove the ones found so far
            }

            foreach ($leftLeaves as $leafTerm) {
                $bestTerms[$leafTerm] = true;
            }
            foreach ($rightLeaves as $leafTerm) {
                $bestTerms[$leafTerm] = true;
            }

            // If configured, keep intermediate dictionary-valid terms that are part of the chosen decomposition tree.
            if ($this->configuration->includeIntermediateTerms()) {
                if ($this->isValidCandidateSide($left, $minLength)) {
                    $bestTerms[$left] = true;
                }
                if ($this->isValidCandidateSide($right, $minLength)) {
                    $bestTerms[$right] = true;
                }
            }
        }

        if ($bestTerms === []) {
            return $leafCache[$term] = null;
        }

        return $leafCache[$term] = array_keys($bestTerms);
    }

    /**
     * @param array<string, array<string>|null> $leafCache
     * @param array<string, bool> $decomposableCache
     * @return array{0:array<string>,1:int}|null
     */
    private function collectLeafTermsWithPenalty(string $term, array &$leafCache, array &$decomposableCache): ?array
    {
        $leaves = $this->collectLeafTerms($term, $leafCache, $decomposableCache);
        if ($leaves === null) {
            return null;
        }

        // Penalty is computed by caller; leaf-only result has zero internal penalty
        return [$leaves, 0];
    }

    /**
     * @param array<string, array<string>|null> $leafCache
     * @param array<string, bool> $decomposableCache
     *
     * @return array{0:array<string>,1:int} A tuple of (leaf terms, penalty)
     */
    private function collectLeavesOrSelf(string $term, array &$leafCache, array &$decomposableCache): array
    {
        $result = $this->collectLeafTermsWithPenalty($term, $leafCache, $decomposableCache);

        if ($result === null) {
            // Important fallback: the side itself is a valid term, even if it cannot be
            // fully decomposed into leaves under current constraints.
            return [[$term], 0];
        }

        return $result;
    }

    private function hasAnySplitCandidate(string $term, int $minLength): bool
    {
        foreach ($this->splitCandidates($term, $minLength) as $_) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, bool> $decomposableCache
     */
    private function isDecomposable(string $term, int $minLength, array &$decomposableCache): bool
    {
        if (isset($decomposableCache[$term])) {
            return $decomposableCache[$term];
        }

        return $decomposableCache[$term] = $this->hasAnySplitCandidate($term, $minLength);
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
     * @return iterable<SplitCandidate>
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
                yield new SplitCandidate($left, $right, false);
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

                $rightAfterInterfix = mb_substr($term, $i + $interfixLength, $termLength - ($i + $interfixLength));

                if ($this->isValidCandidateSide($rightAfterInterfix, $minLength)) {
                    yield new SplitCandidate($left, $rightAfterInterfix, true);
                }
            }
        }
    }
}
