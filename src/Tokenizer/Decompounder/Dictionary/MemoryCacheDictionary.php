<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

class MemoryCacheDictionary implements DictionaryInterface
{
    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    private int $size = 0;

    /**
     * @param int $maxEntries If you pass an integer higher than 0, it will only cache a maximum of that number of entries
     */
    public function __construct(
        private DictionaryInterface $inner,
        private int $maxEntries = 0
    ) {

    }

    public function has(string $term): bool
    {
        if (isset($this->cache[$term])) {
            return $this->cache[$term];
        }

        // Cache size restriction disabled
        if ($this->maxEntries <= 0) {
            return $this->cache[$term] = $this->inner->has($term);
        }

        $result = $this->inner->has($term);

        // If full, evict oldest inserted key (FIFO)
        // I have benched LRU but tracking access performs way worse. It would only make sense if $inner
        // were really slow on lookups
        if ($this->size >= $this->maxEntries) {
            $first = array_key_first($this->cache);
            if ($first !== null) {
                unset($this->cache[$first]);
                $this->size--;
            }
        }

        $this->cache[$term] = $result;
        $this->size++;

        return $result;
    }
}
