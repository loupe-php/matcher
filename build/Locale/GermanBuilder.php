<?php

declare(strict_types=1);

namespace Loupe\Matcher\Build\Locale;

use Loupe\Matcher\Build\DictionaryBuilder\AbstractKaikkiDictionaryBuilder;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German\GermanNormalizer;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class GermanBuilder extends AbstractKaikkiDictionaryBuilder
{
    private const DISALLOW_LIST = [
        'date', // There is no compound word with date. This would split "datenbank" into "date", "daten" and "bank"
        'tuck',
        'stag',
        'klass',
        'rege',
    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('de');
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
        if (!preg_match('/^[A-ZÄÖÜa-zäöüß]{' . German::MIN_DECOMPOSITION_TERM_LENGTH . ',}$/u', $term)) {
            return false;
        }

        // We do need adjectives too otherwise stuff like "Hochhaus" would not work [adj + noun]
        // We also need names, otherwise "Donaudampfschiff" would not work [name + noun + noun]
        if (!$this->isAllowedPos($json, ['noun', 'adj', 'name'])) {
            return false;
        }

        // This reduces the dictionary by multiple 100k terms. Try not removing this unless there's a very
        // good reason (or find out what exactly we need of those)
        if ($this->hasTag($json, 'form-of')) {
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

        return true;
    }

    protected function getDumpUrl(): string
    {
        return 'https://kaikki.org/dictionary/downloads/de/de-extract.jsonl.gz';
    }

    protected function getNormalizer(): NormalizerInterface
    {
        return new GermanNormalizer(new Normalizer());
    }
}
