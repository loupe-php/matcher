<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\WritableDictionaryInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

interface DictionaryBuilderInterface
{
    public function buildDirectory(SymfonyStyle $io): WritableDictionaryInterface;

    public function getLocale(): Locale;
}
