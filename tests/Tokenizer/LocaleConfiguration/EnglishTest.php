<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\LocaleConfiguration\English;
use PHPUnit\Framework\TestCase;

class EnglishTest extends TestCase
{
    public function testDictionary(): void
    {
        $dictionary = new English();
        $this->assertTrue($dictionary->getDictionary()->has('classroom'));
        $this->assertTrue($dictionary->getDictionary()->has('toothpaste'));
        $this->assertFalse($dictionary->getDictionary()->has('ting'));
    }
}
