<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\LocaleConfiguration\German;
use PHPUnit\Framework\TestCase;

class GermanTest extends TestCase
{
    public function testDictionary(): void
    {
        $dictionary = new German();
        $this->assertTrue($dictionary->getDictionary()->has('dampf'));
        $this->assertTrue($dictionary->getDictionary()->has('donau'));
        $this->assertTrue($dictionary->getDictionary()->has('fahrt'));
        $this->assertTrue($dictionary->getDictionary()->has('gesell'));
        $this->assertTrue($dictionary->getDictionary()->has('kapitan'));
        $this->assertTrue($dictionary->getDictionary()->has('schiff'));
    }
}
