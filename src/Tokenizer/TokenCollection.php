<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

use Loupe\Matcher\StopWords\StopWordsInterface;

class TokenCollection implements \Countable
{
    /**
     * @var Token[]
     */
    private array $tokens = [];

    /**
     * @param Token[] $tokens
     */
    public function __construct(array $tokens = [])
    {
        foreach ($tokens as $token) {
            $this->add($token);
        }
    }

    public function add(Token $token): self
    {
        $this->tokens[] = $token;

        return $this;
    }

    /**
     * @return Token[]
     */
    public function all(): array
    {
        return $this->tokens;
    }

    /**
     * @return Token[]
     */
    public function allNegated(): array
    {
        return array_filter($this->tokens, fn (Token $token) => $token->isNegated());
    }

    /**
     * @return array<string>
     */
    public function allNegatedTerms(): array
    {
        $tokens = [];

        foreach ($this->allNegated() as $token) {
            $tokens[] = $token->getTerm();
        }

        return array_unique($tokens);
    }

    /**
     * @return array<string>
     */
    public function allNegatedTermsWithVariants(): array
    {
        $tokens = [];

        foreach ($this->allNegated() as $token) {
            $tokens = array_merge($tokens, $token->allTerms());
        }

        return array_unique($tokens);
    }

    /**
     * @return array<string>
     */
    public function allTerms(): array
    {
        $tokens = [];

        foreach ($this->all() as $token) {
            $tokens[] = $token->getTerm();
        }

        return array_unique($tokens);
    }

    /**
     * @return array<string>
     */
    public function allTermsWithVariants(): array
    {
        $tokens = [];

        foreach ($this->all() as $token) {
            $tokens = array_merge($tokens, $token->allTerms());
        }

        return array_unique($tokens);
    }

    public function atIndex(int $index): ?Token
    {
        return $this->tokens[$index] ?? null;
    }

    public function contains(Token $token, bool $checkPosition = false): bool
    {
        foreach ($this->all() as $t) {
            if ($t->getTerm() === $token->getTerm()) {
                if (!$checkPosition) {
                    return true;
                }
                if ($t->getStartPosition() === $token->getStartPosition()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function count(): int
    {
        return \count($this->tokens);
    }

    public function empty(): bool
    {
        return $this->tokens === [];
    }

    public function last(): ?Token
    {
        $last = end($this->tokens);
        if ($last instanceof Token) {
            return $last;
        }

        return null;
    }

    /**
     * Return an array of "phrase groups" -- either single tokens or phrases as single objects.
     *
     * @return array<Phrase|Token>
     */
    public function phraseGroups(): array
    {
        $groups = [];
        $phrase = null;

        foreach ($this->tokens as $token) {
            if ($token->isPartOfPhrase()) {
                $phrase = $phrase ?? new Phrase([], $token->isNegated());
                $phrase->add($token);
            } else {
                if ($phrase) {
                    $groups[] = $phrase;
                    $phrase = null;
                }
                $groups[] = $token;
            }
        }

        if ($phrase) {
            $groups[] = $phrase;
        }

        return $groups;
    }

    public function withoutStopWords(StopWordsInterface $stopWords, bool $keepOriginalIfEmpty = false): self
    {
        $reduced = new self();

        foreach ($this->tokens as $token) {
            if (!$stopWords->isStopWord($token)) {
                $reduced->add($token);
            }
        }

        if ($reduced->empty() && $keepOriginalIfEmpty) {
            return $this;
        }

        return $reduced;
    }
}
