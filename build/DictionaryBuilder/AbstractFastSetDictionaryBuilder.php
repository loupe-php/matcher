<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\DictionaryBuilder;

use Loupe\Matcher\Build\DictionaryBuilderInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
use Symfony\Component\Console\Style\SymfonyStyle;
use Toflar\FastSet\SetBuilder;

abstract class AbstractFastSetDictionaryBuilder implements DictionaryBuilderInterface
{
    public function buildDirectory(SymfonyStyle $io, string $targetDirectory): void
    {
        SetBuilder::buildFromArray($this->doBuildTerms($io), $targetDirectory . '/' . FastSetDictionary::DICTIONARY_FILE_NAME);
    }

    /**
     * @return array<string>
     */
    abstract protected function doBuildTerms(SymfonyStyle $io): array;
}
