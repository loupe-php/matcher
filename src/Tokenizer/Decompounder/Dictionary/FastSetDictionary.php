<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Toflar\FastSet\FastSet;

class FastSetDictionary implements DictionaryInterface
{
    public const DICTIONARY_FILE_NAME = 'dictionary.gz';

    private FastSet $fastSet;

    public function __construct(string $directory)
    {
        $this->fastSet = new FastSet($directory);

        try {
            $this->fastSet->initialize();
        } catch (\Throwable) {
            $this->fastSet->build($directory . '/' . self::DICTIONARY_FILE_NAME);
            $this->fastSet->initialize();
        }
    }

    public function has(string $term): bool
    {
        return $this->fastSet->has($term);
    }
}
