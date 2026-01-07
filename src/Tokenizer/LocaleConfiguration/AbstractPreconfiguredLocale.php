<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\BinaryFileDictionary;
use Loupe\Matcher\Tokenizer\Decompounder\Dictionary\DictionaryInterface;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

abstract class AbstractPreconfiguredLocale implements LocaleConfigurationInterface
{
    private DictionaryInterface $dictionary;

    public function __construct()
    {
        $this->dictionary = BinaryFileDictionary::create(
            $this->getLocale(),
            __DIR__ . '/../../../dictionaries/' . $this->getLocale()->toString()
        );
    }

    public function getDictionary(): DictionaryInterface
    {
        return $this->dictionary;
    }

    public function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }
}
