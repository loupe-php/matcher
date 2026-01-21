<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilder\AbstractKaikkiDictionaryBuilder;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\English;

class EnglishBuilder extends AbstractKaikkiDictionaryBuilder
{
    private const DISALLOW_LIST = [
        'ting', // apparently the sound made when a small bell is struck, that makes no sense to decompose and it makes for bad splits as a lot of "ing" words end on "ting".
    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('en');
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

        if ($this->hasTag($json, 'form-of')) {
            return false;
        }

        return true;
    }

    protected function allowTermPostNormalize(string $term, array $json): bool
    {
        if (\in_array($term, self::DISALLOW_LIST, true)) {
            return false;
        }

        return true;
    }

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/English/kaikki.org-dictionary-English.jsonl';
    }
}
