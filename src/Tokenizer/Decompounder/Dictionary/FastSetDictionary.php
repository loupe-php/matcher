<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;
use Toflar\FastSet\FastSet;

class FastSetDictionary implements DictionaryInterface
{
    private const FILE_NAME_TERMS = 'terms';

    private FastSet $fastSet;

    public function __construct(
        protected Locale $locale,
        string $directory
    ) {
        $this->fastSet = new FastSet($directory);

        try {
            $this->fastSet->initialize();
        } catch (\Throwable) {
            $this->fastSet->build($directory . '/' . self::FILE_NAME_TERMS);
            $this->fastSet->initialize();
        }
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function has(string $term): bool
    {
        return $this->fastSet->has($term);
    }
}
