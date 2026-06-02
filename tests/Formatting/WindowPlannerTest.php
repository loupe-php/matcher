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

    public function testTagOverheadCanChangePrioritizationOutcome(): void
    {
        $text = <<<'TEXT'
            Here alpha and then more words before beta in text.
            Far far far far far far far far far far far far far far far away from the first cluster entirely.
            At the end gamma gamma gamma tightly packed.
            TEXT;
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta', 'gamma');

        // Without overhead a wide window around alpha also captures beta → 2 distinct terms,
        // which beats gamma's 1 distinct term under prioritization.
        $withoutOverhead = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            tagOverhead: 0,
            maxFragments: 1,
            prioritizeMatches: true,
        );
        $textWithout = $this->combinedWindowText($text, $withoutOverhead);
        $this->assertStringContainsString('alpha', $textWithout);
        $this->assertStringContainsString('beta', $textWithout);

        // With large overhead the window around alpha shrinks and no longer reaches beta →
        // both clusters score 1 distinct term, but gamma has 3 total matches and wins.
        $withOverhead = (new WindowPlanner())->planCropWindows(
            $text,
            $spans,
            cropLength: 30,
            tagOverhead: 15,
            maxFragments: 1,
            prioritizeMatches: true,
        );
        $textWith = $this->combinedWindowText($text, $withOverhead);
        $this->assertStringContainsString('gamma', $textWith);
        $this->assertStringNotContainsString('alpha', $textWith);
    }

    public function testTagOverheadCanPreventWindowMerging(): void
    {
        $text = 'Words before alpha and then a moderate gap of several filler words separating them before beta appears after.';
        $spans = $this->findSpansForTerms($text, 'alpha', 'beta');

        $merged = (new WindowPlanner())->planCropWindows($text, $spans, cropLength: 50, tagOverhead: 0);
        $separate = (new WindowPlanner())->planCropWindows($text, $spans, cropLength: 50, tagOverhead: 20);

        // Without overhead the wider windows overlap and merge into one
        $this->assertCount(1, $merged);
        // With overhead the narrower windows remain separate
        $this->assertCount(2, $separate);
    }

    public function testTagOverheadProducesNarrowerWindow(): void
    {
        $text = 'Plenty of prefix filler content before the keyword and plenty of suffix filler content after it ends here at last.';
        $spans = $this->findSpansForTerms($text, 'keyword');

        $without = (new WindowPlanner())->planCropWindows($text, $spans, cropLength: 40, tagOverhead: 0);
        $with = (new WindowPlanner())->planCropWindows($text, $spans, cropLength: 40, tagOverhead: 20);

        $this->assertCount(1, $without);
        $this->assertCount(1, $with);
        $this->assertLessThan($without[0]->getLength(), $with[0]->getLength());
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
