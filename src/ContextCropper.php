<?php

declare(strict_types=1);

namespace Loupe\ContextCropper;

use Symfony\Component\String\UnicodeString;

class ContextCropper
{
    public function __construct(
        private readonly int $numberOfContextChars = 30,
        private readonly string $contextEllipsis = '[…]',
        private readonly string $preTag = '<mark>',
        private readonly string $postTag = '</mark>'
    ) {

    }

    public function apply(string $context): string
    {
        $context = new UnicodeString($context);
        $chunks = [];

        foreach ($context->split($this->preTag) as $chunk) {
            foreach ($chunk->split($this->postTag, 2) as $innerChunk) {
                $chunks[] = $innerChunk;
            }
        }

        if (\count($chunks) < 3 || \count($chunks) % 2 !== 1) {
            return $context->toString();
        }

        $result = [];

        foreach ($chunks as $i => $chunk) {
            // Even = context, Odd = highlighted key phrases
            if ($i % 2 === 0) {
                // The first chunk only ever has to be prepended
                if ($i === 0) {
                    $result[] = $this->trim($chunk, true)->toString();
                    // The last chunk only ever has to be appended
                } elseif ($i === \count($chunks) - 1) {
                    $result[] = $this->trim($chunk, false)->toString();
                    // An in-between chunk has to be left untouched, if it is shorter or equal the desired context length
                } elseif ($chunk->length() <= $this->numberOfContextChars) {
                    $result[] = $chunk->toString();
                    // Otherwise we have to prepend and append
                } else {
                    $pre = $this->trim($chunk, true);
                    $post = $this->trim($chunk, false);

                    // If both have been shortened, we would have a double ellipsis now, so let's trim that
                    if ($post->endsWith($this->contextEllipsis) && $pre->startsWith($this->contextEllipsis)) {
                        $post = $post->trimSuffix($this->contextEllipsis);
                    }

                    $result[] = $post->append($pre->toString())->toString();
                }
            } else {
                // Highlighted chunk, leave that untouched with the tags
                $result[] = $chunk->prepend($this->preTag)->append($this->postTag)->toString();
            }
        }

        return implode('', $result);
    }

    private function trim(UnicodeString $string, bool $fromEnd): UnicodeString
    {
        $truncated = $fromEnd
            ? $string->reverse()->truncate($this->numberOfContextChars, cut: false)->reverse()
            : $string->truncate($this->numberOfContextChars, cut: false);

        if ($truncated->equalsTo($string)) {
            return $string;
        }

        return $fromEnd
            ? $truncated->prepend($this->contextEllipsis)
            : $truncated->append($this->contextEllipsis);
    }
}
