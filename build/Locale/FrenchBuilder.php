<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilder\AbstractKaikkiDictionaryBuilder;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class FrenchBuilder extends AbstractKaikkiDictionaryBuilder
{
    protected const EXTRA_TERMS = ['auto', 'tire', 'tourne'];

    public function getLocale(): Locale
    {
        return Locale::fromString('fr');
    }

    protected function allowTermPostNormalize(string $term, array $json): bool
    {
        // Rare forms produce spurious splits in common words such as littérature and chaussure.
        return 1 === preg_match('/^[a-z]{4,}$/D', $term)
            && !\in_array($term, ['chaus', 'litte'], true);
    }

    protected function allowTermPreNormalize(string $term, array $json): bool
    {
        return 1 === preg_match('/^\p{Ll}+$/uD', $term)
            && $this->isAllowedPos($json, ['noun', 'adj'])
            && !$this->hasCommonFilterTag($json);
    }

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/French/kaikki.org-dictionary-French.jsonl.gz';
    }

    protected function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }
}
