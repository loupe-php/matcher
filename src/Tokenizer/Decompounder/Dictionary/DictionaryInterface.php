<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;

interface DictionaryInterface
{
    public function getLocale(): Locale;

    public function has(string $term): bool;
}
