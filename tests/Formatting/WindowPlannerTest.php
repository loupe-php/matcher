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
        $text = <<<'TEXT'
            Here delta delta delta repeat the same term three times at the start.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end epsilon zeta eta appear together with full term diversity.
            TEXT;
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
        $text = <<<'TEXT'
            First ruby ruby ruby ruby here with four matches.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            Second jade jade jade here with three matches.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            Third opal opal here with two matches.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            Fourth onyx here with one match.
            TEXT;
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
        $text = <<<'TEXT'
            A lone alpha sits at the very start of this document.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            In the middle beta and beta appear as a pair close together.
            More filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end gamma gamma gamma cluster tightly together as the densest group.
            TEXT;
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
        );

        $this->assertCount(6, $spans);
        $this->assertCount(3, $windows);
    }

    public function testSelectedWindowsNeverOverlap(): void
    {
        // Dense matches where many candidate windows overlap — selection must be non-overlapping.
        $text = 'aa bb cc dd ee ff gg hh ii jj kk ll mm nn oo pp qq rr ss tt uu vv ww xx yy zz';
        $spans = $this->findSpansForTerms($text, 'cc', 'ff', 'ii', 'll', 'oo', 'rr', 'uu', 'xx');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 15,
            prioritizeMatches: true,
        );

        $this->assertNotEmpty($windows);

        for ($i = 1; $i < \count($windows); $i++) {
            $this->assertGreaterThanOrEqual(
                $windows[$i - 1]->getEndPosition(),
                $windows[$i]->getStartPosition(),
                \sprintf(
                    'Windows %d [%d,%d] and %d [%d,%d] overlap',
                    $i - 1,
                    $windows[$i - 1]->getStartPosition(),
                    $windows[$i - 1]->getEndPosition(),
                    $i,
                    $windows[$i]->getStartPosition(),
                    $windows[$i]->getEndPosition()
                ),
            );
        }
    }

    public function testSingleMatchAllAloneGetsOneWindow(): void
    {
        $text = 'All the matches cluster into one tiny region alpha and nothing else matches anywhere at all in the remaining text.';
        $spans = $this->findSpansForTerms($text, 'alpha');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            maxFragments: 3,
            prioritizeMatches: true,
        );

        // Asked for 3 fragments, but only 1 match exists → 1 window
        $this->assertCount(1, $windows);
        $combined = $this->combinedWindowText($text, $windows);
        $this->assertStringContainsString('alpha', $combined);
    }

    public function testSlidingWindowFindsClusterAcrossSeparatedSpans(): void
    {
        // 4 distinct match terms spread across ~35 characters, each separated by non-match words.
        // The old per-match algorithm would score each span individually as [1, 1].
        // The sliding window algorithm finds a single window capturing all 4 → [4, 4].
        $text = 'Filler before alpha word beta word gamma word delta filler after all of them end here.';
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma', 'delta');

        $this->assertCount(4, $spans);

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 45,
            maxFragments: 1,
            prioritizeMatches: true,
        );

        $this->assertCount(1, $windows);
        $combined = $this->combinedWindowText($text, $windows);

        // All 4 terms captured in a single window
        $this->assertStringContainsString('alpha', $combined);
        $this->assertStringContainsString('beta', $combined);
        $this->assertStringContainsString('gamma', $combined);
        $this->assertStringContainsString('delta', $combined);
    }

    public function testWindowsAreNeverWiderThanCropLengthPlusBoundarySnap(): void
    {
        $text = 'Alpha sits in the very beginning then far away beta in the deep middle then gamma at the very end of this text here.';
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        $windows = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
        );

        foreach ($windows as $window) {
            // Each window should be roughly cropLength. Allow +10 chars for word-boundary snapping.
            $this->assertLessThanOrEqual(
                40,
                $window->getLength(),
                \sprintf(
                    'Window [%d, %d] is %d chars, exceeds cropLength+snap budget',
                    $window->getStartPosition(),
                    $window->getEndPosition(),
                    $window->getLength()
                )
            );
        }
    }

    public function testWithoutPrioritizationTakesFirstN(): void
    {
        $text = <<<'TEXT'
            A lone alpha sits at the very start of this document.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            In the middle beta and beta appear as a pair close together.
            More filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end gamma gamma gamma cluster tightly together as the densest group.
            TEXT;
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
        $text = <<<'TEXT'
            A lone alpha sits at the very start of this document.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            In the middle beta and beta appear as a pair close together.
            More filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end gamma gamma gamma cluster tightly together as the densest group.
            TEXT;
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
        $text = <<<'TEXT'
            A lone alpha sits at the very start of this document.
            Filler that contains no matches and only serves to push clusters far apart from each other.
            In the middle beta and beta appear as a pair close together.
            More filler that contains no matches and only serves to push clusters far apart from each other.
            At the very end gamma gamma gamma cluster tightly together as the densest group.
            TEXT;
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
}
