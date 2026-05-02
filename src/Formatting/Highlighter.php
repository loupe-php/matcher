<?php

declare(strict_types=1);

namespace Loupe\Matcher\Formatting;

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

        $text = $input->getText();
        $result = '';
        $end = 0;

        foreach ($input->getSpans() as $span) {
            $result .= mb_substr($text, $end, $span->getStartPosition() - $end, 'UTF-8');
            $result .= $this->startTag;
            $result .= mb_substr($text, $span->getStartPosition(), $span->getLength(), 'UTF-8');
            $result .= $this->endTag;
            $end = $span->getEndPosition();
        }

        $result .= mb_substr($text, $end, null, 'UTF-8');

        // Spans are no longer accurate after tag insertion; the highlighter is terminal.
        return new FormattedText($result, []);
    }
}
