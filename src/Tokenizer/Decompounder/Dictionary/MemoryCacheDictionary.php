<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;

class MemoryCacheDictionary implements DictionaryInterface
{
    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    public function __construct(
        private DictionaryInterface $inner
    ) {

    }

    public function getLocale(): Locale
    {
        return $this->inner->getLocale();
    }

    public function has(string $term): bool
    {
        if (isset($this->cache[$term])) {
            return $this->cache[$term];
        }

        return $this->cache[$term] = $this->inner->has($term);
    }
}
