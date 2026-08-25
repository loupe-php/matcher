<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Toflar\FastSet\FastSet;

class FastSetDictionary implements DictionaryInterface
{
    public const DICTIONARY_FILE_NAME = 'dictionary.gz';

    private readonly FastSet $fastSet;

    public function __construct(string $dictionaryDirectory, string|null $cacheDirectory = null)
    {
        $cacheDirectory ??= $dictionaryDirectory;
        $this->createCacheDirectory($cacheDirectory);
        $this->fastSet = new FastSet($cacheDirectory);

        try {
            $this->fastSet->initialize();
        } catch (\Throwable) {
            $this->fastSet->build($dictionaryDirectory.'/'.self::DICTIONARY_FILE_NAME);
            $this->fastSet->initialize();
        }
    }

    public function has(string $term): bool
    {
        return $this->fastSet->has($term);
    }

    private function createCacheDirectory(string $cacheDirectory): void
    {
        if (is_dir($cacheDirectory)) {
            return;
        }

        // @phpstan-ignore filesystemcall.unsafe (Avoid requiring Symfony Filesystem for one operation.)
        if (!@mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
            throw new \RuntimeException(\sprintf('Cannot create FastSet cache directory "%s".', $cacheDirectory));
        }
    }
}
