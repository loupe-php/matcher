<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Formatting;

use Loupe\Matcher\Formatting\WindowPlanner;
use Loupe\Matcher\Tokenizer\MatchSpan;
use PHPUnit\Framework\TestCase;

class WindowPlannerTest extends TestCase
{
    public function testPrioritizationPrefersDistinctTermsOverRepetition(): void
    {
        $text = $this->getRepetitionVsDiversityText();
        $spans = $this->findSpansForTerms($text, 'delta', 'epsilon', 'zeta', 'eta');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 40,
            maxFragments: 1,
            prioritizeMatches: true,
        );

        $this->assertCount(1, $windows);
        $combined = $this->combinedWindowText($text, $windows);

        // 3 distinct terms (epsilon+zeta+eta) beats 3 repetitions of 1 term (delta×3)
        $this->assertStringContainsString('epsilon', $combined);
        $this->assertStringContainsString('zeta', $combined);
        $this->assertStringContainsString('eta', $combined);
        $this->assertStringNotContainsString('delta', $combined);
    }

    public function testPrioritizedResultIsAlwaysInDocumentOrder(): void
    {
        $text = $this->getDescendingQualityText();
        $spans = $this->findSpansForTerms($text, 'ruby', 'jade', 'opal', 'onyx');

        $this->assertCount(10, $spans);

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            maxFragments: 3,
            prioritizeMatches: true,
        );

        $this->assertCount(3, $windows);
        $combined = $this->combinedWindowText($text, $windows);

        // Weakest cluster (onyx, 1 match) dropped
        $this->assertStringNotContainsString('onyx', $combined);

        // Best 3 returned in document order despite score-based selection
        $this->assertStringContainsInOrder($combined, ['ruby', 'jade', 'opal']);
    }

    public function testReturnsAllWindowsWhenUnlimited(): void
    {
        $text = $this->getWeakMediumDenseText();
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
        );

        $this->assertCount(6, $spans);
        $this->assertCount(3, $windows);
    }

    public function testWithoutPrioritizationTakesFirstN(): void
    {
        $text = $this->getWeakMediumDenseText();
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            maxFragments: 2,
        );

        $this->assertCount(2, $windows);
        $combined = $this->combinedWindowText($text, $windows);

        // First two in document order: weak (alpha) + medium (beta). Dense (gamma) dropped.
        $this->assertStringContainsString('alpha', $combined);
        $this->assertStringContainsString('beta', $combined);
        $this->assertStringNotContainsString('gamma', $combined);
    }

    public function testWithPrioritizationPicksBestN(): void
    {
        // Exact same text as testWithoutPrioritizationTakesFirstN — only the flag changes.
        $text = $this->getWeakMediumDenseText();
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            maxFragments: 2,
            prioritizeMatches: true,
        );

        $this->assertCount(2, $windows);
        $combined = $this->combinedWindowText($text, $windows);

        // Best two by score: dense (gamma, 3 matches) + medium (beta, 2 matches). Weak (alpha) dropped.
        $this->assertStringNotContainsString('alpha', $combined);
        $this->assertStringContainsInOrder($combined, ['beta', 'gamma']);
    }

    public function testWithPrioritizationSingleFragmentPicksBest(): void
    {
        $text = $this->getWeakMediumDenseText();
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            maxFragments: 1,
            prioritizeMatches: true,
        );

        $this->assertCount(1, $windows);
        $combined = $this->combinedWindowText($text, $windows);

        // The dense cluster (gamma, 3 matches) at the end wins
        $this->assertStringContainsString('gamma', $combined);
        $this->assertStringNotContainsString('alpha', $combined);
        $this->assertStringNotContainsString('beta', $combined);
    }

    /**
     * @param string[] $needles
     */
    private function assertStringContainsInOrder(string $haystack, array $needles): void
    {
        $pos = 0;
        foreach ($needles as $needle) {
            $found = mb_strpos($haystack, $needle, $pos, 'UTF-8');
            $this->assertNotFalse($found, "Expected '{$needle}' after position {$pos} in: {$haystack}");
            $pos = $found + mb_strlen($needle, 'UTF-8');
        }
    }

    /**
     * @param \Loupe\Matcher\Tokenizer\Span[] $windows
     */
    private function combinedWindowText(string $text, array $windows): string
    {
        return implode(' | ', array_map(
            fn ($w) => mb_substr($text, $w->getStartPosition(), $w->getLength(), 'UTF-8'),
            $windows,
        ));
    }

    /**
     * @return MatchSpan[]
     */
    private function findSpansForTerms(string $text, string ...$terms): array
    {
        $spans = [];

        foreach ($terms as $term) {
            $len = mb_strlen($term, 'UTF-8');
            $offset = 0;

            while (($pos = mb_strpos($text, $term, $offset, 'UTF-8')) !== false) {
                $spans[] = new MatchSpan($pos, $pos + $len, [mb_strtolower($term, 'UTF-8')]);
                $offset = $pos + $len;
            }
        }

        usort($spans, fn ($a, $b) => $a->getStartPosition() <=> $b->getStartPosition());

        return $spans;
    }

    /**
     * Four clusters of descending quality (best first, worst last).
     * (ruby ×4) ··· (jade ×3) ··· (opal ×2) ··· (onyx ×1)
     */
    private function getDescendingQualityText(): string
    {
        return <<<'TEXT'
            First ruby ruby ruby ruby here with four matches.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            Second jade jade jade here with three matches.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            Third opal opal here with two matches.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            Fourth onyx here with one match.
            TEXT;
    }

    /**
     * Start: delta delta delta (3 matches, 1 distinct term)
     * End:   epsilon zeta eta  (3 matches, 3 distinct terms)
     */
    private function getRepetitionVsDiversityText(): string
    {
        return <<<'TEXT'
            Here delta delta delta repeat the same term three times at the start.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end epsilon zeta eta appear together with full term diversity.
            TEXT;
    }

    /**
     * Densest cluster at the end so document-order and score-based selection diverge.
     * (weak: alpha ×1) ··· (medium: beta ×2) ··· (dense: gamma ×3)
     */
    private function getWeakMediumDenseText(): string
    {
        return <<<'TEXT'
            A lone alpha sits at the very start of this document.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            In the middle beta and beta appear as a pair close together.
            More filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end gamma gamma gamma cluster tightly together as the densest group.
            TEXT;
    }
}
