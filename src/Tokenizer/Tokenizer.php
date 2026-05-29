<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tokenizer;

use IntlChar;
use Loupe\Matcher\Locale;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\English;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\German;
use Loupe\Matcher\Tokenizer\LocaleConfiguration\LocaleConfigurationInterface;
use Loupe\Matcher\Tokenizer\Normalizer\Normalizer;
use Loupe\Matcher\Tokenizer\Normalizer\NormalizerInterface;

class Tokenizer implements TokenizerInterface
{
    public const VERSION = '0.3.0'; // Increase this whenever the logic changes so it gives e.g. Loupe the opportunity to detect when a reindex is needed

    private \IntlRuleBasedBreakIterator $breakIterator;

    private NormalizerInterface $normalizer;

    public function __construct(
        private ?LocaleConfigurationInterface $localeConfiguration = null
    ) {
        $this->breakIterator = \IntlRuleBasedBreakIterator::createWordInstance($this->localeConfiguration?->getLocale()->toString()); // @phpstan-ignore-line - null is allowed
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
            'en' => new English(),
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

    public function tokenize(string $string, bool $withVariants = true, ?int $maxTokens = null): TokenCollection
    {
        $this->breakIterator->setText($string);

        /** @var Token[] $tokenList */
        $tokenList = [];
        $id = 0;
        $position = 0;
        $originalPosition = 0;
        $phrase = false;
        $negated = false;
        $whitespace = true;

        $allAscii = !preg_match('/[^\x00-\x7F]/', $string);

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
            $word = $status >= \IntlBreakIterator::WORD_NONE_LIMIT;
            $whitespace = false;

            // Fast path for pure-ascii tokens: skips normalization and folding
            $isAscii = $allAscii || !preg_match('/[^\x00-\x7F]/', $term);
            $originalLength = $isAscii ? \strlen($term) : mb_strlen($term, 'UTF-8');

            if (!$word) {
                // Non-word path: set whitespace flag for negation/quote logic, skip term work
                $whitespace = $term === ' ' || IntlChar::isspace($term);
                $position += $originalLength;
                $originalPosition += $originalLength;
                continue;
            }

            // $id is incremented per kept token, so it doubles as count
            if ($maxTokens !== null && $id >= $maxTokens) {
                break;
            }

            if ($isAscii) {
                // Fast path: ascii tokens can never be folded and length doesn't change
                $term = strtolower($term);
                $termLength = $originalLength;
                $wasFolded = false;
            } else {
                $originalTerm = $term;
                $term = $this->normalizer->normalize($term);
                $term = mb_strtolower($term, 'UTF-8');
                $wasFolded = mb_strtolower($originalTerm, 'UTF-8') !== $term;
                $termLength = !preg_match('/[^\x00-\x7F]/', $term) ? \strlen($term) : mb_strlen($term, 'UTF-8');
            }

            $token = new Token(
                $id++,
                $term,
                $position,
                $phrase,
                $negated,
                $wasFolded,
                $originalPosition,
                $originalLength,
                $termLength,
            );

            if ($withVariants && $this->localeConfiguration !== null) {
                $token = $this->localeConfiguration->enhanceToken($token);
            }

            $tokenList[] = $token;
            $position += $termLength;
            $originalPosition += $originalLength;
        }

        return new TokenCollection($tokenList);
    }
}
