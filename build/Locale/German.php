<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilder\AbstractKaikkiDictionaryBuilder;
use Loupe\Matcher\Locale;

class German extends AbstractKaikkiDictionaryBuilder
{
    private const EXCLUDE_LIST = [
        'Ges', // "Ges" in German is the note G♭ but there's never a compound word with it
    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('de');
    }

    protected function allowTerm(string $term, array $json): bool
    {
        // At least 3 letters total
        if (!preg_match('/^[A-ZÄÖÜa-zäöüß]{3,}$/u', $term)) {
            return false;
        }

        // We do need adjectives too otherwise stuff like "Hochhaus" would not work [adj + noun]
        // We also need names, otherwise "Donaudampfschiff" would not work [name + noun + noun]
        if (!$this->isAllowedPos($json, ['noun', 'adj', 'name'])) {
            return false;
        }

        // Skip cities because yes, there are compound words like "Parisreise" but it's much more likely
        // that you would write "Paris-Reise" and thus, there's no benefit of having them in the dictionary
        if ($this->hasHypernym($json, 'stadt')) {
            return false;
        }

        // Skip "Gemeinden" because we don't want stuff like "Ell" in here
        if ($this->hasHypernym($json, 'gemeinde')) {
            return false;
        }

        if (\in_array($term, self::EXCLUDE_LIST, true)) {
            return false;
        }

        return true;
    }

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/downloads/de/de-extract.jsonl.gz';
    }
}
