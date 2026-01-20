<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Command;

use Loupe\Matcher\Build\DictionaryBuilderInterface;
use Loupe\Matcher\Build\Locale\Dutch;
use Loupe\Matcher\Build\Locale\English;
use Loupe\Matcher\Build\Locale\German;
use Loupe\Matcher\Locale;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'update-dictionaries', description: 'Updates all dictionaries')]
class UpdateDictionariesCommand
{
    public function __invoke(SymfonyStyle $io, #[Argument] string|null $locale = null): int
    {
        $builders = [
            new German(),
            new Dutch(),
            new English(),
        ];

        foreach ($builders as $builder) {
            if (!$builder instanceof DictionaryBuilderInterface) {
                $io->error('All builders must implement the DictionaryBuilderInterface!');
                return Command::FAILURE;
            }

            if ($locale !== null && !$builder->getLocale()->matches(Locale::fromString($locale))) {
                continue;
            }

            $this->info($io, $builder->getLocale(), 'Building directory now.');
            $builder->buildDirectory($io, __DIR__ . '/../../dictionaries/' . $builder->getLocale()->toString());
        }

        return Command::SUCCESS;
    }

    private function info(SymfonyStyle $io, Locale $locale, string $info): void
    {
        $io->info(\sprintf('[Locale %s]: %s', $locale, $info));
    }
}
