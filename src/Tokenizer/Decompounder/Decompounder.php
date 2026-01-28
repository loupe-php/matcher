<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

class Decompounder
{
    public function __construct(
        private ConfigurationInterface $configuration,
        private bool $includeIntermediateTerms
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
     * Returns false if the term cannot be fully decomposed into dictionary-valid (or allow listed) leaves.
     *
     * @param array<string, array<string>|false> $leafCache
     * @param array<string, bool> $decomposableCache
     *
     * @return array<string>|false
     */
    private function collectLeafTerms(string $term, array &$leafCache, array &$decomposableCache): array|false
    {
        if (isset($leafCache[$term])) {
            return $leafCache[$term];
        }

        $termIsValid = $this->configuration->isValidTerm($term);
        $termIsDecomposable = $this->isDecomposable($term, $decomposableCache);

        // If we only want leaves: a valid, non-decomposable term is a leaf.
        if ($termIsValid && !$this->includeIntermediateTerms && !$termIsDecomposable) {
            return $leafCache[$term] = [$term];
        }

        // If we want intermediate terms: start with the term itself if it is valid.
        $bestInterfixRemovalCount = null;
        $bestTerms = [];

        foreach ($this->splitCandidates($term) as $candidate) {
            $left = $candidate->left;
            $right = $candidate->right;
            $leftIsDecomposable = $this->isDecomposable($left, $decomposableCache);

            if (!$leftIsDecomposable) {
                $leftLeaves = [$left];
                $leftPenalty = 0;
            } else {
                [$leftLeaves, $leftPenalty] = $this->collectLeavesOrSelf($left, $leafCache, $decomposableCache);
            }

            [$rightLeaves, $rightPenalty] = $this->collectLeavesOrSelf($right, $leafCache, $decomposableCache);
            $penalty = $leftPenalty + $rightPenalty + $candidate->penalty;

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
            if ($this->includeIntermediateTerms) {
                $bestTerms[$left] = true;
                $bestTerms[$right] = true;
            }
        }

        if ($bestTerms === []) {
            return $leafCache[$term] = false;
        }

        return $leafCache[$term] = array_keys($bestTerms);
    }

    /**
     * @param array<string, array<string>|false> $leafCache
     * @param array<string, bool> $decomposableCache
     * @return array{0:array<string>,1:int}|false
     */
    private function collectLeafTermsWithPenalty(string $term, array &$leafCache, array &$decomposableCache): array|false
    {
        $leaves = $this->collectLeafTerms($term, $leafCache, $decomposableCache);
        if ($leaves === false) {
            return false;
        }

        // Penalty is computed by caller; leaf-only result has zero internal penalty
        return [$leaves, 0];
    }

    /**
     * @param array<string, array<string>|false> $leafCache
     * @param array<string, bool> $decomposableCache
     *
     * @return array{0:array<string>,1:int} A tuple of (leaf terms, penalty)
     */
    private function collectLeavesOrSelf(string $term, array &$leafCache, array &$decomposableCache): array
    {
        $result = $this->collectLeafTermsWithPenalty($term, $leafCache, $decomposableCache);

        if ($result === false) {
            // Only fallback to the term itself if it is valid. Otherwise, make it very costly.
            return $this->configuration->isValidTerm($term) ? [[$term], 0] : [[], 100];
        }

        return $result;
    }

    private function hasAnySplitCandidate(string $term): bool
    {
        foreach ($this->splitCandidates($term) as $_) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, bool> $decomposableCache
     */
    private function isDecomposable(string $term, array &$decomposableCache): bool
    {
        if (isset($decomposableCache[$term])) {
            return $decomposableCache[$term];
        }

        return $decomposableCache[$term] = $this->hasAnySplitCandidate($term);
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

        return $result === false ? [] : $result;
    }

    /**
     * @return iterable<BoundaryCandidate>
     */
    private function splitCandidates(string $term): iterable
    {
        $termLength = mb_strlen($term);

        if ($termLength < 2) {
            return;
        }

        for ($i = 1; $i <= $termLength - 1; $i++) {
            yield from $this->configuration->boundaryCandidates(
                new BoundaryContext($term, $i, mb_substr($term, 0, $i), mb_substr($term, $i))
            );
        }
    }
}
