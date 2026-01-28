<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration\German;

use Loupe\Matcher\Tokenizer\Decompounder\BoundaryCandidate;
use Loupe\Matcher\Tokenizer\Decompounder\BoundaryContext;
use Loupe\Matcher\Tokenizer\Decompounder\DefaultConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\TermPool;

class GermanDecompounderConfiguration extends DefaultConfiguration
{
    public const MIN_DECOMPOSITION_TERM_LENGTH = 4;

    private const INTERFIXES = ['s', 'es', 'n', 'en', 'er', 'e'];

    public function __construct(TermPool $termPool)
    {
        parent::__construct($termPool, self::MIN_DECOMPOSITION_TERM_LENGTH, self::INTERFIXES);
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
            $left = $this->getTermPool()->term($left->term . 'e');
            if ($left->isValid) {
                yield new BoundaryCandidate($left, $right, 1);
            }
        }
    }
}
