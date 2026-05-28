<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\TokenCollection;

class Truncator implements Transformer
{
    private const WORD_BOUNDARIES = [' ', "\t", "\n", "\r"];

    public function __construct(
        private int $truncationLength,
        private string $truncationMarker,
        private string $highlightStartTag,
        private string $highlightEndTag,
    ) {
    }

    public function transform(string $text, TokenCollection|string $query, TokenCollection $matches): string
    {
        if ($this->truncationLength <= 0 || $text === '') {
            return $text;
        }

        $hasTags = $this->highlightStartTag !== '' && $this->highlightEndTag !== '';

        if ($hasTags) {
            $pattern = '/(' . preg_quote($this->highlightStartTag, '/') . '|' . preg_quote($this->highlightEndTag, '/') . ')/u';
            $segments = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        } else {
            $segments = [$text];
        }

        if ($segments === false) {
            return $text;
        }

        $result = '';
        $visibleLength = 0;
        $insideHighlight = false;
        $lastVisibleChar = null;

        $checkpointResult = '';
        $checkpointInsideHighlight = false;
        $wasTruncated = false;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($hasTags && $segment === $this->highlightStartTag) {
                $result .= $segment;
                $insideHighlight = true;
                continue;
            }

            if ($hasTags && $segment === $this->highlightEndTag) {
                $result .= $segment;
                $insideHighlight = false;
                continue;
            }

            $remainingBudget = $this->truncationLength - $visibleLength + 1;
            $relevantSegment = mb_substr($segment, 0, $remainingBudget, 'UTF-8');

            foreach (mb_str_split($relevantSegment, 1, 'UTF-8') as $char) {
                $isBoundary = \in_array($char, self::WORD_BOUNDARIES, true);

                if ($isBoundary && $lastVisibleChar !== null && !\in_array($lastVisibleChar, self::WORD_BOUNDARIES, true)) {
                    $checkpointResult = $result;
                    $checkpointInsideHighlight = $insideHighlight;
                }

                if ($visibleLength >= $this->truncationLength) {
                    $wasTruncated = true;
                    break 2;
                }

                $result .= $char;
                $visibleLength++;
                $lastVisibleChar = $char;
            }
        }

        if (!$wasTruncated) {
            return $result;
        }

        $result = $checkpointResult;
        if ($checkpointInsideHighlight) {
            $result .= $this->highlightEndTag;
        }
        $result .= $this->truncationMarker;

        return $result;
    }
}
