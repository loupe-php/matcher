<?php

declare(strict_types=1);

namespace Loupe\Matcher\StopWords;

use Loupe\Matcher\Tokenizer\Token;

class InMemoryStopWords implements StopWordsInterface
{
    /**
     * @var array<string, true>
     */
    private array $stopWordsIndex = [];

    /**
     * @param array<string> $stopWords
     */
    public function __construct(
        private array $stopWords = []
    ) {
        $this->stopWordsIndex = array_fill_keys($stopWords, true);
    }

    public function isStopWord(Token $token): bool
    {
        return isset($this->stopWordsIndex[$token->getTerm()]);
    }
}
