<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

interface WritableDictionaryInterface extends DictionaryInterface
{
    public function add(string $term): void;

    public function write(string $pathToDirectory): void;
}
