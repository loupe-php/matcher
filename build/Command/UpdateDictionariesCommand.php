<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Command;

use Loupe\Matcher\Build\DictionaryBuilderInterface;
use Loupe\Matcher\Build\Locale\Dutch;
use Loupe\Matcher\Build\Locale\German;
use Loupe\Matcher\Locale;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'loupe-matcher:update_dictionaries', description: 'Updates all dictionaries')]
class UpdateDictionariesCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $builders = [
            new German(),
            new Dutch(),
        ];

        foreach ($builders as $builder) {
            if (!$builder instanceof DictionaryBuilderInterface) {
                $io->error('All builders must implement the DictionaryBuilderInterface!');
                return Command::FAILURE;
            }

            $this->info($io, $builder->getLocale(), 'Building directory now.');
            $directory = $builder->buildDirectory($io);
            $this->info($io, $builder->getLocale(), 'Done building directory.');
            $this->info($io, $builder->getLocale(), 'Writing to disk now.');
            $directory->write(__DIR__ . '/../../dictionaries/' . $directory->getLocale()->toString());
        }

        return Command::SUCCESS;
    }

    private function info(SymfonyStyle $io, Locale $locale, string $info): void
    {
        $io->info(\sprintf('[Locale %s]: %s', $locale, $info));
    }
}
