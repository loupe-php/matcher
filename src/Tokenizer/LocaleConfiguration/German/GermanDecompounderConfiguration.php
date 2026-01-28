<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration\German;

use Loupe\Matcher\Tokenizer\Decompounder\BoundaryCandidate;
use Loupe\Matcher\Tokenizer\Decompounder\BoundaryContext;
use Loupe\Matcher\Tokenizer\Decompounder\DefaultConfiguration;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;

class GermanDecompounderConfiguration extends DefaultConfiguration
{
    public const MIN_DECOMPOSITION_TERM_LENGTH = 4;

    private const ALLOW_LIST = [
        'amt' => true,
        'art' => true,
        'bad' => true,
        'bau' => true,
        'bus' => true,
        'ehe' => true,
        'eis' => true,
        'erz' => true,
        'fee' => true,
        'gut' => true,
        'hof' => true,
        'hut' => true,
        'klo' => true,
        'mut' => true,
        'rad' => true,
        'ruf' => true,
        'see' => true,
        'tag' => true,
        'tee' => true,
        'tal' => true,
        'tor' => true,
        'typ' => true,
        'weg' => true,
        'zug' => true,
        'ei' => true,
    ];

    private const INTERFIXES = ['s', 'es', 'n', 'en', 'er', 'e'];

    public function __construct(DictionaryInterface $dictionary)
    {
        parent::__construct(
            $dictionary,
            self::MIN_DECOMPOSITION_TERM_LENGTH,
            self::ALLOW_LIST,
            self::INTERFIXES,
        );

    }

    public function boundaryCandidates(BoundaryContext $boundaryContext): iterable
    {
        yield from parent::boundaryCandidates($boundaryContext);

        $left = $boundaryContext->left;
        $right = $boundaryContext->right;

        // If the right side is valid but the left is not, it might be the typical German case for
        // e.g. "Schulhof" which is "Schule" and "Hof". So we try that and if it's a valid term, we
        // add that as a candidate as well with a penalty of 1
        if ($this->isValidTerm($right) && !$this->isValidTerm($left)) {
            $left .= 'e';
            if ($this->isValidTerm($left)) {
                yield new BoundaryCandidate($left, $right, 1);
            }
        }
    }
}
