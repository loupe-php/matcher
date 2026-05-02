<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;

/**
 * Carrier for text + match spans through the formatter pipeline.
 * Spans are always in coordinates of the accompanying $text.
 */
class FormattedText
{
    /**
     * @param MatchSpan[] $spans
     */
    public function __construct(
        public readonly string $text,
        public readonly array $spans = [],
    ) {
    }
}
