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

    private int $size = 0;

    public function __construct(
        private readonly TermValidatorInterface $termValidator,
        private readonly int $maxCacheEntries = 0
    ) {

    }

    public function term(string $term): Term
    {
        if (isset($this->pool[$term])) {
            return $this->pool[$term];
        }

        // Cache size restriction disabled
        if ($this->maxCacheEntries <= 0) {
            return $this->pool[$term] = new Term($term, mb_strlen($term), $this->termValidator->isValid($term));
        }

        $termInstance = new Term($term, mb_strlen($term), $this->termValidator->isValid($term));

        // If full, evict oldest inserted key (FIFO)
        // I have benched LRU but tracking access performs way worse.
        if ($this->size >= $this->maxCacheEntries) {
            $first = array_key_first($this->pool);
            if ($first !== null) {
                unset($this->pool[$first]);
                $this->size--;
            }
        }

        $this->pool[$term] = $termInstance;
        $this->size++;

        return $termInstance;
    }
}
