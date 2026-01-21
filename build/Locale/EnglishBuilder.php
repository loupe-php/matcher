<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilder\AbstractKaikkiDictionaryBuilder;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\English;

class EnglishBuilder extends AbstractKaikkiDictionaryBuilder
{
    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    protected function allowTerm(string $term, array $json): bool
    {
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

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/English/kaikki.org-dictionary-English.jsonl';
    }
}
