<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration\German;

use Loupe\Matcher\Tokenizer\Decompounder\BoundaryCandidate;
use Loupe\Matcher\Tokenizer\Decompounder\BoundaryContext;
use Loupe\Matcher\Tokenizer\Decompounder\DefaultConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;

class GermanDecompounderConfiguration extends DefaultConfiguration
{
    private const INTERFIXES = [
        's' => 1,
        'es' => 2,
        'n' => 1,
        'en' => 2,
        'er' => 2,
        'e' => 1,
    ];

    public function __construct(
        private TermPool $termPool,
        private int $minimumDecompositionTermLength
    ) {
        parent::__construct($termPool, $minimumDecompositionTermLength, self::INTERFIXES);
    }

    public function boundaryCandidates(BoundaryContext $boundaryContext): iterable
    {
        yield from parent::boundaryCandidates($boundaryContext);

        $left = $boundaryContext->left;
        $right = $boundaryContext->right;

        // If the right side is valid but the left is not, it might be the typical German case for
        // e.g. "Schulhof" which is "Schule" and "Hof". So we try that and if it's a valid term, we
        // add that as a candidate as well with a penalty of 1
        if ($right->isValid && !$left->isValid) {

            // Performance optimization:
            // The ones that already end on "e" are certainly no candidates
            if (str_ends_with($left->term, 'e')) {
                return;
            }

            $left = $this->termPool->term($left->term . 'e');
            if (!$left->isValid) {
                return;
            }

            yield new BoundaryCandidate($left, $right, 1);
        }
    }
}
