<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Locale;

class Dutch extends AbstractKaikkiDictionary
{
    public function getLocale(): Locale
    {
        return Locale::fromString('nl');
    }

    protected function allowTerm(string $term, array $json): bool
    {
        // At least 3 letters total
        // I don't speak Dutch so this regex could probably get improved
        if (!preg_match('/^[A-Za-zÉéÈèËëÏïÎîÇçÑñÁáÓóÚú]{3,}$/u', $term)) {
            return false;
        }

        // I don't speak Dutch, this is mirroring the German logic, but maybe it needs to be adjusted
        if (!$this->isAllowedPos($json, ['noun', 'adj', 'name'])) {
            return false;
        }

        if ($this->hasTag($json, 'form-of')) {
            return false;
        }

        return true;
    }

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/downloads/nl/nl-extract.jsonl.gz';
    }
}
