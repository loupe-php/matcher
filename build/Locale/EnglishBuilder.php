<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilder\AbstractKaikkiDictionaryBuilder;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\English;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class EnglishBuilder extends AbstractKaikkiDictionaryBuilder
{
    private const DISALLOW_LIST = [
        'ing',
        'not',
        'eld',
        'shi',
        'ting', // apparently the sound made when a small bell is struck, that makes no sense to decompose and it makes for bad splits as a lot of "ing" words end on "ting".
        'der', // interjection
        'kee', // alternative of "cow"
        'sch', // abbreviation of "school"
        'ser', // old form of "sir"
        'und', // old for "wave", bad for decomposing "ground" and similar
    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    protected function allowTermPostNormalize(string $term, array $json): bool
    {
        if (\in_array($term, self::DISALLOW_LIST, true)) {
            return false;
        }

        return true;
    }

    protected function allowTermPreNormalize(string $term, array $json): bool
    {
        // This already filters out anything that does e.g. start with capital letters
        if (!preg_match('/^[a-z]{' . English::MIN_DECOMPOSITION_TERM_LENGTH . ',}$/u', $term)) {
            return false;
        }

        if (!$this->isAllowedPos($json, ['noun', 'adj'])) {
            return false;
        }

        if ($this->isClipped($json)) {
            return false;
        }

        if ($this->isSlang($json)) {
            return false;
        }

        if ($this->hasCommonFilterTag($json)) {
            return false;
        }

        return true;
    }

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/English/kaikki.org-dictionary-English.jsonl';
    }

    protected function getNormalizer(): NormalizerInterface
    {
        return new Normalizer();
    }
}
