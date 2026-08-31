<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

class Decompounder
{
    public const COSTLY_PENALTY = 100;

    private readonly TermPool $termPool;

    /**
     * @var array<string, array<string>>
     */
    private array $resultCache = [];

    private int $resultCacheSize = 0;

    private readonly int $maximumResultCacheEntries;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly bool $includeIntermediateTerms,
        ResultCacheConfiguration|null $resultCacheConfiguration = null,
    ) {
        $this->termPool = $this->configuration->getTermPool();
        $this->maximumResultCacheEntries = ($resultCacheConfiguration ?? new ResultCacheConfiguration())->getMaximumEntries();
    }

    /**
     * Returns all decomposition split variants (meaning no part can be further decomposed),
     * and never returns the term itself.
     *
     * @return array<string>
     */
    public function decompoundTerm(string $term): array
    {
        if (0 !== $this->maximumResultCacheEntries && isset($this->resultCache[$term])) {
            $variants = $this->resultCache[$term];
            unset($this->resultCache[$term]);
            $this->resultCache[$term] = $variants;

            return $variants;
        }

        $this->termPool->clear();

        try {
            $variants = $this->decompoundUncachedTerm($term);
        } finally {
            // Intermediate substrings are useful only while recursively decomposing this complete token.
            $this->termPool->clear();
        }

        $this->cacheResult($term, $variants);

        return $variants;
    }

    public function clearResultCache(): void
    {
        $this->resultCache = [];
        $this->resultCacheSize = 0;
        $this->termPool->clear();
    }

    /**
     * @return array<string>
     */
    private function decompoundUncachedTerm(string $term): array
    {
        $termInstance = $this->termPool->term($term);
        if ($termInstance->length <= $this->configuration->getMinimumDecompositionTermLength()) {
            return [];
        }

        $variants = $this->split($termInstance);

        // Keep a stable order
        sort($variants, SORT_STRING);

        return $variants;
    }

    /**
     * @param array<string> $variants
     */
    private function cacheResult(string $term, array $variants): void
    {
        if (0 === $this->maximumResultCacheEntries) {
            return;
        }

        // PHP arrays preserve insertion order, so moving hits to the end provides LRU without separate tracking.
        if ($this->resultCacheSize >= $this->maximumResultCacheEntries) {
            $first = array_key_first($this->resultCache);
            if (null !== $first) {
                unset($this->resultCache[$first]);
                --$this->resultCacheSize;
            }
        }

        $this->resultCache[$term] = $variants;
        ++$this->resultCacheSize;
    }

    /**
     * Collect unique leaf terms for given term.
     * Returns false if the term cannot be fully decomposed into dictionary-valid (or allow listed) leaves.
     *
     * @param array<string, array<Term>|false> $leafCache
     *
     * @return array<Term>|false
     */
    private function collectLeafTerms(Term $term, array &$leafCache): array|false
    {
        if (isset($leafCache[$term->term])) {
            return $leafCache[$term->term];
        }

        $hasCandidate = false;
        $bestPenalty = null;
        $bestTerms = [];

        foreach ($this->splitCandidates($term) as $candidate) {
            $hasCandidate = true;
            $left = $candidate->left;
            $right = $candidate->right;

            [$leftLeaves, $leftPenalty] = $this->collectLeavesOrSelf($left, $leafCache);
            [$rightLeaves, $rightPenalty] = $this->collectLeavesOrSelf($right, $leafCache);
            $penalty = $leftPenalty + $rightPenalty + $candidate->penalty;

            if (null !== $bestPenalty && $penalty > $bestPenalty) {
                continue; // This is worse, ignore
            }

            if (null === $bestPenalty || $penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestTerms = []; // We found a new best: remove the ones found so far
            }

            foreach ($this->candidateTerms($candidate, $leftLeaves, $rightLeaves) as $leafTerm) {
                $bestTerms[$leafTerm->term] = $leafTerm;
            }
        }

        if (!$hasCandidate) {
            return $leafCache[$term->term] = ($term->isValid ? [$term] : false);
        }

        if ([] === $bestTerms) {
            return $leafCache[$term->term] = false;
        }

        return $leafCache[$term->term] = array_values($bestTerms);
    }

    /**
     * @param array<Term> $leftLeaves
     * @param array<Term> $rightLeaves
     *
     * @return array<string, Term>
     */
    private function candidateTerms(BoundaryCandidate $candidate, array $leftLeaves, array $rightLeaves): array
    {
        $terms = [];

        foreach (array_merge($leftLeaves, $rightLeaves) as $leafTerm) {
            $terms[$leafTerm->term] = $leafTerm;
        }

        // If configured, keep intermediate dictionary-valid terms that are part of the chosen decomposition tree.
        if ($this->includeIntermediateTerms) {
            $terms[$candidate->left->term] = $candidate->left;
            $terms[$candidate->right->term] = $candidate->right;
        }

        return $terms;
    }

    /**
     * @param array<string, array<Term>|false> $leafCache
     *
     * @return array{0:array<Term>, 1:int}|false
     */
    private function collectLeafTermsWithPenalty(Term $term, array &$leafCache): array|false
    {
        $leaves = $this->collectLeafTerms($term, $leafCache);
        if (false === $leaves) {
            return false;
        }

        // Penalty is computed by caller; leaf-only result has zero internal penalty
        return [$leaves, 0];
    }

    /**
     * @param array<string, array<Term>|false> $leafCache
     *
     * @return array{0:array<Term>, 1:int} A tuple of (leaf terms, penalty)
     */
    private function collectLeavesOrSelf(Term $term, array &$leafCache): array
    {
        $result = $this->collectLeafTermsWithPenalty($term, $leafCache);

        if (false === $result) {
            // Only fallback to the term itself if it is valid. Otherwise, make it very costly.
            return $term->isValid ? [[$term], 0] : [[], self::COSTLY_PENALTY];
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function split(Term $term): array
    {
        $leafCache = [];
        $result = [];
        $leaves = $this->collectLeafTerms($term, $leafCache);

        if (false === $leaves) {
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

        for ($i = 1; $i <= $term->length - 1; ++$i) {
            yield from $this->configuration->boundaryCandidates(
                new BoundaryContext(
                    $term,
                    $i,
                    $this->termPool->term(mb_substr($term->term, 0, $i)),
                    $this->termPool->term(mb_substr($term->term, $i)),
                ),
            );
        }
    }
}
