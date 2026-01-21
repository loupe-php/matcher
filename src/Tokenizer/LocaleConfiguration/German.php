<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Configuration;

class German extends AbstractPreconfiguredLocale
{
    public const MIN_DECOMPOSITION_TERM_LENGTH = 4;

    private const ALLOW_LIST = [
        'amt' => true,
        'art' => true,
        'bad' => true,
        'bau' => true,
        'bus' => true,
        'ehe' => true,
        'eis' => true,
        'erz' => true,
        'fee' => true,
        'gut' => true,
        'hof' => true,
        'hut' => true,
        'klo' => true,
        'mut' => true,
        'rad' => true,
        'ruf' => true,
        'see' => true,
        'tag' => true,
        'tal' => true,
        'tor' => true,
        'typ' => true,
        'weg' => true,
        'zug' => true,
        'ei' => true,
    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('de');
    }

    protected function getDecompounderConfiguration(): Configuration
    {
        return (new Configuration(
            $this->getDictionary(),
            self::MIN_DECOMPOSITION_TERM_LENGTH
        ))
            ->withInterfixes(['s', 'es', 'n', 'en', 'er', 'e'])
            ->withAllowList(self::ALLOW_LIST)
        ;
    }
}
