<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\TokenCollection;

class Truncator implements Transformer
{
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
        $truncatedLength = 0;
        $wasTruncated = false;
        $insideHighlight = false;

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

            $segmentLength = mb_strlen($segment, 'UTF-8');

            if ($truncatedLength + $segmentLength <= $this->truncationLength) {
                $result .= $segment;
                $truncatedLength += $segmentLength;
                continue;
            }

            $remainingLength = $this->truncationLength - $truncatedLength;
            $wasTruncatedAt = $this->snapBackToBoundary($segment, $remainingLength);
            $result .= mb_substr($segment, 0, $wasTruncatedAt, 'UTF-8');

            if ($insideHighlight) {
                $result .= $this->highlightEndTag;
            }

            $wasTruncated = true;
            break;
        }

        if ($wasTruncated) {
            $result .= $this->truncationMarker;
        }

        return $result;
    }

    private function snapBackToBoundary(string $segment, int $maxLength): int
    {
        if ($maxLength <= 0) {
            return 0;
        }

        $segmentLength = mb_strlen($segment, 'UTF-8');
        if ($maxLength >= $segmentLength) {
            return $segmentLength;
        }

        $boundaries = [' ', "\t", "\n", "\r"];
        for ($i = $maxLength; $i > 0; $i--) {
            $char = mb_substr($segment, $i - 1, 1, 'UTF-8');
            if (\in_array($char, $boundaries, true)) {
                return $i - 1;
            }
        }

        return $maxLength;
    }
}
