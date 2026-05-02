<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

use Loupe\Matcher\Tokenizer\MatchSpan;

class Highlighter implements Transformer
{
    public function __construct(
        private string $startTag,
        private string $endTag,
    ) {
    }

    public function transform(FormattedText $input): FormattedText
    {
        if ($input->getSpans() === [] || $this->startTag === '' || $this->endTag === '') {
            return $input;
        }

        $startTagLength = mb_strlen($this->startTag, 'UTF-8');
        $endTagLength = mb_strlen($this->endTag, 'UTF-8');

        $text = $input->getText();
        $result = '';
        $end = 0;
        $offset = 0;
        $rebasedSpans = [];

        foreach ($input->getSpans() as $span) {
            $result .= mb_substr($text, $end, $span->getStartPosition() - $end, 'UTF-8');
            $result .= $this->startTag;
            $result .= mb_substr($text, $span->getStartPosition(), $span->getLength(), 'UTF-8');
            $result .= $this->endTag;
            $end = $span->getEndPosition();

            $offset += $startTagLength;
            $rebasedSpans[] = new MatchSpan(
                $span->getStartPosition() + $offset,
                $span->getEndPosition() + $offset,
                $span->getTerms(),
            );
            $offset += $endTagLength;
        }

        $result .= mb_substr($text, $end, null, 'UTF-8');

        return new FormattedText($result, $rebasedSpans);
    }
}
