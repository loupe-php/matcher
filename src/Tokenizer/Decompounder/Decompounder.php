<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

class Decompounder
{
    public const int COSTLY_PENALTY = 100;

    private TermPool $termPool;

    public function __construct(
        private ConfigurationInterface $configuration,
        private bool $includeIntermediateTerms
    ) {
        $this->termPool = $this->configuration->getTermPool();
    }

    /**
     * Returns all decomposition split variants (meaning no part can be further decomposed),
     * and never returns the term itself.
     * @return array<string>
     */
    public function decompoundTerm(string $term): array
    {
        $term = $this->termPool->term($term);
        if ($term->length <= $this->configuration->getMinimumDecompositionTermLength()) {
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
     * @param array<string, array<Term>|false> $leafCache
     * @param array<string, bool> $decomposableCache
     *
     * @return array<Term>|false
     */
    private function collectLeafTerms(Term $term, array &$leafCache, array &$decomposableCache): array|false
    {
        if (isset($leafCache[$term->term])) {
            return $leafCache[$term->term];
        }

        if (!$this->isDecomposable($term, $decomposableCache)) {
            return $leafCache[$term->term] = ($term->isValid ? [$term] : false);
        }

        $bestPenalty = null;
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

            if ($bestPenalty !== null && $penalty > $bestPenalty) {
                continue; // This is worse, ignore
            }

            if ($bestPenalty === null || $penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestTerms = []; // We found a new best: remove the ones found so far
            }

            foreach ($leftLeaves as $leafTerm) {
                $bestTerms[$leafTerm->term] = $leafTerm;
            }
            foreach ($rightLeaves as $leafTerm) {
                $bestTerms[$leafTerm->term] = $leafTerm;
            }

            // If configured, keep intermediate dictionary-valid terms that are part of the chosen decomposition tree.
            if ($this->includeIntermediateTerms) {
                $bestTerms[$left->term] = $left;
                $bestTerms[$right->term] = $right;
            }
        }

        if ($bestTerms === []) {
            return $leafCache[$term->term] = false;
        }

        return $leafCache[$term->term] = array_values($bestTerms);
    }

    /**
     * @param array<string, array<Term>|false> $leafCache
     * @param array<string, bool> $decomposableCache
     * @return array{0:array<Term>,1:int}|false
     */
    private function collectLeafTermsWithPenalty(Term $term, array &$leafCache, array &$decomposableCache): array|false
    {
        $leaves = $this->collectLeafTerms($term, $leafCache, $decomposableCache);
        if ($leaves === false) {
            return false;
        }

        // Penalty is computed by caller; leaf-only result has zero internal penalty
        return [$leaves, 0];
    }

    /**
     * @param array<string, array<Term>|false> $leafCache
     * @param array<string, bool> $decomposableCache
     *
     * @return array{0:array<Term>,1:int} A tuple of (leaf terms, penalty)
     */
    private function collectLeavesOrSelf(Term $term, array &$leafCache, array &$decomposableCache): array
    {
        $result = $this->collectLeafTermsWithPenalty($term, $leafCache, $decomposableCache);

        if ($result === false) {
            // Only fallback to the term itself if it is valid. Otherwise, make it very costly.
            return $term->isValid ? [[$term], 0] : [[], self::COSTLY_PENALTY];
        }

        return $result;
    }

    private function hasAnySplitCandidate(Term $term): bool
    {
        foreach ($this->splitCandidates($term) as $_) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, bool> $decomposableCache
     */
    private function isDecomposable(Term $term, array &$decomposableCache): bool
    {
        if (isset($decomposableCache[$term->term])) {
            return $decomposableCache[$term->term];
        }

        return $decomposableCache[$term->term] = $this->hasAnySplitCandidate($term);
    }

    /**
     * @return array<string>
     */
    private function split(Term $term): array
    {
        $leafCache = [];
        $decomposableCache = [];
        $result = [];
        $leaves = $this->collectLeafTerms($term, $leafCache, $decomposableCache);

        if ($leaves === false) {
            return $result;
        }

        foreach ($leaves as $leaf) {
            $result[] = $leaf->term;
        }

        // Ignore ourselves
        if ([$term->term] === $result) {
            return [];
        }

        return $result;
    }

    /**
     * @return iterable<BoundaryCandidate>
     */
    private function splitCandidates(Term $term): iterable
    {
        if ($term->length < 2) {
            return;
        }

        for ($i = 1; $i <= $term->length - 1; $i++) {
            yield from $this->configuration->boundaryCandidates(
                new BoundaryContext(
                    $term,
                    $i,
                    $this->termPool->term(mb_substr($term->term, 0, $i)),
                    $this->termPool->term(mb_substr($term->term, $i)),
                )
            );
        }
    }
}
