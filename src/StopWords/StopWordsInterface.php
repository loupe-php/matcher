<?php

namespace Loupe\Matcher\StopWords;

use Loupe\Matcher\Tokenizer\Token;

interface StopWordsInterface
{
    public function isStopWord(Token $token): bool;
}