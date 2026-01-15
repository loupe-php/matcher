<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\Decompounder\Decompounder;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\Dutch;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\LocaleConfigurationInterface;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class Tokenizer implements TokenizerInterface
{
    public const VERSION = '0.3.0'; // Increase this whenever the logic changes so it gives e.g. Loupe the opportunity to detect when a reindex is needed

    private \IntlRuleBasedBreakIterator $breakIterator;

    private ?Decompounder $decompounder = null;

    private NormalizerInterface $normalizer;

    public function __construct(
        private ?LocaleConfigurationInterface $localeConfiguration = null
    ) {
        $this->breakIterator = \IntlRuleBasedBreakIterator::createWordInstance($this->localeConfiguration?->getLocale()->toString()); // @phpstan-ignore-line - null is allowed
        $this->decompounder = $this->localeConfiguration === null ? null : new Decompounder($this->localeConfiguration);
        $this->normalizer = $this->localeConfiguration?->getNormalizer() ?? new Normalizer();
    }

    public static function createFromPreconfiguredLocaleConfiguration(Locale $locale): self
    {
        return new self(self::getPreconfiguredLocaleConfigurationForLocale($locale));
    }

    public static function getPreconfiguredLocaleConfigurationForLocale(Locale $locale): ?LocaleConfigurationInterface
    {
        return match ($locale->getPrimaryLanguage()) {
            'de' => new German(),
            'nl' => new Dutch(),
            default => null,
        };
    }

    public function matches(Token $token, TokenCollection $tokens): bool
    {
        foreach ($tokens->all() as $checkToken) {
            foreach ($checkToken->allTerms() as $checkTerm) {
                if (\in_array($checkTerm, $token->allTerms(), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function tokenize(string $string, ?int $maxTokens = null): TokenCollection
    {
        $this->breakIterator->setText($string);

        $tokens = new TokenCollection();
        $id = 0;
        $position = 0;
        $phrase = false;
        $negated = false;
        $whitespace = true;

        foreach ($this->breakIterator->getPartsIterator() as $term) {
            // Set negation if the previous token was not a word and we're not in a phrase
            if (!$phrase && $whitespace) {
                $negated = false;
                if ($term === '-') {
                    $negated = true;
                }
            }

            // Toggle phrases between quotes
            if ($term === '"') {
                $phrase = !$phrase;
                if (!$phrase) {
                    $negated = false;
                }
            }

            $status = $this->breakIterator->getRuleStatus();
            $word = $this->isWord($status);
            $whitespace = $this->isWhitespace($status, $term);

            if (!$word) {
                $position += mb_strlen($term, 'UTF-8');
                continue;
            }

            if ($maxTokens !== null && $tokens->count() >= $maxTokens) {
                break;
            }

            $term = $this->normalizer->normalize($term);

            $token = new Token(
                $id++,
                $term,
                $position,
                $phrase,
                $negated,
            );

            $token = $token->withAddedVariants($this->decompounder?->decompoundTerm($term) ?? []);

            $position += $token->getLength();
            $tokens->add($token);
        }

        return $tokens;
    }

    private function isWhitespace(?int $status, string $token): bool
    {
        return ($status === null || ($status >= \IntlBreakIterator::WORD_NONE && $status < \IntlBreakIterator::WORD_NONE_LIMIT)) && trim($token) === '';
    }

    private function isWord(?int $status): bool
    {
        return $status >= \IntlBreakIterator::WORD_NONE_LIMIT;
    }
}
