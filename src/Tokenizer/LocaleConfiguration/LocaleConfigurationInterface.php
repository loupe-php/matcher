<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

interface LocaleConfigurationInterface
{
    public function getDictionary(): DictionaryInterface;

    /**
     * @return array<string>
     */
    public function getInterfixes(): array;

    public function getLocale(): Locale;

    public function getMinimumDecompositionTermLength(): int;

    public function getNormalizer(): NormalizerInterface;
}
