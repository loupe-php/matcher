<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

class Token
{
    private int $length;

    private int $originalLength;

    private int $originalStartPosition;

    /**
     * @var array<string>
     */
    private array $variants = [];

    public function __construct(
        private int $id,
        private string $term,
        private int $startPosition,
        private bool $isPartOfPhrase,
        private bool $isNegated,
        private bool $wasFolded = false,
        ?int $originalStartPosition = null,
        ?int $originalLength = null,
    ) {
        $this->length = mb_strlen($this->term, 'UTF-8');
        $this->originalLength = $originalLength ?? $this->length;
        $this->originalStartPosition = $originalStartPosition ?? $this->startPosition;
    }

    /**
     * Return an array with a single element, the token itself.
     * Useful for iterating over a TokenCollection with tokens and phrases.
     *
     * @return array<Token>
     */
    public function all(): array
    {
        return [$this];
    }

    /**
     * @return array<string>
     */
    public function allTerms(): array
    {
        return array_unique(array_merge([$this->getTerm()], $this->getVariants()));
    }

    public function getEndPosition(): int
    {
        return $this->startPosition + $this->length;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getOriginalEndPosition(): int
    {
        return $this->originalStartPosition + $this->originalLength;
    }

    public function getOriginalLength(): int
    {
        return $this->originalLength;
    }

    public function getOriginalStartPosition(): int
    {
        return $this->originalStartPosition;
    }

    public function getStartPosition(): int
    {
        return $this->startPosition;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    /**
     * @return array<string>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function isNegated(): bool
    {
        return $this->isNegated;
    }

    /**
     * @param array<string> $haystack
     */
    public function isOneOf(array $haystack): bool
    {
        if ($haystack === []) {
            return false;
        }

        foreach ($this->allTerms() as $term) {
            foreach ($haystack as $needle) {
                if ($term === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isPartOfPhrase(): bool
    {
        return $this->isPartOfPhrase;
    }

    /**
     * Whether tokenization changed the token beyond case normalization.
     *
     * Case-only changes such as `Thomas` -> `thomas` return false.
     * Folding changes such as `Müller` -> `muller` or `Straße` -> `strasse` return true.
     */
    public function wasFolded(): bool
    {
        return $this->wasFolded;
    }

    /**
     * @param array<string> $variants
     */
    public function withVariants(array $variants): self
    {
        $clone = clone $this;
        $clone->variants = $variants;
        return $clone;
    }
}
