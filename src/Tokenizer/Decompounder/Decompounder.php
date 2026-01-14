<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\LocaleConfiguration\LocaleConfigurationInterface;

class Decompounder
{
    public function __construct(
        private LocaleConfigurationInterface $localeConfiguration
    ) {
    }

    /**
     * Returns all decomposition split variants (meaning no part can be further decomposed),
     * and never returns the term itself.
     * @return array<string>
     */
    public function decompoundTerm(string $term): array
    {
        if (mb_strlen($term) <= $this->localeConfiguration->getMinimumDecompositionTermLength()) {
            return [];
        }

        $variants = $this->split($term);

        // Keep a stable order
        sort($variants, SORT_STRING);

        return $variants;
    }

    /**
     * Collect unique leaf terms for $term, but ONLY from COMPLETE decomposition paths.
     * Returns null if $term cannot be fully decomposed into dictionary-valid leaves.
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

        // Base case: dictionary-valid AND not further decomposable => leaf itself
        if ($this->dictionaryHas($term) && !$this->isDecomposable($term, $decomposableCache)) {
            return $leafCache[$term] = [$term];
        }

        $minLength = $this->localeConfiguration->getMinimumDecompositionTermLength();
        $leafTerms = [];

        foreach ($this->splitCandidates($term, $minLength) as [$left, $right]) {
            // splitCandidates guarantees $left is dictionary-valid.
            // Determine left contribution:
            // - if left is a leaf: [left]
            // - if left is decomposable: its leaf terms, but only if it can be fully decomposed
            if (!$this->isDecomposable($left, $decomposableCache)) {
                $leftLeaves = [$left];
            } else {
                $leftLeaves = $this->collectLeafTerms($left, $leafCache, $decomposableCache);
                if ($leftLeaves === null) {
                    continue; // left can't be fully decomposed => dead end branch
                }
            }

            // Right must be fully decomposable too
            $rightLeaves = $this->collectLeafTerms($right, $leafCache, $decomposableCache);
            if ($rightLeaves === null) {
                continue; // dead end branch (partial split)
            }

            // This split represents a COMPLETE decomposition path => merge leaves
            foreach ($leftLeaves as $leafTerm) {
                $leafTerms[$leafTerm] = true;
            }
            foreach ($rightLeaves as $leafTerm) {
                $leafTerms[$leafTerm] = true;
            }
        }

        // No complete decomposition path found => not fully decomposable
        if ($leafTerms === []) {
            return $leafCache[$term] = null;
        }

        return $leafCache[$term] = array_keys($leafTerms);
    }

    private function dictionaryHas(string $term): bool
    {
        return $this->localeConfiguration->getDictionary()->has($term);
    }

    /**
     * True if $term can be split into two dictionary-valid terms,
     * either directly (left|right) or by removing an interfix at the boundary (left|interfix|right).
     * @param array<string, bool> $decomposableCache
     */
    private function isDecomposable(string $term, array &$decomposableCache): bool
    {
        if (isset($decomposableCache[$term])) {
            return $decomposableCache[$term];
        }

        foreach ($this->splitCandidates($term, $this->localeConfiguration->getMinimumDecompositionTermLength()) as [$left, $right]) {
            if ($this->dictionaryHas($right)) {
                return $decomposableCache[$term] = true;
            }
        }

        return $decomposableCache[$term] = false;
    }

    /**
     * Return unique "leaf" dictionary terms that appear in any COMPLETE decomposition of $term.
     * Leaf = dictionary-valid term that cannot be further decomposed (directly or via interfix removal).
     *
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
     * Yield all candidate (left, right) pairs where:
     * - left is dictionary-valid
     * - right is the remainder, either direct or with an interfix removed
     *
     * @return iterable<array{0:string,1:string}>
     */
    private function splitCandidates(string $term, int $minLength): iterable
    {
        $interfixes = $this->localeConfiguration->getInterfixes();

        $termLength = mb_strlen($term);

        for ($i = $minLength; $i <= $termLength - $minLength; $i++) {
            $left = mb_substr($term, 0, $i);
            if (!$this->dictionaryHas($left)) {
                continue;
            }

            // Direct boundary: left | right
            $right = mb_substr($term, $i, $termLength - $i);
            yield [$left, $right];

            // Interfix boundary: left | interfix | rightAfterInterfix
            foreach ($interfixes as $interfix) {
                $interfixLength = mb_strlen($interfix);

                if ($i + $interfixLength > $termLength - $minLength) {
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

                yield [$left, $rightAfterInterfix];
            }
        }
    }
}
