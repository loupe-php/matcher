<?php

declare(strict_types=1);

namespace Loupe\Matcher\Tests\Tokenizer\LocaleConfiguration;

use Loupe\Matcher\Tokenizer\LocaleConfiguration\Dutch;
use PHPUnit\Framework\TestCase;

class DutchTest extends TestCase
{
    public function testDictionary(): void
    {
        $dictionary = new Dutch();
        $this->assertTrue($dictionary->getDictionary()->has('ziekte'));
        $this->assertTrue($dictionary->getDictionary()->has('kosten'));
        $this->assertTrue($dictionary->getDictionary()->has('verzekering'));
    }
}
