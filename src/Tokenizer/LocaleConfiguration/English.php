<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Configuration;

class English extends AbstractPreconfiguredLocale
{
    public const MIN_DECOMPOSITION_TERM_LENGTH = 4;

    private const ALLOW_LIST = [
        'air' => true,
        'arm' => true,
        'ash' => true,
        'bar' => true,
        'bat' => true,
        'bed' => true,
        'bee' => true,
        'bin' => true,
        'bit' => true,
        'box' => true,
        'boy' => true,
        'bus' => true,
        'car' => true,
        'cat' => true,
        'cow' => true,
        'day' => true,
        'dog' => true,
        'ear' => true,
        'egg' => true,
        'eye' => true,
        'fan' => true,
        'far' => true,
        'fat' => true,
        'fig' => true,
        'fir' => true,
        'fox' => true,
        'fur' => true,
        'gas' => true,
        'gel' => true,
        'gem' => true,
        'gun' => true,
        'hat' => true,
        'hen' => true,
        'hip' => true,
        'ice' => true,
        'ink' => true,
        'jar' => true,
        'jet' => true,
        'key' => true,
        'kid' => true,
        'leg' => true,
        'lip' => true,
        'log' => true,
        'man' => true,
        'map' => true,
        'net' => true,
        'oak' => true,
        'oil' => true,
        'pan' => true,
        'pen' => true,
        'pet' => true,
        'pig' => true,
        'pit' => true,
        'pot' => true,
        'ram' => true,
        'rat' => true,
        'ray' => true,
        'rib' => true,
        'rod' => true,
        'row' => true,
        'rug' => true,
        'sap' => true,
        'sea' => true,
        'sky' => true,
        'sun' => true,
        'tag' => true,
        'tar' => true,
        'tea' => true,
        'tin' => true,
        'tip' => true,
        'toe' => true,
        'ton' => true,
        'top' => true,
        'toy' => true,
        'tub' => true,
        'van' => true,
        'war' => true,
        'web' => true,
        'wax' => true,
        'way' => true,
        'win' => true,
        'zip' => true,
        'zoo' => true,
    ];

    public function getLocale(): Locale
    {
        return Locale::fromString('en');
    }

    protected function getDecompounderConfiguration(): Configuration
    {
        return (new Configuration(
            $this->wrapDictionaryWithInMemoryCacheDictionary($this->getFastSetDictionary()),
            self::MIN_DECOMPOSITION_TERM_LENGTH,
        ))->withAllowList(self::ALLOW_LIST);
    }
}
