<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer\Decompounder\Dictionary;

use Loupe\Matcher\Locale;

class FastSetDictionary extends AbstractFastSetDictionary
{
    public static function create(Locale $locale, string $directory): self
    {
        $self = new self($locale);
        $self->loadFromDirectory($directory);

        return $self;
    }
}
