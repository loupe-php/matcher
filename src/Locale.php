<?php

declare(strict_types=1);

namespace Loupe\Matcher;

class Locale implements \Stringable
{
    private function __construct(
        private string $locale
    ) {
    }

    public function __toString(): string
    {
        return $this->locale;
    }

    public static function fromString(string $locale): self
    {
        $canonical = \Locale::canonicalize($locale);

        if (!$canonical || \Locale::getDisplayName($canonical, $canonical) === '') {
            throw new \InvalidArgumentException("Invalid locale: {$locale}");
        }

        return new self($canonical);
    }

    public function getPrimaryLanguage(): string
    {
        return (string) \Locale::getPrimaryLanguage($this->locale);
    }

    public function toString(): string
    {
        return $this->locale;
    }
}
