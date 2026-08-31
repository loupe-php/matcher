<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder;

use Loupe\Matcher\Tokenizer\Decompounder\TermValidator\TermValidatorInterface;

final class TermPool
{
    /**
     * @var array<string, Term>
     */
    private array $pool = [];

    public function __construct(private readonly TermValidatorInterface $termValidator)
    {
    }

    public function term(string $term): Term
    {
        if (isset($this->pool[$term])) {
            return $this->pool[$term];
        }

        return $this->pool[$term] = new Term($term, mb_strlen($term), $this->termValidator->isValid($term));
    }

    public function clear(): void
    {
        $this->pool = [];
    }
}
