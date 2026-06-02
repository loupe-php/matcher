<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\DictionaryBuilder;

use Loupe\Matcher\Build\DictionaryBuilderInterface;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\FastSetDictionary;
use Symfony\Component\Console\Style\SymfonyStyle;
use Toflar\FastSet\SetBuilder;

abstract class AbstractFastSetDictionaryBuilder implements DictionaryBuilderInterface
{
    public function buildDirectory(SymfonyStyle $io, string $targetDirectory, bool $debug): void
    {
        $terms = array_unique($this->doBuildTerms($io));

        if ($debug) {
            file_put_contents($targetDirectory . '/debug.txt', implode("\n", $terms));
        }

        foreach (glob($targetDirectory . '/*.bin') as $file) {
            unlink($file);
        }

        SetBuilder::buildFromArray($terms, $targetDirectory . '/' . FastSetDictionary::DICTIONARY_FILE_NAME);
    }

    /**
     * @return array<string>
     */
    abstract protected function doBuildTerms(SymfonyStyle $io): array;
}
