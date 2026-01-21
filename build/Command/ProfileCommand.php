<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Command;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Tokenizer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'profile', description: 'Outputs the memory required by loading a given pre-built locale dictionary.')]
class ProfileCommand
{
    public function __invoke(SymfonyStyle $io, #[Argument] string $locale): int
    {
        $memoryBefore = memory_get_usage(true);
        $tokenizer = Tokenizer::createFromPreconfiguredLocaleConfiguration(Locale::fromString($locale));
        $memoryAfter = memory_get_usage(true);

        $io->success(\sprintf('Loading this dictionary required %.2F MiB.', ($memoryAfter - $memoryBefore) / 1024 / 1024));

        return Command::SUCCESS;
    }
}
