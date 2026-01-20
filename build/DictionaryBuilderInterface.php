<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build;

use Loupe\Matcher\Locale;
use Symfony\Component\Console\Style\SymfonyStyle;

interface DictionaryBuilderInterface
{
    public function buildDirectory(SymfonyStyle $io, string $targetDirectory): void;

    public function getLocale(): Locale;
}
